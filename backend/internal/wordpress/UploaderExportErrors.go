// Package wordpress — remote error log and error session fetching.
package wordpress

import (
	"encoding/json"
	"fmt"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/pkg/apperror"
)

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
	StackTraceFrames []PhpStackTraceFrame `json:"stackTraceFrames,omitempty"` // external key
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

	decodeResult := decodeApiResponse[RemoteErrorLogsResult](rawResult.Value(), "remote error logs")
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
	StackTraceFrames []PhpStackTraceFrame `json:"stackTraceFrames,omitempty"` // external key
	Context          json.RawMessage      `json:"context,omitempty"`         // external key
	CreatedAt        string               `json:"createdAt"`                 // external key
}

// RemoteFlashState represents the flash notification state from the plugin.
type RemoteFlashState struct {
	LastSeenId  int  `json:"last_seen_id"`  // external key (Riseup Asia Uploader API)
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
	StackTraceFrames []PhpStackTraceFrame      `json:"stackTraceFrames,omitempty"` // external key
}

// ErrorSessionsInput bundles parameters for FetchRemoteErrorSessions.
type ErrorSessionsInput struct {
	Level   string
	Search  string
	SinceId int
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
		SinceId:   input.SinceId,
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

	decodeResult := decodeApiResponse[RemoteErrorSessionsResult](rawResult.Value(), "remote error sessions")
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
	SinceId   int
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

	if p.SinceId > 0 {
		params = append(params, fmt.Sprintf("since_id=%d", p.SinceId))
	}

	if p.Limit > 0 {
		params = append(params, fmt.Sprintf("limit=%d", p.Limit))
	}

	if p.Offset > 0 {
		params = append(params, fmt.Sprintf("offset=%d", p.Offset))
	}

	return params
}
