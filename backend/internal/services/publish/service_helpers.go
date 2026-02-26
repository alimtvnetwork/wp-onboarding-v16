package publish

import (
	"context"
	"crypto/md5"
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/pathutil"
)

// getMapping retrieves the plugin-site mapping
func (s *Service) getMapping(ctx context.Context, pluginID, siteID int64) (*models.PluginMapping, error) {
	query := `
		SELECT Id, PluginId, SiteId, RemoteSlug, SyncStatus, LastSyncAt, LastBackupAt, CreatedAt, UpdatedAt
		FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`
	row := s.db.QueryRowContext(ctx, query, pluginID, siteID)
	return scanMapping(row)
}

// scanMapping scans a mapping row into a PluginMapping.
func scanMapping(row *sql.Row) (*models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAt, &updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "mapping not found")
	}

	m.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	m.LastBackupAt = dbops.ParseNullTime(lastBackupAt)
	m.CreatedAt = dbops.ParseDateTime(createdAt.String)
	m.UpdatedAt = dbops.ParseDateTime(updatedAt.String)
	return &m, nil
}

// getSiteCredentials retrieves site info and decrypted password
func (s *Service) getSiteCredentials(ctx context.Context, siteID int64) (*models.Site, string, error) {
	site, err := s.querySite(ctx, siteID)
	if err != nil {
		return nil, "", err
	}

	password, err := s.decryptSitePassword(ctx, siteID)
	if err != nil {
		return nil, "", err
	}
	return site, password, nil
}

