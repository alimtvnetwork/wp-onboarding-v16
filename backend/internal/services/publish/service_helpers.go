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
	loglevel "wp-plugin-publish/internal/enums/log_level"
	uploadsource "wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
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

	var mapping models.PluginMapping
	var lastSyncAt, lastBackupAt, createdAt, updatedAt sql.NullString

	err := row.Scan(
		&mapping.ID, &mapping.PluginID, &mapping.SiteID,
		&mapping.RemoteSlug, &mapping.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAt, &updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "mapping not found")
	}

	mapping.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	mapping.LastBackupAt = dbops.ParseNullTime(lastBackupAt)
	mapping.CreatedAt = dbops.ParseDateTime(createdAt.String)
	mapping.UpdatedAt = dbops.ParseDateTime(updatedAt.String)

	return &mapping, nil
}

// getSiteCredentials retrieves site info and decrypted password
func (s *Service) getSiteCredentials(ctx context.Context, siteID int64) (*models.Site, string, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Id = ?
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
		return nil, "", apperror.Wrap(err, apperror.ErrNotFound, "site not found")
	}

	site.LastTestedAt = dbops.ParseNullTime(lastTestedAt)
	site.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	site.CreatedAt = dbops.ParseDateTime(createdAt.String)
	site.UpdatedAt = dbops.ParseDateTime(updatedAt.String)

	var password string
	if s.sitePasswordDecryptor != nil {
		password, err = s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
		if err != nil {
			s.log.Warn("Failed to decrypt password", "siteId", siteID, "error", err)
			return nil, "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
		}
	}

	return &site, password, nil
}

// uploadPlugin uploads a plugin ZIP via the Riseup Asia Uploader companion plugin.
// Returns (performed, result, alreadyActivated, error).
func (s *Service) uploadPlugin(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) (bool, *wordpress.OnboardUploadResult, bool, error) {
	uploaderAvailable, _, _ := wpClient.CheckRiseupAsiaAvailable()
	if !uploaderAvailable {
		s.log.Warn("Riseup Asia Uploader not available; upload simulated", "slug", slug)
		if info, err := os.Stat(zipPath); err == nil {
			s.log.Info("Plugin upload prepared (simulated)", "slug", slug, "size", info.Size())
		}
		return false, nil, false, nil
	}

	s.log.Info("Using Riseup Asia Uploader for upload", "slug", slug)
	result, err := wpClient.UploadPluginViaUploader(zipPath, slug, true, uploadsource.RestAPI)
	if err != nil {
		return true, nil, false, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via uploader helper")
	}

	onboardResult := &wordpress.OnboardUploadResult{
		Success:     result.Success,
		Message:     result.Message,
		PluginSlug:  slug,
		Overwritten: true,
	}
	if result.PluginDetails != nil {
		onboardResult.PluginName = result.PluginDetails.Name
		onboardResult.Version = result.PluginDetails.Version
	}

	s.log.Info("Plugin uploaded via Riseup Asia Uploader",
		"slug", slug, "success", result.Success,
		"message", result.Message, "activated", result.Activated)

	return true, onboardResult, result.Activated, nil
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

		filePath, err := pathutil.Join(absPath, entry.Name())
		if err != nil {
			continue
		}
		content, err := os.ReadFile(filePath)
		if err != nil {
			continue
		}

		contentStr := string(content)
		if !strings.Contains(contentStr, "Plugin Name:") {
			continue
		}

		lines := strings.Split(contentStr, "\n")
		for _, line := range lines {
			trimmed := strings.TrimSpace(line)
			if strings.HasPrefix(trimmed, "* Version:") || strings.HasPrefix(trimmed, "*Version:") {
				version := strings.TrimPrefix(trimmed, "* Version:")
				version = strings.TrimPrefix(version, "*Version:")
				version = strings.TrimSpace(version)
				if version != "" {
					return version
				}
			}
			if strings.HasPrefix(trimmed, "Version:") {
				version := strings.TrimPrefix(trimmed, "Version:")
				version = strings.TrimSpace(version)
				if version != "" {
					return version
				}
			}
		}
	}

	return ""
}

