// Package site — typed detail structs for WebSocket broadcast payloads.
// These replace inline map[string]any literals at call sites, ensuring
// type safety per the Generic Enforce Pattern (GE-1).
package site

import "encoding/json"

// toJson converts a typed struct to json.RawMessage for WS broadcast boundaries.
// This replaces the legacy toDetailsMap helper, ensuring typed structs are serialized
// directly to json.RawMessage without an intermediate map[string]any.
func toJson(v any) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// --- Connection test detail structs ---

// AppErrorDetail carries a single error message for broadcast context.
// The Error field stores the string representation from *apperror.AppError.
type AppErrorDetail struct {
	Error string
}

// ConnectionFailureDetails carries context for a failed connection attempt.
type ConnectionFailureDetails struct {
	Url      string
	Username string
}

// ConnectionSuccessDetails carries context for a successful connection.
type ConnectionSuccessDetails struct {
	WPVersion string
}

// UrlNormalizeDetails carries URL normalization context.
type UrlNormalizeDetails struct {
	OriginalUrl   string
	NormalizedUrl string
}

// --- Bootstrap/uploader detail structs ---

// SiteContextDetails carries site identification context for broadcast logs.
type SiteContextDetails struct {
	SiteId   int64
	SiteName string `json:",omitempty"`
	SiteUrl  string `json:",omitempty"`
}

// SiteIdDetail carries a minimal site ID reference.
type SiteIdDetail struct {
	SiteId int64
}

// BootstrapLogDetails carries bootstrap progress context with step info.
type BootstrapLogDetails struct {
	SiteId   int64
	SiteName string          `json:",omitempty"`
	Step     string          `json:",omitempty"`
	Status   string          `json:",omitempty"`
	Details  json.RawMessage `json:",omitempty"`
}

// ZipCreationDetails carries ZIP archive creation context.
type ZipCreationDetails struct {
	SiteId int64
	Path   string
}

// UploaderDeployDetails carries uploader deployment result context.
type UploaderDeployDetails struct {
	SiteId      int64
	SiteName    string
	IsActivated bool
}

// --- Remote action detail structs ---

// PhpErrorDetail carries context for a single remote PHP error entry.
type PhpErrorDetail struct {
	PhpFile    string
	PhpLine    int
	PhpLevel   string
	PhpCreated string
}

// PhpErrorCountDetail carries the count of remote PHP errors.
type PhpErrorCountDetail struct {
	PhpErrorCount int
}

// StackTraceLogDetails carries PHP stacktrace metadata.
type StackTraceLogDetails struct {
	Lines     int
	TotalSize int
	Truncated bool
}

// StackTraceContentDetails carries full PHP stacktrace content for session persistence.
type StackTraceContentDetails struct {
	Content   string
	Lines     int
	Truncated bool
}

// RemoteActionContext carries context for remote plugin action logs.
type RemoteActionContext struct {
	SiteId     int64
	SiteName   string `json:",omitempty"`
	SiteUrl    string `json:",omitempty"`
	PluginSlug string `json:",omitempty"`
}

// RemoteActionExecDetails carries target context for a remote plugin action execution step.
type RemoteActionExecDetails struct {
	TargetUrl  string
	PluginSlug string
}

// DurationDetail carries a duration in milliseconds.
type DurationDetail struct {
	DurationMs int64
}

// RemoteActionStartedEvent is the BroadcastWithSession payload for "remote_plugin_action_started".
type RemoteActionStartedEvent struct {
	SiteId     int64
	SiteName   string
	Action     string
	PluginSlug string
}

// RemoteActionCompleteEvent is the BroadcastWithSession payload for "remote_plugin_action_complete".
type RemoteActionCompleteEvent struct {
	SiteId       int64
	SiteName     string `json:",omitempty"`
	Action       string
	PluginSlug   string
	IsSuccess    bool
	Error        string                 `json:",omitempty"`
	ErrorDetails *ExtractedErrorDetails `json:",omitempty"`
	DurationMs   int64
}

// RemoteActionRequestBody is the typed body for session SaveRequest in remote actions.
type RemoteActionRequestBody struct {
	SiteId     int64
	PluginSlug string
	Action     string
}

