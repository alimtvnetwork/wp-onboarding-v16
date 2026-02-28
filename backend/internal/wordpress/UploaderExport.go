package wordpress

import (
	"encoding/json"
	"fmt"
	"strings"

	action "wp-plugin-publish/internal/enums/actiontype"
	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
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
func (c *Client) ExportPlugin(slug string) apperror.Result[*ExportPluginResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*ExportPluginResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	endpoint := "/" + namespace + ep.ExportPlugin.String()
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: slug},
		Operation:  "export plugin for rollback",
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*ExportPluginResult](rawResult.AppError())
	}

	decodeResult := decodeAPIResponse[ExportPluginResult](rawResult.Value(), "export plugin")
	if decodeResult.HasError() {
		return apperror.Fail[*ExportPluginResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
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
func (c *Client) ExportSelfFromSite() apperror.Result[*ExportSelfResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*ExportSelfResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	c.reportExportSelfStart()

	result := c.callExportSelf(namespace)
	if result.HasError() {
		return result
	}

	c.reportExportSelfComplete(result.Value())

	return result
}

// reportExportSelfStart logs the export self start progress.
func (c *Client) reportExportSelfStart() {
	c.progress(ProgressEvent{
		Step: action.ExportSelf.String(), Status: stagestatus.Running.String(),
		Message: "Exporting Riseup Asia Uploader plugin...",
	})
}

// callExportSelf sends the export-self API call.
func (c *Client) callExportSelf(namespace string) apperror.Result[*ExportSelfResult] {
	endpoint := BuildNamespacedEndpoint(namespace, ep.ExportSelf)

	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "export self via Riseup Asia Uploader",
		ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*ExportSelfResult](rawResult.AppError())
	}

	decodeResult := decodeAPIResponse[ExportSelfResult](rawResult.Value(), "export self")
	if decodeResult.HasError() {
		return apperror.Fail[*ExportSelfResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
}

// reportExportSelfComplete logs the export self completion progress.
func (c *Client) reportExportSelfComplete(result *ExportSelfResult) {
	c.progress(ProgressEvent{
		Step: action.ExportSelf.String(), Status: stagestatus.Completed.String(),
		Message: fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount),
	})
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
func (c *Client) FetchRemoteErrorLogs() apperror.Result[*RemoteErrorLogsResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*RemoteErrorLogsResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	endpoint := BuildNamespacedEndpoint(namespace, ep.ErrorLogs)
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "fetch remote error logs",
		ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*RemoteErrorLogsResult](rawResult.AppError())
	}

	decodeResult := decodeAPIResponse[RemoteErrorLogsResult](rawResult.Value(), "remote error logs")
	if decodeResult.HasError() {
		return apperror.Fail[*RemoteErrorLogsResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
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

// ErrorSessionsInput bundles parameters for FetchRemoteErrorSessions.
type ErrorSessionsInput struct {
	Level   string
	Search  string
	SinceID int
	Limit   int
	Offset  int
}

// FetchRemoteErrorSessions retrieves structured error entries from the WordPress plugin's
// error_sessions SQLite table.
func (c *Client) FetchRemoteErrorSessions(input ErrorSessionsInput) apperror.Result[*RemoteErrorSessionsResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*RemoteErrorSessionsResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	endpoint := buildErrorSessionsEndpoint(errorSessionsParams{
		Namespace: namespace,
		Level:     input.Level,
		Search:    input.Search,
		SinceID:   input.SinceID,
		Limit:     input.Limit,
		Offset:    input.Offset,
	})
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "fetch remote error sessions",
		ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*RemoteErrorSessionsResult](rawResult.AppError())
	}

	decodeResult := decodeAPIResponse[RemoteErrorSessionsResult](rawResult.Value(), "remote error sessions")
	if decodeResult.HasError() {
		return apperror.Fail[*RemoteErrorSessionsResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
}

// errorSessionsParams bundles query parameters for error sessions endpoint.
type errorSessionsParams struct {
	Namespace string
	Level     string
	Search    string
	SinceID   int
	Limit     int
	Offset    int
}

// buildErrorSessionsEndpoint constructs the endpoint URL with query parameters.
func buildErrorSessionsEndpoint(p errorSessionsParams) string {
	endpoint := BuildNamespacedEndpoint(p.Namespace, ep.ErrorSessions)
	params := collectErrorSessionParams(p)

	if len(params) > 0 {
		endpoint += "?" + strings.Join(params, "&")
	}

	return endpoint
}

// collectErrorSessionParams builds the query parameter list for error sessions.
func collectErrorSessionParams(p errorSessionsParams) []string {
	var params []string

	if p.Level != "" {
		params = append(params, fmt.Sprintf("level=%s", p.Level))
	}

	if p.Search != "" {
		params = append(params, fmt.Sprintf("search=%s", p.Search))
	}

	if p.SinceID > 0 {
		params = append(params, fmt.Sprintf("since_id=%d", p.SinceID))
	}

	if p.Limit > 0 {
		params = append(params, fmt.Sprintf("limit=%d", p.Limit))
	}

	if p.Offset > 0 {
		params = append(params, fmt.Sprintf("offset=%d", p.Offset))
	}

	return params
}
