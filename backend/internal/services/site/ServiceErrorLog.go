package site

import (
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/constants/logfile"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// logToErrorFile writes error details to data/errors/error.log.txt
func (s *Service) logToErrorFile(ref *remoteActionRef, details *ExtractedErrorDetails) {
	if s.isDuplicateErrorLog(ref, details) {
		return
	}

	f, err := s.openErrorLogFile()
	if err != nil {
		return
	}
	defer f.Close()

	logEntry := s.buildErrorLogEntry(ref, details)
	f.WriteString(logEntry)
}

// isDuplicateErrorLog checks and registers the error hash to suppress duplicates.
func (s *Service) isDuplicateErrorLog(ref *remoteActionRef, details *ExtractedErrorDetails) bool {
	hashInput := fmt.Sprintf("%s|%d|%s|%s|%d|%s", ref.Action, ref.SiteID, ref.PluginSlug, details.Endpoint, details.StatusCode, details.ResponseBody)
	hashBytes := md5.Sum([]byte(hashInput))
	hashHex := hex.EncodeToString(hashBytes[:])

	s.errorLogHashesMu.Lock()
	defer s.errorLogHashesMu.Unlock()

	if _, isDuplicate := s.errorLogHashes[hashHex]; isDuplicate {
		s.log.Debug("Duplicate error log entry suppressed", "action", ref.Action, "siteId", ref.SiteID, "plugin", ref.PluginSlug, "hash", hashHex)

		return true
	}
	s.errorLogHashes[hashHex] = struct{}{}
	return false
}

// openErrorLogFile creates the errors directory and opens the log file for appending.
func (s *Service) openErrorLogFile() (*os.File, error) {
	logPaths, err := s.resolveErrorLogPaths()
	if err != nil {
		return nil, err
	}

	if err := os.MkdirAll(logPaths.Dir, 0755); err != nil {
		s.log.Error("Failed to create errors directory", "error", err)
		return nil, err
	}

	return s.openLogFileForAppend(logPaths.FilePath)
}

// errorLogPaths holds the resolved directory and file paths for the error log.
type errorLogPaths struct {
	Dir      string
	FilePath string
}

// resolveErrorLogPaths resolves the errors directory and log file paths.
func (s *Service) resolveErrorLogPaths() (*errorLogPaths, error) {
	errorsDir, err := pathutil.Join(filepath.Dir(s.db.Path()), "errors")
	if err != nil {
		s.log.Error("Failed to resolve errors directory path", "error", err)
		return nil, err
	}
	errorLogPath, err := pathutil.Join(errorsDir, logfile.ErrorLog)
	if err != nil {
		s.log.Error("Failed to resolve error log path", "error", err)
		return nil, err
	}
	return &errorLogPaths{Dir: errorsDir, FilePath: errorLogPath}, nil
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
func (s *Service) buildErrorLogEntry(ref *remoteActionRef, details *ExtractedErrorDetails) string {
	method, delegatedUrl := resolveMethodAndUrl(details, ref.Site.Url)
	pluginIdentifier := resolvePluginIdentifier(ref.PluginSlug, details)
	requestBody := resolveRequestBody(details, pluginIdentifier)

	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	entry := fmt.Sprintf("\n[%s] REMOTE PLUGIN %s FAILED\n", timestamp, strings.ToUpper(ref.Action))
	entry += fmt.Sprintf("  Site Request URL: %s\n  Site ID: %d\n  Site Name: %s\n  Site Base URL: %s\n", delegatedUrl, ref.SiteID, ref.Site.Name, ref.Site.Url)
	entry += fmt.Sprintf("  Plugin Identifier: %s\n  Requested Action: %s\n", pluginIdentifier, ref.Action)
	entry += fmt.Sprintf("  Delegated Request:\n    Method: %s\n    Endpoint: %s\n    Request Body:\n      %s\n", method, details.Endpoint, requestBody)
	entry += formatResponseSection(details)
	entry += formatGuardRailSection(guardRailInput{Action: ref.Action, SiteUrl: ref.Site.Url, Details: details, Method: method})
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
	isDelegatedUrlMissing := delegatedUrl == ""
	hasEndpoint          := details.Endpoint != ""

	if isDelegatedUrlMissing && hasEndpoint {
		delegatedUrl = wordpress.BuildWPJSONURL(siteUrl, details.Endpoint)
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