// RemoteActionSuccessBody is the typed body for session SaveResponse on success.
type RemoteActionSuccessBody struct {
	IsSuccess bool
	Action    string
	Plugin    string
}

// --- Extracted error details ---

// PhpStackFrame represents a single frame in a PHP stack trace.
type PhpStackFrame struct {
	Function string `json:"function"` // external key
	File     string `json:"file"`     // external key
	Line     int    `json:"line"`     // external key
	Class    string `json:"class,omitempty"` // external key
}

// PhpErrorEntry represents a PHP error entry from the remote WordPress site.
type PhpErrorEntry struct {
	Id               int             `json:"id"`                          // external key
	Level            string          `json:"level"`                       // external key
	Message          string          `json:"message"`                     // external key
	File             string          `json:"file"`                        // external key
	Line             int             `json:"line"`                        // external key
	CreatedAt        string          `json:"createdAt"`                   // external key
	StackTraceFrames json.RawMessage `json:"stackTraceFrames,omitempty"` // external key
}

// ExtractedErrorDetails carries structured error context extracted from WordPress API errors.
// This replaces the legacy map[string]any return from extractErrorDetails.
type ExtractedErrorDetails struct {
	Error                      string
	Method                     string `json:",omitempty"`
	Endpoint                   string `json:",omitempty"`
	Url                        string `json:",omitempty"`
	StatusCode                 int    `json:",omitempty"`
	RequestBody                string `json:",omitempty"`
	ResponseBody               string `json:",omitempty"`
	StackTrace                 string `json:",omitempty"`
	PluginSlugIn               string `json:",omitempty"`
	PluginIdUsed               string `json:",omitempty"`
	ErrorMessage               string `json:",omitempty"`
	DelegatedServiceErrorStack []string        `json:",omitempty"`
	PhpBackendStack            json.RawMessage `json:",omitempty"`
	StackTraceFrames           []PhpStackFrame `json:",omitempty"`
	ErrorFile                  string          `json:",omitempty"`
	ErrorLine                  int             `json:",omitempty"`
	// Enriched by fetchAndAttachRemotePhpErrors
	RemotePhpErrors          []PhpErrorEntry `json:",omitempty"`
	RemotePhpErrorCount      int             `json:",omitempty"`
	RemotePhpFlashUnseen     int             `json:",omitempty"`
	RemotePhpStackTrace      string          `json:",omitempty"`
	RemotePhpStackTraceLines int             `json:",omitempty"`
}

// --- Typed structs for error response parsing (replaces map[string]any in extractErrorDetails) ---

// errorResponseEnvelope is the typed structure for parsing WordPress API error responses.
// Covers both the modern Errors envelope and the legacy error.details format.
type errorResponseEnvelope struct {
	Errors      errorEnvelopeErrors `json:"Errors"`      // external key (WordPress envelope)
	ErrorLegacy errorLegacyBlock    `json:"error"`        // external key (legacy format)
}

// errorEnvelopeErrors holds the modern error envelope fields.
type errorEnvelopeErrors struct {
	BackendMessage             string          `json:"BackendMessage"`             // external key
	DelegatedServiceErrorStack []string        `json:"DelegatedServiceErrorStack"` // external key
	Backend                    json.RawMessage `json:"Backend"`                    // external key
}

// errorLegacyBlock holds the legacy "error" top-level object.
type errorLegacyBlock struct {
	Details errorLegacyDetails `json:"details"` // external key
}

// errorLegacyDetails holds legacy error detail fields.
type errorLegacyDetails struct {
	StackTraceFrames []legacyStackFrame `json:"stackTraceFrames"` // external key
	FileFull         string             `json:"fileFull"`         // external key
	Line             int                `json:"line"`             // external key
}

// legacyStackFrame is a single frame from the legacy PHP stack trace format.
type legacyStackFrame struct {
	Function string `json:"function"` // external key
	File     string `json:"file"`     // external key
	Line     int    `json:"line"`     // external key
	Class    string `json:"class"`    // external key
}

// remoteActionLogContext holds typed fields extracted from log details JSON
// for name resolution in logRemoteAction. Replaces map[string]any parsing.
type remoteActionLogContext struct {
	SiteName   string
	SiteUrl    string
	PluginSlug string
}
