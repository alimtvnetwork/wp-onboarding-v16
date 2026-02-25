package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"

	action "wp-plugin-publish/internal/enums/action"
	ep "wp-plugin-publish/internal/enums/endpoint"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/pkg/apperror"
)

// =============================================================================
// EXPORT TYPES AND METHODS
// =============================================================================

// ExportPluginResult holds the response from the export-plugin endpoint.
type ExportPluginResult struct {
	Success   bool   `json:"success"`    // external key (Riseup Asia Uploader API)
	PluginZip string `json:"plugin_zip"` // external key (base64 encoded)
	Slug      string `json:"slug"`       // external key
	FileCount int    `json:"file_count"` // external key
	Size      int    `json:"size"`       // external key
}

// ExportPlugin fetches an arbitrary plugin as a base64-encoded ZIP from the remote site.
func (c *Client) ExportPlugin(slug string) (*ExportPluginResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	endpoint := "/" + namespace + ep.ExportPlugin.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "export-plugin request failed").
			WithSlug(slug)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "export plugin for rollback",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: slug,
		}
	}

	var result ExportPluginResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode export-plugin result")
	}

	return &result, nil
}

// ExportSelfResult represents the result of exporting the uploader plugin.
type ExportSelfResult struct {
	Success    bool   `json:"success"`    // external key (Riseup Asia Uploader API)
	PluginName string `json:"pluginName"` // external key
	Version    string `json:"version"`    // external key
	PluginSlug string `json:"pluginSlug"` // external key
	PluginZip  string `json:"pluginZip"`  // external key (base64 encoded)
	Checksum   string `json:"checksum"`   // external key
	FileCount  int    `json:"fileCount"`  // external key
}

// ExportSelfFromSite fetches the Riseup Asia Uploader plugin as a ZIP from a site.
func (c *Client) ExportSelfFromSite() (*ExportSelfResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	c.progress(action.ExportSelf.String(), stagestatus.Running.String(), "Exporting Riseup Asia Uploader plugin...", nil)

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ExportSelf)

	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "export-self request failed")
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "export self via Riseup Asia Uploader",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
		}
	}

	var result ExportSelfResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode export-self result")
	}

	c.progress(action.ExportSelf.String(), stagestatus.Completed.String(), fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount), nil)

	return &result, nil
}

// =============================================================================
// ERROR LOG TYPES AND METHODS
// =============================================================================

// RemoteLogFile represents a single log file returned by the error-logs endpoint.
type RemoteLogFile struct {
	Exists     bool   `json:"exists"`     // external key (Riseup Asia Uploader API)
	File       string `json:"file"`       // external key
	Path       string `json:"path"`       // external key
	Content    string `json:"content"`    // external key
	Lines      int    `json:"lines"`      // external key
	TotalLines int    `json:"totalLines"` // external key
	TotalSize  int64  `json:"totalSize"`  // external key
	Truncated  bool   `json:"truncated"`  // external key
}

// RemoteErrorLogsResult represents the /error-logs endpoint response.
type RemoteErrorLogsResult struct {
	Success          bool                 `json:"success"`                    // external key (Riseup Asia Uploader API)
	Version          string               `json:"version"`                    // external key
	Settings         ProgressDetails      `json:"settings"`                   // external key
	ErrorLog         *RemoteLogFile       `json:"errorLog,omitempty"`         // external key
	FullLog          *RemoteLogFile       `json:"fullLog,omitempty"`          // external key
	StackTraceLog    *RemoteLogFile       `json:"stacktraceLog,omitempty"`    // external key
	StackTraceFrames []PHPStackTraceFrame `json:"stackTraceFrames,omitempty"` // external key
}

// FetchRemoteErrorLogs retrieves the PHP error and log files from the WordPress plugin.
func (c *Client) FetchRemoteErrorLogs() (*RemoteErrorLogsResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ErrorLogs)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "fetch remote error logs")
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "fetch remote error logs",
			Method:       "GET",
			Endpoint:     endpoint,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBody), 4000),
			StackTrace:   captureStackTraceN(2, c.stackTraceDepth),
		}
	}

	var result RemoteErrorLogsResult
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode remote error logs response")
	}

	return &result, nil
}

// RemoteErrorSessionEntry represents a single structured error from the plugin's SQLite DB.
type RemoteErrorSessionEntry struct {
	ID               int                  `json:"id"`                        // external key (Riseup Asia Uploader API)
	Level            string               `json:"level"`                     // external key
	Message          string               `json:"message"`                   // external key
	File             string               `json:"file"`                      // external key
	FileBase         string               `json:"fileBase"`                  // external key
	Line             *int                 `json:"line"`                      // external key
	StackTrace       string               `json:"stackTrace,omitempty"`      // external key
	StackTraceFrames []PHPStackTraceFrame `json:"stackTraceFrames,omitempty"` // external key
	Context          json.RawMessage      `json:"context,omitempty"`         // external key
	CreatedAt        string               `json:"createdAt"`                 // external key
}

// RemoteFlashState represents the flash notification state from the plugin.
type RemoteFlashState struct {
	LastSeenID  int  `json:"last_seen_id"`  // external key (Riseup Asia Uploader API)
	HasUnseen   bool `json:"has_unseen"`    // external key
	UnseenCount int  `json:"unseen_count"`  // external key
}

// RemoteErrorSessionsResult represents the /error-sessions endpoint response.
type RemoteErrorSessionsResult struct {
	Success          bool                      `json:"success"`                    // external key (Riseup Asia Uploader API)
	Version          string                    `json:"version"`                    // external key
	Message          string                    `json:"message,omitempty"`          // external key
	Entries          []RemoteErrorSessionEntry `json:"entries"`                    // external key
	Total            int                       `json:"total"`                      // external key
	Limit            int                       `json:"limit"`                      // external key
	Offset           int                       `json:"offset"`                     // external key
	Flash            RemoteFlashState          `json:"flash"`                      // external key
	StackTraceFrames []PHPStackTraceFrame      `json:"stackTraceFrames,omitempty"` // external key
}

// FetchRemoteErrorSessions retrieves structured error entries from the WordPress plugin's
// error_sessions SQLite table.
func (c *Client) FetchRemoteErrorSessions(level string, search string, sinceID int, limit int, offset int) (*RemoteErrorSessionsResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	// Build query string
	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ErrorSessions)
	params := []string{}
	if level != "" {
		params = append(params, fmt.Sprintf("level=%s", level))
	}
	if search != "" {
		params = append(params, fmt.Sprintf("search=%s", search))
	}
	if sinceID > 0 {
		params = append(params, fmt.Sprintf("since_id=%d", sinceID))
	}
	if limit > 0 {
		params = append(params, fmt.Sprintf("limit=%d", limit))
	}
	if offset > 0 {
		params = append(params, fmt.Sprintf("offset=%d", offset))
	}
	if len(params) > 0 {
		endpoint += "?" + strings.Join(params, "&")
	}

	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "fetch remote error sessions")
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "fetch remote error sessions",
			Method:       "GET",
			Endpoint:     endpoint,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBody), 4000),
			StackTrace:   captureStackTraceN(2, c.stackTraceDepth),
		}
	}

	var result RemoteErrorSessionsResult
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode remote error sessions response")
	}

	return &result, nil
}
