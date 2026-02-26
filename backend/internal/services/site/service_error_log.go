package site

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// logToErrorFile writes error details to data/errors/error.log.txt
func (s *Service) logToErrorFile(action string, siteId int64, pluginSlug, siteName, siteUrl string, details *ExtractedErrorDetails) {
	if s.isDuplicateErrorLog(action, siteId, pluginSlug, details) {
		return
	}

	f, err := s.openErrorLogFile()
	if err != nil {
		return
	}
	defer f.Close()

	logEntry := s.buildErrorLogEntry(action, siteId, pluginSlug, siteName, siteUrl, details)
	f.WriteString(logEntry)
}

// isDuplicateErrorLog checks and registers the error hash to suppress duplicates.
func (s *Service) isDuplicateErrorLog(action string, siteId int64, pluginSlug string, details *ExtractedErrorDetails) bool {
	hashInput := fmt.Sprintf("%s|%d|%s|%s|%d|%s", action, siteId, pluginSlug, details.Endpoint, details.StatusCode, details.ResponseBody)
	hashBytes := md5.Sum([]byte(hashInput))
	hashHex := hex.EncodeToString(hashBytes[:])

	s.errorLogHashesMu.Lock()
	defer s.errorLogHashesMu.Unlock()

	if _, exists := s.errorLogHashes[hashHex]; exists {
		s.log.Debug("Duplicate error log entry suppressed", "action", action, "siteId", siteId, "plugin", pluginSlug, "hash", hashHex)
		return true
	}
	s.errorLogHashes[hashHex] = struct{}{}
	return false
}

// openErrorLogFile creates the errors directory and opens the log file for appending.
func (s *Service) openErrorLogFile() (*os.File, error) {
	errorsDir, errorLogPath, err := s.resolveErrorLogPaths()
	if err != nil {
		return nil, err
	}

	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		s.log.Error("Failed to create errors directory", "error", err)
		return nil, err
	}

	return s.openLogFileForAppend(errorLogPath)
}

// resolveErrorLogPaths resolves the errors directory and log file paths.
func (s *Service) resolveErrorLogPaths() (string, string, error) {
	errorsDir, err := pathutil.Join(filepath.Dir(s.db.Path()), "errors")
	if err != nil {
		s.log.Error("Failed to resolve errors directory path", "error", err)
		return "", "", err
	}
	errorLogPath, err := pathutil.Join(errorsDir, "error.log.txt")
	if err != nil {
		s.log.Error("Failed to resolve error log path", "error", err)
		return "", "", err
	}
	return errorsDir, errorLogPath, nil
}

// openLogFileForAppend opens the log file for appending.
func (s *Service) openLogFileForAppend(errorLogPath string) (*os.File, error) {
	f, err := os.OpenFile(errorLogPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		s.log.Error("Failed to open error log file", "error", err)
		return nil, err
	}
	return f, nil
}

// buildErrorLogEntry formats the complete error log entry string.
func (s *Service) buildErrorLogEntry(action string, siteId int64, pluginSlug, siteName, siteUrl string, details *ExtractedErrorDetails) string {
	method, delegatedUrl := resolveMethodAndUrl(details, siteUrl)
	pluginIdentifier := resolvePluginIdentifier(pluginSlug, details)
	requestBody := resolveRequestBody(details, pluginIdentifier)

	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	entry := fmt.Sprintf("\n[%s] REMOTE PLUGIN %s FAILED\n", timestamp, strings.ToUpper(action))
	entry += fmt.Sprintf("  Site Request URL: %s\n  Site ID: %d\n  Site Name: %s\n  Site Base URL: %s\n", delegatedUrl, siteId, siteName, siteUrl)
	entry += fmt.Sprintf("  Plugin Identifier: %s\n  Requested Action: %s\n", pluginIdentifier, action)
	entry += fmt.Sprintf("  Delegated Request:\n    Method: %s\n    Endpoint: %s\n    Request Body:\n      %s\n", method, details.Endpoint, requestBody)
	entry += formatResponseSection(details)
	entry += formatGuardRailSection(action, siteUrl, details, method)
	entry += formatStackTraceSection(details)
	entry += formatPhpErrorsSection(details)
	entry += "───────────────────────────────────────────────────────────────────────────────\n"
	return entry
}

// resolveMethodAndUrl derives the HTTP method and delegated URL from error details.
func resolveMethodAndUrl(details *ExtractedErrorDetails, siteUrl string) (string, string) {
	method := details.Method
	if method == "" {
		method = "POST"
	}
	delegatedUrl := details.Url
	if delegatedUrl == "" && details.Endpoint != "" {
		delegatedUrl = fmt.Sprintf("%s/wp-json%s", siteUrl, details.Endpoint)
	}
	return method, delegatedUrl
}

// resolvePluginIdentifier returns the best available plugin identifier.
func resolvePluginIdentifier(pluginSlug string, details *ExtractedErrorDetails) string {
	if details.PluginSlugIn != "" {
		return details.PluginSlugIn
	}
	return pluginSlug
}

// resolveRequestBody returns the request body or a default.
func resolveRequestBody(details *ExtractedErrorDetails, pluginIdentifier string) string {
	if details.RequestBody != "" {
		return details.RequestBody
	}
	return fmt.Sprintf(`{"plugin":"%s"}`, pluginIdentifier)
}

// formatResponseSection formats the delegated response section of the log entry.
func formatResponseSection(details *ExtractedErrorDetails) string {
	entry := fmt.Sprintf("  Delegated Response:\n    Status Code: %d\n", details.StatusCode)
	if len(details.ResponseBody) > 0 {
		displayBody := details.ResponseBody
		if len(displayBody) > 2000 {
			displayBody = displayBody[:2000] + "... (truncated)"
		}
		entry += fmt.Sprintf("    Response Body:\n      %s\n", displayBody)
	}
	entry += fmt.Sprintf("  Error Summary:\n    %s\n", details.Error)
	return entry
}

