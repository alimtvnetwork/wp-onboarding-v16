// Package site — typed detail structs for WebSocket broadcast payloads.
// These replace inline map[string]any literals at call sites, ensuring
// type safety per the Generic Enforce Pattern (GE-1).
package site

import "encoding/json"

// toJSON converts a typed struct to json.RawMessage for WS broadcast boundaries.
// This replaces the legacy toDetailsMap helper, ensuring typed structs are serialized
// directly to json.RawMessage without an intermediate map[string]any.
func toJSON(v any) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// --- Connection test detail structs ---

// ErrorDetail carries a single error message for broadcast context.
type ErrorDetail struct {
	Error string `json:"error"`
}

// ConnectionFailureDetails carries context for a failed connection attempt.
type ConnectionFailureDetails struct {
	URL      string `json:"url"`
	Username string `json:"username"`
}

// ConnectionSuccessDetails carries context for a successful connection.
type ConnectionSuccessDetails struct {
	WPVersion string `json:"wpVersion"`
}

// URLNormalizeDetails carries URL normalization context.
type URLNormalizeDetails struct {
	OriginalURL   string `json:"originalUrl"`
	NormalizedURL string `json:"normalizedUrl"`
}

// --- Bootstrap/uploader detail structs ---

// SiteContextDetails carries site identification context for broadcast logs.
type SiteContextDetails struct {
	SiteID   int64  `json:"siteId"`
	SiteName string `json:"siteName,omitempty"`
	SiteURL  string `json:"siteUrl,omitempty"`
}

// SiteIDDetail carries a minimal site ID reference.
type SiteIDDetail struct {
	SiteID int64 `json:"siteId"`
}

// BootstrapLogDetails carries bootstrap progress context with step info.
type BootstrapLogDetails struct {
	SiteID   int64           `json:"siteId"`
	SiteName string          `json:"siteName,omitempty"`
	Step     string          `json:"step,omitempty"`
	Status   string          `json:"status,omitempty"`
	Details  json.RawMessage `json:"details,omitempty"`
}

// ZipCreationDetails carries ZIP archive creation context.
type ZipCreationDetails struct {
	SiteID int64  `json:"siteId"`
	Path   string `json:"path"`
}

// UploaderDeployDetails carries uploader deployment result context.
type UploaderDeployDetails struct {
	SiteID    int64  `json:"siteId"`
	SiteName  string `json:"siteName"`
	Activated bool   `json:"activated"`
}

// --- Remote action detail structs ---

// PHPErrorDetail carries context for a single remote PHP error entry.
type PHPErrorDetail struct {
	PHPFile    string `json:"phpFile"`
	PHPLine    int    `json:"phpLine"`
	PHPLevel   string `json:"phpLevel"`
	PHPCreated string `json:"phpCreated"`
}

// PHPErrorCountDetail carries the count of remote PHP errors.
type PHPErrorCountDetail struct {
	PHPErrorCount int `json:"phpErrorCount"`
}

// StackTraceLogDetails carries PHP stacktrace metadata.
type StackTraceLogDetails struct {
	Lines     int  `json:"lines"`
	TotalSize int  `json:"totalSize"`
	Truncated bool `json:"truncated"`
}

// StackTraceContentDetails carries full PHP stacktrace content for session persistence.
type StackTraceContentDetails struct {
	Content   string `json:"content"`
	Lines     int    `json:"lines"`
	Truncated bool   `json:"truncated"`
}

// RemoteActionContext carries context for remote plugin action logs.
type RemoteActionContext struct {
	SiteID     int64  `json:"siteId"`
	SiteName   string `json:"siteName,omitempty"`
	SiteURL    string `json:"siteUrl,omitempty"`
	PluginSlug string `json:"pluginSlug,omitempty"`
}

// RemoteActionExecDetails carries target context for a remote plugin action execution step.
type RemoteActionExecDetails struct {
	TargetURL  string `json:"targetUrl"`
	PluginSlug string `json:"pluginSlug"`
}

// DurationDetail carries a duration in milliseconds.
type DurationDetail struct {
	DurationMs int64 `json:"durationMs"`
}

// RemoteActionStartedEvent is the BroadcastWithSession payload for "remote_plugin_action_started".
type RemoteActionStartedEvent struct {
	SiteID     int64  `json:"siteId"`
	SiteName   string `json:"siteName"`
	Action     string `json:"action"`
	PluginSlug string `json:"pluginSlug"`
}

// RemoteActionCompleteEvent is the BroadcastWithSession payload for "remote_plugin_action_complete".
type RemoteActionCompleteEvent struct {
	SiteID       int64                  `json:"siteId"`
	SiteName     string                 `json:"siteName,omitempty"`
	Action       string                 `json:"action"`
	PluginSlug   string                 `json:"pluginSlug"`
	Success      bool                   `json:"success"`
	Error        string                 `json:"error,omitempty"`
	ErrorDetails *ExtractedErrorDetails `json:"errorDetails,omitempty"`
	DurationMs   int64                  `json:"durationMs"`
}