// querySite fetches a site by ID.
func (s *Service) querySite(ctx context.Context, siteID int64) (*models.Site, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus,
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites WHERE Id = ?
	`
	row := s.db.QueryRowContext(ctx, query, siteID)

	var site models.Site
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&site.ID, &site.Name, &site.URL, &site.Username,
		&site.PasswordEncrypted, &site.ConnectionStatus,
		&lastTestedAt, &lastSyncAt, &createdAt, &updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "site not found")
	}

	site.LastTestedAt = dbops.ParseNullTime(lastTestedAt)
	site.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	site.CreatedAt = dbops.ParseDateTime(createdAt.String)
	site.UpdatedAt = dbops.ParseDateTime(updatedAt.String)
	return &site, nil
}

// decryptSitePassword decrypts the site password via the decryptor.
func (s *Service) decryptSitePassword(ctx context.Context, siteID int64) (string, error) {
	if s.sitePasswordDecryptor == nil {
		return "", nil
	}
	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		s.log.Warn("Failed to decrypt password", "siteId", siteID, "error", err)
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
	}
	return password, nil
}

// uploadPlugin uploads a plugin ZIP via the Riseup Asia Uploader.
func (s *Service) uploadPlugin(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) (bool, *wordpress.OnboardUploadResult, bool, error) {
	uploaderAvailable, _, _ := wpClient.CheckRiseupAsiaAvailable()
	if !uploaderAvailable {
		return s.simulateUpload(zipPath, slug)
	}

	return s.performRealUpload(wpClient, zipPath, slug)
}

// simulateUpload logs a simulated upload when no uploader is available.
func (s *Service) simulateUpload(zipPath, slug string) (bool, *wordpress.OnboardUploadResult, bool, error) {
	s.log.Warn("Riseup Asia Uploader not available; upload simulated", "slug", slug)
	if fi, appErr := pathutil.StatFile(zipPath); appErr == nil {
		s.log.Info("Plugin upload prepared (simulated)", "slug", slug, "size", fi.Info.Size())
	}
	return false, nil, false, nil
}

// performRealUpload uploads via the Riseup Asia Uploader.
func (s *Service) performRealUpload(wpClient *wordpress.Client, zipPath, slug string) (bool, *wordpress.OnboardUploadResult, bool, error) {
	s.log.Info("Using Riseup Asia Uploader for upload", "slug", slug)
	result, err := wpClient.UploadPluginViaUploader(zipPath, slug, true, uploadsource.RestAPI)
	if err != nil {
		return true, nil, false, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via uploader helper")
	}

	onboardResult := buildOnboardResult(slug, result)
	s.log.Info("Plugin uploaded via Riseup Asia Uploader", "slug", slug, "success", result.Success, "message", result.Message, "activated", result.Activated)
	return true, onboardResult, result.Activated, nil
}

// buildOnboardResult converts uploader result to OnboardUploadResult.
func buildOnboardResult(slug string, result *wordpress.UploaderUploadResult) *wordpress.OnboardUploadResult {
	r := &wordpress.OnboardUploadResult{
		Success: result.Success, Message: result.Message, PluginSlug: slug, Overwritten: true,
	}
	if result.PluginDetails != nil {
		r.PluginName = result.PluginDetails.Name
		r.Version = result.PluginDetails.Version
	}
	return r
}

// formatBytes formats byte count as human-readable string
func formatBytes(bytes int64) string {
	const unit = 1024
	if bytes < unit {
		return fmt.Sprintf("%d B", bytes)
	}
	div, exp := int64(unit), 0
	for n := bytes / unit; n >= unit; n /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f %cB", float64(bytes)/float64(div), "KMGTPE"[exp])
}

// truncateString truncates a string to maxLen with ellipsis
func truncateString(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen-3] + "..."
}

// calculateFileHash computes MD5 hash of a file
func (s *Service) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	h := md5.New()
	if _, err := io.Copy(h, file); err != nil {
		return "", err
	}
	return fmt.Sprintf("%x", h.Sum(nil)), nil
}

// getLocalPluginVersion extracts the version from a WordPress plugin's main PHP file header
func (s *Service) getLocalPluginVersion(pluginPath string) string {
	absPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return ""
	}

	entries, err := os.ReadDir(absPath)
	if err != nil {
		return ""
	}

	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php") {
			continue
		}
		if version := s.extractVersionFromPhpFile(absPath, entry.Name()); version != "" {
			return version
		}
	}
	return ""
}

// extractVersionFromPhpFile reads a PHP file and extracts the Version header.
func (s *Service) extractVersionFromPhpFile(dirPath, fileName string) string {
	filePath, err := pathutil.Join(dirPath, fileName)
	if err != nil {
		return ""
	}
	content, err := os.ReadFile(filePath)
	if err != nil || !strings.Contains(string(content), "Plugin Name:") {
		return ""
	}

	for _, line := range strings.Split(string(content), "\n") {
		if version := parseVersionLine(strings.TrimSpace(line)); version != "" {
			return version
		}
	}
	return ""
}

// parseVersionLine extracts a version string from a PHP header comment line.
func parseVersionLine(trimmed string) string {
	prefixes := []string{"* Version:", "*Version:", "Version:"}
	for _, prefix := range prefixes {
		if strings.HasPrefix(trimmed, prefix) {
			version := strings.TrimSpace(strings.TrimPrefix(trimmed, prefix))
			if version != "" {
				return version
			}
		}
	}
	return ""
}

// cleanupZip handles ZIP file cleanup after publish
func (s *Service) cleanupZip(pluginID, siteID int64, zipPath string, isPublishFailed, isKeepZipFiles bool) {
	if zipPath == "" {
		return
	}

	if isPublishFailed {
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP for debugging (publish failed): %s", zipPath), toDetails(CleanupDetails{ZipPath: zipPath, Reason: "publish_failed"}))
		return
	}

	if isKeepZipFiles {
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP (user setting): %s", zipPath), toDetails(CleanupDetails{ZipPath: zipPath, IsKeepZipFiles: true}))
		return
	}

	s.broadcastDetailedLog(pluginID, siteID, "debug", "cleanup", fmt.Sprintf("Removing temp ZIP: %s", zipPath), toDetails(CleanupDetails{IsKeepZipFiles: isKeepZipFiles}))
	os.Remove(zipPath)
}

// logZipCreated logs the ZIP file creation details
func (s *Service) logZipCreated(pluginID, siteID int64, zipPath string, fileCount int) {
	fi, statErr := pathutil.StatFile(zipPath)
	if statErr != nil {
		return
	}

	zipEntries := s.getZipStructure(zipPath)
	s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(zipPath), fi.Info.Size()), toDetails(ZipCreatedDetails{ZipPath: zipPath, ZipSize: fi.Info.Size(), FileCount: fileCount, ZipStructure: zipEntries}))
	s.logZipEntries(pluginID, siteID, zipEntries)
}

// logZipEntries logs individual zip entries (up to 20).
func (s *Service) logZipEntries(pluginID, siteID int64, entries []string) {
	maxShow := 20
	if len(entries) < maxShow {
		maxShow = len(entries)
	}
	for i := 0; i < maxShow; i++ {
		s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  └─ %s", entries[i]), nil)
	}
	if len(entries) > 20 {
		s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  ... and %d more files", len(entries)-20), nil)
	}
}

// logUploadError logs a structured upload error
func (s *Service) logUploadError(pluginID, siteID int64, sessionID string, siteInfo *models.Site, mapping *models.PluginMapping, attempts int, err error) {
	inner := buildUploadErrorInner(mapping.RemoteSlug, attempts, err)
	s.broadcastStageLog(pluginID, siteID, sessionID, "error", "upload", StageContext{
		What: fmt.Sprintf("Upload ZIP to %s", siteInfo.URL), Result: fmt.Sprintf("FAILED: %s", err.Error()), Details: toDetails(inner),
	})
}

// buildUploadErrorInner extracts structured error info.
func buildUploadErrorInner(slug string, attempts int, err error) UploadErrorInner {
	inner := UploadErrorInner{RemoteSlug: slug, Attempts: attempts}
	if apiErr, ok := err.(*wordpress.APIError); ok {
		inner.Status = apiErr.StatusCode
		inner.Response = truncateString(apiErr.ResponseBody, 2000)
	} else if appErr, ok := err.(*apperror.AppError); ok {
		inner.Code = appErr.Code
		if cause := appErr.Unwrap(); cause != nil {
			if apiErr, ok := cause.(*wordpress.APIError); ok {
				inner.Status = apiErr.StatusCode
				inner.Response = truncateString(apiErr.ResponseBody, 2000)
			}
		}
	}
	return inner
}

// logUploadSuccess logs a structured upload success
func (s *Service) logUploadSuccess(pluginID, siteID int64, sessionID string, mapping *models.PluginMapping, zipSize int64, startTime time.Time, isActivated bool, attempts int, uploadResult *wordpress.OnboardUploadResult) {
	resultMsg := "Plugin uploaded successfully"
	if isActivated {
		resultMsg = "Plugin uploaded and activated"
	}
	inner := buildUploadSuccessInner(mapping.RemoteSlug, isActivated, startTime, attempts, uploadResult)
	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "upload", StageContext{
		What: fmt.Sprintf("Upload ZIP (%s)", formatBytes(zipSize)), Result: resultMsg, Details: toDetails(inner),
	})
}

// buildUploadSuccessInner constructs success detail struct.
func buildUploadSuccessInner(slug string, isActivated bool, startTime time.Time, attempts int, result *wordpress.OnboardUploadResult) UploadSuccessInner {
	inner := UploadSuccessInner{
		RemoteSlug: slug, Activated: isActivated, DurationMs: time.Since(startTime).Milliseconds(), Attempts: attempts,
	}
	if result != nil {
		inner.Version = result.Version
		inner.Overwritten = result.Overwritten
	}
	return inner
}

// logActivationError logs a structured activation error
func (s *Service) logActivationError(pluginID, siteID int64, sessionID string, mapping *models.PluginMapping, endpointURL string, startTime time.Time, err error) {
	inner := buildActivateErrorInner(mapping.RemoteSlug, startTime, err)
	s.broadcastStageLog(pluginID, siteID, sessionID, loglevel.Error.String(), "activate", StageContext{
		What: "Activate plugin via Riseup Asia Uploader", Why: "Enable plugin after upload",
		Where: endpointURL, Result: fmt.Sprintf("FAILED: %s", err.Error()), Details: toDetails(inner),
	})
}

// buildActivateErrorInner constructs activation error details.
func buildActivateErrorInner(slug string, startTime time.Time, err error) ActivateErrorInner {
	inner := ActivateErrorInner{RemoteSlug: slug, DurationMs: time.Since(startTime).Milliseconds()}
	if apiErr, ok := err.(*wordpress.APIError); ok {
		inner.Request = &ActivateRequestInfo{Method: apiErr.Method, Endpoint: apiErr.Endpoint, URL: apiErr.URL}
		inner.Response = &ActivateResponseInfo{Status: apiErr.StatusCode, Body: truncateString(apiErr.ResponseBody, 2000)}
	}
	return inner
}