// formatGuardRailSection formats the WP Core mutation guard rail section.
func formatGuardRailSection(action, siteUrl string, details *ExtractedErrorDetails, method string) string {
	if !strings.Contains(details.Endpoint, "/wp/v2/plugins") || method == "GET" {
		return "    This request was correctly delegated through the Riseup Uploader endpoint.\n"
	}

	requiredEndpoint := resolveRequiredEndpoint(action, siteUrl)
	entry := "    WARNING: This request was sent to a WordPress Core endpoint instead of the Riseup Uploader.\n"
	entry += fmt.Sprintf("  Guard Rail:\n    Blocked Direct WP Core Mutation: true\n    Blocked Endpoint: %s\n    Required Delegation Endpoint: %s\n", details.Endpoint, requiredEndpoint)
	return entry
}

// resolveRequiredEndpoint maps an action to its required Riseup delegation endpoint.
func resolveRequiredEndpoint(action, siteUrl string) string {
	switch action {
	case "disable":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Disable.String())
	case "enable":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Enable.String())
	case "delete":
		return fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Delete.String())
	default:
		return fmt.Sprintf("%s/wp-json/%s/plugins/%s", siteUrl, wordpress.RiseupAsiaNamespace, action)
	}
}

// formatStackTraceSection formats PHP stack trace frames for the log entry.
func formatStackTraceSection(details *ExtractedErrorDetails) string {
	if len(details.StackTraceFrames) == 0 {
		return ""
	}

	entry := "  PHP Stack Trace Frames:\n"
	for i, frame := range details.StackTraceFrames {
		if frame.Class != "" {
			entry += fmt.Sprintf("    #%d %s::%s() at %s:%d\n", i, frame.Class, frame.Function, frame.File, frame.Line)
		} else {
			entry += fmt.Sprintf("    #%d %s() at %s:%d\n", i, frame.Function, frame.File, frame.Line)
		}
	}
	return entry
}

// formatPhpErrorsSection formats remote PHP error sessions for the log entry.
func formatPhpErrorsSection(details *ExtractedErrorDetails) string {
	if len(details.RemotePhpErrors) == 0 {
		return ""
	}

	entry := fmt.Sprintf("  Remote PHP Error Sessions (%d entries):\n", len(details.RemotePhpErrors))
	for i, phpErr := range details.RemotePhpErrors {
		entry += fmt.Sprintf("    [%d] [%s] %s\n        File: %s  Line: %d  At: %s\n", i+1, strings.ToUpper(phpErr.Level), phpErr.Message, phpErr.File, phpErr.Line, phpErr.CreatedAt)
	}
	return entry
}

// =============================================================================
// REMOTE PLUGIN FILE BROWSER
// =============================================================================

// RemotePluginFile represents a file in a remote plugin
type RemotePluginFile struct {
	Path       string    `json:"path"`       // external key (Riseup Asia Uploader API)
	Hash       string    `json:"hash"`       // external key
	Size       int64     `json:"size"`       // external key
	ModifiedAt time.Time `json:"modifiedAt,omitempty"` // external key
}

// RemotePluginFilesResult wraps the file list result
type RemotePluginFilesResult struct {
	PluginSlug string             `json:"pluginSlug"` // external key
	TotalFiles int                `json:"totalFiles"` // external key
	Files      []RemotePluginFile `json:"files"`      // external key
}

// GetRemotePluginFiles fetches the file list for a remote plugin via Riseup Asia Uploader
func (s *Service) GetRemotePluginFiles(ctx context.Context, siteId int64, pluginSlug string) (*RemotePluginFilesResult, error) {
	client, err := s.createClientForSite(ctx, siteId)
	if err != nil {
		return nil, err
	}

	files, err := client.GetPluginFilesViaRiseup(ctx, pluginSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote plugin files").
			WithSiteId(siteId).
			WithPluginSlug(pluginSlug)
	}

	return s.buildRemoteFilesResult(siteId, pluginSlug, files), nil
}

// buildRemoteFilesResult converts file info into the result struct.
func (s *Service) buildRemoteFilesResult(siteId int64, pluginSlug string, files []wordpress.RemoteFileInfo) *RemotePluginFilesResult {
	filesResult := &RemotePluginFilesResult{PluginSlug: pluginSlug, TotalFiles: len(files), Files: make([]RemotePluginFile, 0, len(files))}
	for _, f := range files {
		filesResult.Files = append(filesResult.Files, RemotePluginFile{Path: f.Path, Hash: f.Hash, Size: f.Size, ModifiedAt: f.ModifiedAt})
	}
	s.log.Debug("Remote plugin files fetched", "siteId", siteId, "pluginSlug", pluginSlug, "fileCount", len(filesResult.Files))
	return filesResult
}

// createClientForSite creates a WordPress client for the given site.
func (s *Service) createClientForSite(ctx context.Context, siteId int64) (*wordpress.Client, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	return s.wpClientFactory(site.Url, site.Username, string(password), nil), nil
}

// GetRemotePluginFileContent fetches the content of a specific file from a remote plugin
func (s *Service) GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, error) {
	client, err := s.createClientForSite(ctx, siteId)
	if err != nil {
		return "", err
	}

	content, err := client.GetPluginFileContent(ctx, pluginSlug, filePath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote file content").
			WithSiteId(siteId).
			WithPluginSlug(pluginSlug).
			WithFilePath(filePath)
	}

	s.log.Debug("Remote file content fetched", "siteId", siteId, "pluginSlug", pluginSlug, "filePath", filePath, "contentLen", len(content))
	return content, nil
}
