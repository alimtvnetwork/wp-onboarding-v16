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
	publishstep "wp-plugin-publish/internal/enums/publish_step"
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

// cleanupZipInput bundles parameters for cleanupZip.
type cleanupZipInput struct {
	PluginId        int64
	SiteId          int64
	ZipPath         string
	IsPublishFailed bool
	IsKeepZipFiles  bool
}

// cleanupZip handles ZIP file cleanup after publish
func (s *Service) cleanupZip(input cleanupZipInput) {
	if input.ZipPath == "" {
		return
	}

	if input.IsPublishFailed {
		keepLog := DetailedLogInput{
			PluginId: input.PluginId,
			SiteId:   input.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  fmt.Sprintf("Keeping temp ZIP for debugging (publish failed): %s", input.ZipPath),
			Details:  toDetails(CleanupDetails{ZipPath: input.ZipPath, Reason: "publish_failed"}),
		}
		s.broadcastDetailedLog(keepLog)

		return
	}

	if input.IsKeepZipFiles {
		keepLog := DetailedLogInput{
			PluginId: input.PluginId,
			SiteId:   input.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  fmt.Sprintf("Keeping temp ZIP (user setting): %s", input.ZipPath),
			Details:  toDetails(CleanupDetails{ZipPath: input.ZipPath, IsKeepZipFiles: true}),
		}
		s.broadcastDetailedLog(keepLog)

		return
	}

	removeLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Debug,
		Step:     publishstep.Cleanup,
		Message:  fmt.Sprintf("Removing temp ZIP: %s", input.ZipPath),
		Details:  toDetails(CleanupDetails{IsKeepZipFiles: input.IsKeepZipFiles}),
	}
	s.broadcastDetailedLog(removeLog)
	os.Remove(input.ZipPath)
}

// logZipInput bundles parameters for logZipCreated.
type logZipInput struct {
	PluginId  int64
	SiteId    int64
	ZipPath   string
	FileCount int
}

// logZipCreated logs the ZIP file creation details
func (s *Service) logZipCreated(input logZipInput) {
	fi, statErr := pathutil.StatFile(input.ZipPath)
	if statErr != nil {
		return
	}

	zipEntries := s.getZipStructure(input.ZipPath)

	zipLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(input.ZipPath), fi.Info.Size()),
		Details: toDetails(ZipCreatedDetails{
			ZipPath:      input.ZipPath,
			ZipSize:      fi.Info.Size(),
			FileCount:    input.FileCount,
			ZipStructure: zipEntries,
		}),
	}
	s.broadcastDetailedLog(zipLog)
	s.logZipEntries(input.PluginId, input.SiteId, zipEntries)
}

// logZipEntries logs individual zip entries (up to 20).
func (s *Service) logZipEntries(pluginId, siteId int64, entries []string) {
	maxShow := 20
	if len(entries) < maxShow {
		maxShow = len(entries)
	}
	for i := 0; i < maxShow; i++ {
		entryLog := DetailedLogInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Level:    loglevel.Debug,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("  └─ %s", entries[i]),
		}
		s.broadcastDetailedLog(entryLog)
	}
	if len(entries) > 20 {
		moreLog := DetailedLogInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Level:    loglevel.Debug,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("  ... and %d more files", len(entries)-20),
		}
		s.broadcastDetailedLog(moreLog)
	}
}

// logUploadError logs a structured upload error
func (s *Service) logUploadError(pctx *publishContext, attempts int, appErr *apperror.AppError) {
	inner := buildUploadErrorInner(pctx.Mapping.RemoteSlug, attempts, appErr)

	errCtx := StageContext{
		What:    fmt.Sprintf("Upload ZIP to %s", pctx.SiteInfo.URL),
		Result:  fmt.Sprintf("FAILED: %s", appErr.Error()),
		Details: toDetails(inner),
	}
	errLog := pctx.stageLog(loglevel.Error, publishstep.Upload, errCtx)
	s.broadcastStageLog(errLog)
}

// buildUploadErrorInner extracts structured error info from an AppError.
func buildUploadErrorInner(slug string, attempts int, appErr *apperror.AppError) UploadErrorInner {
	inner := UploadErrorInner{
		RemoteSlug: slug,
		Attempts:   attempts,
		Code:       appErr.Code,
	}

	if cause := appErr.Unwrap(); cause != nil {
		if apiErr := wordpress.ExtractAPIError(cause); apiErr != nil {
			inner.Status = apiErr.StatusCode
			inner.Response = truncateString(apiErr.ResponseBody, 2000)
		}
	}

	return inner
}

// logUploadSuccessInput bundles parameters for logUploadSuccess.
type logUploadSuccessInput struct {
	ZipSize      int64
	StartTime    time.Time
	IsActivated  bool
	Attempts     int
	UploadResult *wordpress.OnboardUploadResult
}

// logUploadSuccess logs a structured upload success
func (s *Service) logUploadSuccess(pctx *publishContext, input logUploadSuccessInput) {
	resultMsg := "Plugin uploaded successfully"
	if input.IsActivated {
		resultMsg = "Plugin uploaded and activated"
	}

	inner := buildUploadSuccessInner(pctx.Mapping.RemoteSlug, input)
	successCtx := StageContext{
		What:    fmt.Sprintf("Upload ZIP (%s)", formatBytes(input.ZipSize)),
		Result:  resultMsg,
		Details: toDetails(inner),
	}
	successLog := pctx.stageLog(loglevel.Info, publishstep.Upload, successCtx)
	s.broadcastStageLog(successLog)
}

// buildUploadSuccessInner constructs success detail struct.
func buildUploadSuccessInner(slug string, input logUploadSuccessInput) UploadSuccessInner {
	inner := UploadSuccessInner{
		RemoteSlug: slug,
		Activated:  input.IsActivated,
		DurationMs: time.Since(input.StartTime).Milliseconds(),
		Attempts:   input.Attempts,
	}

	if input.UploadResult != nil {
		inner.Version = input.UploadResult.Version
		inner.Overwritten = input.UploadResult.Overwritten
	}

	return inner
}

// activationErrorInput bundles parameters for logActivationError.
type activationErrorInput struct {
	EndpointURL string
	StartTime   time.Time
	Err         *apperror.AppError
}

// logActivationError logs a structured activation error
func (s *Service) logActivationError(pctx *publishContext, input activationErrorInput) {
	inner := buildActivateErrorInner(pctx.Mapping.RemoteSlug, input.StartTime, input.Err)

	errCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after upload",
		Where:   input.EndpointURL,
		Result:  fmt.Sprintf("FAILED: %s", input.Err.Error()),
		Details: toDetails(inner),
	}
	errLog := pctx.stageLog(loglevel.Error, publishstep.Activate, errCtx)
	s.broadcastStageLog(errLog)
}

// buildActivateErrorInner constructs activation error details.
func buildActivateErrorInner(slug string, startTime time.Time, appErr *apperror.AppError) ActivateErrorInner {
	inner := ActivateErrorInner{
		RemoteSlug: slug,
		DurationMs: time.Since(startTime).Milliseconds(),
	}

	if cause := appErr.Unwrap(); cause != nil {
		if apiErr := wordpress.ExtractAPIError(cause); apiErr != nil {
			inner.Request = &ActivateRequestInfo{
				Method:   apiErr.Method,
				Endpoint: apiErr.Endpoint,
				URL:      apiErr.URL,
			}
			inner.Response = &ActivateResponseInfo{
				Status: apiErr.StatusCode,
				Body:   truncateString(apiErr.ResponseBody, 2000),
			}
		}
	}

	return inner
}
