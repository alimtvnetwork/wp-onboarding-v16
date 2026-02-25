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
	hashInput := fmt.Sprintf("%s|%d|%s|%s|%d|%s", action, siteId, pluginSlug, details.Endpoint, details.StatusCode, details.ResponseBody)
	hashBytes := md5.Sum([]byte(hashInput))
	hashHex := hex.EncodeToString(hashBytes[:])

	s.errorLogHashesMu.Lock()
	if _, exists := s.errorLogHashes[hashHex]; exists {
		s.errorLogHashesMu.Unlock()
		s.log.Debug("Duplicate error log entry suppressed", "action", action, "siteId", siteId, "plugin", pluginSlug, "hash", hashHex)
		return
	}
	s.errorLogHashes[hashHex] = struct{}{}
	s.errorLogHashesMu.Unlock()

	errorsDir, err := pathutil.Join(filepath.Dir(s.db.Path()), "errors")
	if err != nil {
		s.log.Error("Failed to resolve errors directory path", "error", err)
		return
	}
	errorLogPath, err := pathutil.Join(errorsDir, "error.log.txt")
	if err != nil {
		s.log.Error("Failed to resolve error log path", "error", err)
		return
	}

	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		s.log.Error("Failed to create errors directory", "error", err)
		return
	}

	f, err := os.OpenFile(errorLogPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		s.log.Error("Failed to open error log file", "error", err)
		return
	}
	defer f.Close()

	method := details.Method
	if method == "" {
		method = "POST"
	}
	delegatedUrl := details.Url
	if delegatedUrl == "" && details.Endpoint != "" {
		delegatedUrl = fmt.Sprintf("%s/wp-json%s", siteUrl, details.Endpoint)
	}

	isWPCoreMutation := false
	blockedEndpoint := ""
	requiredEndpoint := ""
	if strings.Contains(details.Endpoint, "/wp/v2/plugins") && method != "GET" {
		isWPCoreMutation = true
		blockedEndpoint = details.Endpoint
		switch action {
		case "disable":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Disable.String())
		case "enable":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Enable.String())
		case "delete":
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s%s", siteUrl, wordpress.RiseupAsiaNamespace, ep.Delete.String())
		default:
			requiredEndpoint = fmt.Sprintf("%s/wp-json/%s/plugins/%s", siteUrl, wordpress.RiseupAsiaNamespace, action)
		}
	}

	pluginIdentifier := pluginSlug
	if details.PluginSlugIn != "" {
		pluginIdentifier = details.PluginSlugIn
	}
	requestBody := details.RequestBody
	if requestBody == "" {
		requestBody = fmt.Sprintf(`{"plugin":"%s"}`, pluginIdentifier)
	}

	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	logEntry := fmt.Sprintf("\n[%s] REMOTE PLUGIN %s FAILED\n", timestamp, strings.ToUpper(action))
	logEntry += fmt.Sprintf("  Site Request URL: %s\n  Site ID: %d\n  Site Name: %s\n  Site Base URL: %s\n", delegatedUrl, siteId, siteName, siteUrl)
	logEntry += fmt.Sprintf("  Plugin Identifier: %s\n  Requested Action: %s\n", pluginIdentifier, action)
	logEntry += fmt.Sprintf("  Delegated Request:\n    Method: %s\n    Endpoint: %s\n    Request Body:\n      %s\n", method, details.Endpoint, requestBody)
	logEntry += fmt.Sprintf("  Delegated Response:\n    Status Code: %d\n", details.StatusCode)

	if len(details.ResponseBody) > 0 {
		displayBody := details.ResponseBody
		if len(displayBody) > 2000 {
			displayBody = displayBody[:2000] + "... (truncated)"
		}
		logEntry += fmt.Sprintf("    Response Body:\n      %s\n", displayBody)
	}

	logEntry += fmt.Sprintf("  Error Summary:\n    %s\n", details.Error)
	if isWPCoreMutation {
		logEntry += "    WARNING: This request was sent to a WordPress Core endpoint instead of the Riseup Uploader.\n"
		logEntry += fmt.Sprintf("  Guard Rail:\n    Blocked Direct WP Core Mutation: true\n    Blocked Endpoint: %s\n    Required Delegation Endpoint: %s\n", blockedEndpoint, requiredEndpoint)
	} else {
		logEntry += "    This request was correctly delegated through the Riseup Uploader endpoint.\n"
	}

	if len(details.StackTraceFrames) > 0 {
		logEntry += "  PHP Stack Trace Frames:\n"
		for i, frame := range details.StackTraceFrames {
			if frame.Class != "" {
				logEntry += fmt.Sprintf("    #%d %s::%s() at %s:%d\n", i, frame.Class, frame.Function, frame.File, frame.Line)
			} else {
				logEntry += fmt.Sprintf("    #%d %s() at %s:%d\n", i, frame.Function, frame.File, frame.Line)
			}
		}
	}

	if len(details.RemotePhpErrors) > 0 {
		logEntry += fmt.Sprintf("  Remote PHP Error Sessions (%d entries):\n", len(details.RemotePhpErrors))
		for i, phpErr := range details.RemotePhpErrors {
			logEntry += fmt.Sprintf("    [%d] [%s] %s\n        File: %s  Line: %d  At: %s\n", i+1, strings.ToUpper(phpErr.Level), phpErr.Message, phpErr.File, phpErr.Line, phpErr.CreatedAt)
		}
	}

	logEntry += "───────────────────────────────────────────────────────────────────────────────\n"
	f.WriteString(logEntry)
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
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, result.AppError()
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)
	files, err := client.GetPluginFilesViaRiseup(ctx, pluginSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote plugin files").WithSiteId(siteId).WithPluginSlug(pluginSlug)
	}

	filesResult := &RemotePluginFilesResult{PluginSlug: pluginSlug, TotalFiles: len(files), Files: make([]RemotePluginFile, 0, len(files))}
	for _, f := range files {
		filesResult.Files = append(filesResult.Files, RemotePluginFile{Path: f.Path, Hash: f.Hash, Size: f.Size, ModifiedAt: f.ModifiedAt})
	}

	s.log.Debug("Remote plugin files fetched", "siteId", siteId, "pluginSlug", pluginSlug, "fileCount", len(filesResult.Files))
	return filesResult, nil
}

// GetRemotePluginFileContent fetches the content of a specific file from a remote plugin
func (s *Service) GetRemotePluginFileContent(ctx context.Context, siteId int64, pluginSlug, filePath string) (string, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return "", result.AppError()
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)
	content, err := client.GetPluginFileContent(ctx, pluginSlug, filePath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "failed to fetch remote file content").WithSiteId(siteId).WithPluginSlug(pluginSlug).WithFilePath(filePath)
	}

	s.log.Debug("Remote file content fetched", "siteId", siteId, "pluginSlug", pluginSlug, "filePath", filePath, "contentLen", len(content))
	return content, nil
}
