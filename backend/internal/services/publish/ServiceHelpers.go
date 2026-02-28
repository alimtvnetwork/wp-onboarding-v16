package publish

import (
	"context"
	"crypto/md5"
	"database/sql"
	"fmt"
	"io"
	"os"

	"wp-plugin-publish/internal/database/dbops"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
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

	applyMappingTimestamps(&m, mappingTimestamps{
		LastSync:   lastSyncAt,
		LastBackup: lastBackupAt,
		Created:    createdAt,
		Updated:    updatedAt,
	})

	return &m, nil
}

// mappingTimestamps bundles nullable timestamp fields for PluginMapping.
type mappingTimestamps struct {
	LastSync   sql.NullString
	LastBackup sql.NullString
	Created    sql.NullString
	Updated    sql.NullString
}

// applyMappingTimestamps parses and assigns timestamp fields on a PluginMapping.
func applyMappingTimestamps(m *models.PluginMapping, ts mappingTimestamps) {
	m.LastSyncAt = dbops.ParseNullTime(ts.LastSync)
	m.LastBackupAt = dbops.ParseNullTime(ts.LastBackup)
	m.CreatedAt = dbops.ParseDateTime(ts.Created.String)
	m.UpdatedAt = dbops.ParseDateTime(ts.Updated.String)
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

	return scanSiteRow(row)
}

// scanSiteRow scans a site row into a models.Site.
func scanSiteRow(row *sql.Row) (*models.Site, error) {
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

	applySiteTimestamps(&site, siteTimestamps{
		LastTested: lastTestedAt,
		LastSync:   lastSyncAt,
		Created:    createdAt,
		Updated:    updatedAt,
	})

	return &site, nil
}

// siteTimestamps bundles nullable timestamp fields for Site.
type siteTimestamps struct {
	LastTested sql.NullString
	LastSync   sql.NullString
	Created    sql.NullString
	Updated    sql.NullString
}

// applySiteTimestamps parses and assigns timestamp fields on a Site.
func applySiteTimestamps(site *models.Site, ts siteTimestamps) {
	site.LastTestedAt = dbops.ParseNullTime(ts.LastTested)
	site.LastSyncAt = dbops.ParseNullTime(ts.LastSync)
	site.CreatedAt = dbops.ParseDateTime(ts.Created.String)
	site.UpdatedAt = dbops.ParseDateTime(ts.Updated.String)
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

	if availability.IsUnavailable() {
		return s.simulateUpload(zipPath, slug)
	}

	return s.performRealUpload(wpClient, zipPath, slug)
}

// simulateUpload logs a simulated upload when no uploader is available.
func (s *Service) simulateUpload(zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Warn("Riseup Asia Uploader not available; upload simulated", "slug", slug)
	fi, appErr := pathutil.StatFile(zipPath)
	if appErr == nil {
		s.log.Info("Plugin upload prepared (simulated)", "slug", slug, "size", fi.Info.Size())
	}

	return apperror.Ok(UploadOutcome{})
}

// performRealUpload uploads via the Riseup Asia Uploader.
func (s *Service) performRealUpload(wpClient *wordpress.Client, zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Info("Using Riseup Asia Uploader for upload", "slug", slug)

	result, err := s.callUploaderUpload(wpClient, zipPath, slug)
	if err != nil {
		return apperror.Fail[UploadOutcome](apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via uploader helper"))
	}

	s.logUploaderSuccess(slug, result)

	return apperror.Ok(buildUploadOutcome(slug, result))
}

// callUploaderUpload sends the upload request to the uploader.
func (s *Service) callUploaderUpload(wpClient *wordpress.Client, zipPath, slug string) (*wordpress.UploaderUploadResult, *apperror.AppError) {
	uploadInput := wordpress.UploadInput{
		ZipPath:      zipPath,
		Slug:         slug,
		IsActivate:   true,
		UploadSource: uploadsource.RestAPI,
	}

	return wpClient.UploadPluginViaUploader(uploadInput)
}

// logUploaderSuccess logs the successful upload result.
func (s *Service) logUploaderSuccess(slug string, result *wordpress.UploaderUploadResult) {
	s.log.Info("Plugin uploaded via Riseup Asia Uploader",
		"slug", slug,
		"success", result.Success,
		"message", result.Message,
		"activated", result.Activated,
	)
}

// buildUploadOutcome constructs an UploadOutcome from the uploader result.
func buildUploadOutcome(slug string, result *wordpress.UploaderUploadResult) UploadOutcome {
	return UploadOutcome{
		IsPerformed:  true,
		UploadResult: buildOnboardResult(slug, result),
		IsActivated:  result.Activated,
	}
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
	_, err = io.Copy(h, file)
	if err != nil {
		return "", err
	}
	return fmt.Sprintf("%x", h.Sum(nil)), nil
}