// RemoteActionRequestBody is the typed body for session SaveRequest in remote actions.
type RemoteActionRequestBody struct {
	SiteID     int64  `json:"siteId"`
	PluginSlug string `json:"pluginSlug"`
	Action     string `json:"action"`
}

// RemoteActionSuccessBody is the typed body for session SaveResponse on success.
type RemoteActionSuccessBody struct {
	Success bool   `json:"success"`
	Action  string `json:"action"`
	Plugin  string `json:"plugin"`
}

// --- Extracted error details ---

// PHPStackFrame represents a single frame in a PHP stack trace.
type PHPStackFrame struct {
	Function string `json:"function"`
	File     string `json:"file"`
	Line     int    `json:"line"`
	Class    string `json:"class,omitempty"`
}

// PHPErrorEntry represents a PHP error entry from the remote WordPress site.
type PHPErrorEntry struct {
	ID               int             `json:"id"`
	Level            string          `json:"level"`
	Message          string          `json:"message"`
	File             string          `json:"file"`
	Line             int             `json:"line"`
	CreatedAt        string          `json:"createdAt"`
	StackTraceFrames json.RawMessage `json:"stackTraceFrames,omitempty"`
}

// ExtractedErrorDetails carries structured error context extracted from WordPress API errors.
// This replaces the legacy map[string]any return from extractErrorDetails.
type ExtractedErrorDetails struct {
	Error                      string          `json:"error"`
	Method                     string          `json:"method,omitempty"`
	Endpoint                   string          `json:"endpoint,omitempty"`
	URL                        string          `json:"url,omitempty"`
	StatusCode                 int             `json:"statusCode,omitempty"`
	RequestBody                string          `json:"requestBody,omitempty"`
	ResponseBody               string          `json:"responseBody,omitempty"`
	StackTrace                 string          `json:"stackTrace,omitempty"`
	PluginSlugIn               string          `json:"pluginSlugIn,omitempty"`
	PluginIDUsed               string          `json:"pluginIdUsed,omitempty"`
	ErrorMessage               string          `json:"errorMessage,omitempty"`
	DelegatedServiceErrorStack []string        `json:"delegatedServiceErrorStack,omitempty"`
	PHPBackendStack            json.RawMessage `json:"phpBackendStack,omitempty"`
	StackTraceFrames           []PHPStackFrame `json:"stackTraceFrames,omitempty"`
	ErrorFile                  string          `json:"errorFile,omitempty"`
	ErrorLine                  int             `json:"errorLine,omitempty"`
	// Enriched by fetchAndAttachRemotePHPErrors
	RemotePHPErrors          []PHPErrorEntry `json:"remotePHPErrors,omitempty"`
	RemotePHPErrorCount      int             `json:"remotePHPErrorCount,omitempty"`
	RemotePHPFlashUnseen     int             `json:"remotePHPFlashUnseen,omitempty"`
	RemotePHPStackTrace      string          `json:"remotePHPStackTrace,omitempty"`
	RemotePHPStackTraceLines int             `json:"remotePHPStackTraceLines,omitempty"`
}

// --- Typed structs for error response parsing (replaces map[string]any in extractErrorDetails) ---

// errorResponseEnvelope is the typed structure for parsing WordPress API error responses.
// Covers both the modern Errors envelope and the legacy error.details format.
type errorResponseEnvelope struct {
	Errors      errorEnvelopeErrors `json:"Errors"`
	ErrorLegacy errorLegacyBlock    `json:"error"`
}

// errorEnvelopeErrors holds the modern error envelope fields.
type errorEnvelopeErrors struct {
	BackendMessage             string          `json:"BackendMessage"`
	DelegatedServiceErrorStack []string        `json:"DelegatedServiceErrorStack"`
	Backend                    json.RawMessage `json:"Backend"`
}

// errorLegacyBlock holds the legacy "error" top-level object.
type errorLegacyBlock struct {
	Details errorLegacyDetails `json:"details"`
}

// errorLegacyDetails holds legacy error detail fields.
type errorLegacyDetails struct {
	StackTraceFrames []legacyStackFrame `json:"stackTraceFrames"`
	FileFull         string             `json:"fileFull"`
	Line             int                `json:"line"`
}

// legacyStackFrame is a single frame from the legacy PHP stack trace format.
type legacyStackFrame struct {
	Function string `json:"function"`
	File     string `json:"file"`
	Line     int    `json:"line"`
	Class    string `json:"class"`
}

// remoteActionLogContext holds typed fields extracted from log details JSON
// for name resolution in logRemoteAction. Replaces map[string]any parsing.
type remoteActionLogContext struct {
	SiteName   string `json:"siteName"`
	SiteURL    string `json:"siteUrl"`
	PluginSlug string `json:"pluginSlug"`
}