// cleanupZip handles ZIP file cleanup after publish
func (s *Service) cleanupZip(pluginID, siteID int64, zipPath string, publishFailed, keepZipFiles bool) {
	if zipPath == "" {
		return
	}

	if publishFailed {
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP for debugging (publish failed): %s", zipPath), toDetails(CleanupDetails{
			ZipPath: zipPath, Reason: "publish_failed",
		}))
		return
	}

	if keepZipFiles {
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP (user setting): %s", zipPath), toDetails(CleanupDetails{
			ZipPath: zipPath, KeepZipFiles: true,
		}))
		return
	}

	s.broadcastDetailedLog(pluginID, siteID, "debug", "cleanup", fmt.Sprintf("Removing temp ZIP: %s", zipPath), toDetails(CleanupDetails{
		KeepZipFiles: keepZipFiles,
	}))
	os.Remove(zipPath)
}

// logZipCreated logs the ZIP file creation details
func (s *Service) logZipCreated(pluginID, siteID int64, zipPath string, fileCount int) {
	info, statErr := os.Stat(zipPath)
	if statErr != nil {
		return
	}

	zipEntries := s.getZipStructure(zipPath)
	s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(zipPath), info.Size()), toDetails(ZipCreatedDetails{
		ZipPath: zipPath, ZipSize: info.Size(), FileCount: fileCount, ZipStructure: zipEntries,
	}))

	maxShow := 20
	if len(zipEntries) < maxShow {
		maxShow = len(zipEntries)
	}
	for i := 0; i < maxShow; i++ {
		s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  └─ %s", zipEntries[i]), nil)
	}
	if len(zipEntries) > 20 {
		s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  ... and %d more files", len(zipEntries)-20), nil)
	}
}

// logUploadError logs a structured upload error
func (s *Service) logUploadError(pluginID, siteID int64, sessionID string, siteInfo *models.Site, mapping *models.PluginMapping, attempts int, err error) {
	inner := UploadErrorInner{
		RemoteSlug: mapping.RemoteSlug,
		Attempts:   attempts,
	}
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
	s.broadcastStageLog(pluginID, siteID, sessionID, "error", "upload", StageContext{
		What:    fmt.Sprintf("Upload ZIP to %s", siteInfo.URL),
		Result:  fmt.Sprintf("FAILED: %s", err.Error()),
		Details: toDetails(inner),
	})
}

// logUploadSuccess logs a structured upload success
func (s *Service) logUploadSuccess(pluginID, siteID int64, sessionID string, mapping *models.PluginMapping, zipSize int64, startTime time.Time, activated bool, attempts int, uploadResult *wordpress.OnboardUploadResult) {
	resultMsg := "Plugin uploaded successfully"
	if activated {
		resultMsg = "Plugin uploaded and activated"
	}
	inner := UploadSuccessInner{
		RemoteSlug: mapping.RemoteSlug,
		Activated:  activated,
		DurationMs: time.Since(startTime).Milliseconds(),
		Attempts:   attempts,
	}
	if uploadResult != nil {
		inner.Version = uploadResult.Version
		inner.Overwritten = uploadResult.Overwritten
	}
	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "upload", StageContext{
		What:    fmt.Sprintf("Upload ZIP (%s)", formatBytes(zipSize)),
		Result:  resultMsg,
		Details: toDetails(inner),
	})
}

// logActivationError logs a structured activation error
func (s *Service) logActivationError(pluginID, siteID int64, sessionID string, mapping *models.PluginMapping, endpointURL string, startTime time.Time, err error) {
	inner := ActivateErrorInner{
		RemoteSlug: mapping.RemoteSlug,
		DurationMs: time.Since(startTime).Milliseconds(),
	}
	if apiErr, ok := err.(*wordpress.APIError); ok {
		inner.Request = &ActivateRequestInfo{
			Method: apiErr.Method, Endpoint: apiErr.Endpoint, URL: apiErr.URL,
		}
		inner.Response = &ActivateResponseInfo{
			Status: apiErr.StatusCode, Body: truncateString(apiErr.ResponseBody, 2000),
		}
	}
	s.broadcastStageLog(pluginID, siteID, sessionID, loglevel.Error.String(), "activate", StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after upload",
		Where:   endpointURL,
		Result:  fmt.Sprintf("FAILED: %s", err.Error()),
		Details: toDetails(inner),
	})
}
