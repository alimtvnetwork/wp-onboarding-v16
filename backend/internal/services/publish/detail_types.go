// Package publish — typed detail structs for marshalDetails calls.
// These replace inline map[string]any literals at call sites,
// ensuring type safety per the Generic Enforce Pattern (GE-1).
package publish

import "encoding/json"

// toDetails marshals a typed struct into json.RawMessage for WS broadcast boundaries.
func toDetails[T any](v T) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// --- Stage context inner data structs ---

// UploadStageDetails carries upload stage completion context.
type UploadStageDetails struct {
	RemoteSlug string `json:",omitempty"`
	Activated  bool   `json:",omitempty"`
}

// ActivateSkipDetails carries context when activation is skipped.
type ActivateSkipDetails struct {
	RemoteSlug string `json:",omitempty"`
	Skipped    bool   `json:",omitempty"`
}

// ActivateRequestDetails carries activation request context.
type ActivateRequestDetails struct {
	Method     string `json:",omitempty"`
	RemoteSlug string `json:",omitempty"`
}

// ActivateSuccessDetails carries successful activation context.
type ActivateSuccessDetails struct {
	RemoteSlug string `json:",omitempty"`
	DurationMs int64  `json:",omitempty"`
}

// --- Broadcast log detail structs ---

// ConnectDetails carries WordPress connection context.
type ConnectDetails struct {
	SiteURL  string `json:",omitempty"`
	Username string `json:",omitempty"`
}

// BackupStageDetails carries backup stage context.
type BackupStageDetails struct {
	MappingID  int64  `json:",omitempty"`
	RemoteSlug string `json:",omitempty"`
}

// PackageDetails carries packaging stage context.
type PackageDetails struct {
	PluginPath      string   `json:",omitempty"`
	PluginName      string   `json:",omitempty"`
	Mode            string   `json:",omitempty"`
	ExcludePatterns []string `json:",omitempty"`
}

// SelectedFilesDetails carries selected file publish context.
type SelectedFilesDetails struct {
	SelectedFiles []string `json:",omitempty"`
}

// ZipCreatedDetails carries ZIP creation context.
type ZipCreatedDetails struct {
	ZipPath      string   `json:",omitempty"`
	ZipSize      int64    `json:",omitempty"`
	FileCount    int      `json:",omitempty"`
	ZipStructure []string `json:",omitempty"`
}

// CleanupDetails carries ZIP cleanup context.
type CleanupDetails struct {
	ZipPath      string `json:",omitempty"`
	Reason       string `json:",omitempty"`
	KeepZipFiles bool   `json:",omitempty"`
}

// CompletionDetails carries publish completion context.
type CompletionDetails struct {
	IsSuccess    bool  `json:",omitempty"`
	FilesUpdated int   `json:",omitempty"`
	DurationMs   int64 `json:",omitempty"`
}

// --- Upload/activate error detail structs ---

// UploadErrorInner carries upload error context.
type UploadErrorInner struct {
	RemoteSlug string `json:",omitempty"`
	Attempts   int    `json:",omitempty"`
	Status     int    `json:",omitempty"`
	Response   string `json:",omitempty"`
	Code       string `json:",omitempty"`
}

// UploadSuccessInner carries upload success context.
type UploadSuccessInner struct {
	RemoteSlug  string `json:",omitempty"`
	Activated   bool   `json:",omitempty"`
	DurationMs  int64  `json:",omitempty"`
	Attempts    int    `json:",omitempty"`
	Version     string `json:",omitempty"`
	Overwritten bool   `json:",omitempty"`
}

// ActivateErrorInner carries activation error context.
type ActivateErrorInner struct {
	RemoteSlug string                  `json:",omitempty"`
	DurationMs int64                   `json:",omitempty"`
	Request    *ActivateRequestInfo    `json:",omitempty"`
	Response   *ActivateResponseInfo   `json:",omitempty"`
}

// ActivateRequestInfo carries API request details.
type ActivateRequestInfo struct {
	Method   string `json:",omitempty"`
	Endpoint string `json:",omitempty"`
	URL      string `json:",omitempty"`
}

// ActivateResponseInfo carries API response details.
type ActivateResponseInfo struct {
	Status int    `json:",omitempty"`
	Body   string `json:",omitempty"`
}
