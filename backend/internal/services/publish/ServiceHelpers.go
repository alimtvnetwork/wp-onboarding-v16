package publish

import (
	"context"
	"crypto/md5"
	"database/sql"
	"fmt"
	"io"
	"os"
	"strings"

	"wp-plugin-publish/internal/database/dbops"
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

// SiteCredentialsResult holds a site and its decrypted password.
type SiteCredentialsResult struct {
	Site     *models.Site
	Password string
}

// getSiteCredentials retrieves site info and decrypted password
func (s *Service) getSiteCredentials(ctx context.Context, siteID int64) (*SiteCredentialsResult, error) {
	site, err := s.querySite(ctx, siteID)
	if err != nil {
		return nil, err
	}

	password, err := s.decryptSitePassword(ctx, siteID)
	if err != nil {
		return nil, err
	}

	result := &SiteCredentialsResult{
		Site:     site,
		Password: password,
	}
	return result, nil
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

// UploadOutcome holds the result of an upload attempt.
type UploadOutcome struct {
	IsPerformed  bool
	UploadResult *wordpress.OnboardUploadResult
	IsActivated  bool
}

// uploadPlugin uploads a plugin ZIP via the Riseup Asia Uploader.
func (s *Service) uploadPlugin(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) apperror.Result[UploadOutcome] {
	availability, _ := wpClient.CheckRiseupAsiaAvailable()
	if availability == nil || !availability.Available {
		return s.simulateUpload(zipPath, slug)
	}

	return s.performRealUpload(wpClient, zipPath, slug)
}

// simulateUpload logs a simulated upload when no uploader is available.
func (s *Service) simulateUpload(zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Warn("Riseup Asia Uploader not available; upload simulated", "slug", slug)
	if fi, appErr := pathutil.StatFile(zipPath); appErr == nil {
		s.log.Info("Plugin upload prepared (simulated)", "slug", slug, "size", fi.Info.Size())
	}

	return apperror.Ok(UploadOutcome{})
}

// performRealUpload uploads via the Riseup Asia Uploader.
func (s *Service) performRealUpload(wpClient *wordpress.Client, zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Info("Using Riseup Asia Uploader for upload", "slug", slug)

	uploadInput := wordpress.UploadInput{
		ZipPath:      zipPath,
		Slug:         slug,
		IsActivate:   true,
		UploadSource: uploadsource.RestAPI,
	}
	result, err := wpClient.UploadPluginViaUploader(uploadInput)
	if err != nil {
		return apperror.Fail[UploadOutcome](apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via uploader helper"))
	}

	onboardResult := buildOnboardResult(slug, result)
	s.log.Info("Plugin uploaded via Riseup Asia Uploader",
		"slug", slug,
		"success", result.Success,
		"message", result.Message,
		"activated", result.Activated,
	)

	outcome := UploadOutcome{
		IsPerformed:  true,
		UploadResult: onboardResult,
		IsActivated:  result.Activated,
	}
	return apperror.Ok(outcome)
}

// buildOnboardResult converts uploader result to OnboardUploadResult.
func buildOnboardResult(slug string, result *wordpress.UploaderUploadResult) *wordpress.OnboardUploadResult {
	r := &wordpress.OnboardUploadResult{
		Success:     result.Success,
		Message:     result.Message,
		PluginSlug:  slug,
		Overwritten: true,
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
		version := s.extractVersionFromPhpFile(absPath, entry.Name())
		if version != "" {
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
		version := parseVersionLine(strings.TrimSpace(line))
		if version != "" {
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
