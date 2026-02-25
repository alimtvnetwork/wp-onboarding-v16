// Package wordpress — typed progress detail structs for c.progress() calls.
// These replace the legacy ProgressDetails (map[string]any) with typed structs,
// ensuring type safety per the Generic Enforce Pattern (GE-1).
package wordpress

import "encoding/json"

// ProgressDetails is pre-marshaled JSON for progress callback payloads.
// Call sites MUST use toProgress() with a typed struct — never construct raw JSON.
type ProgressDetails = json.RawMessage

// toProgress marshals a typed struct into ProgressDetails (json.RawMessage).
func toProgress[T any](v T) ProgressDetails {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// --- Connection test progress structs (used in TestConnection) ---

// URLProgress carries a URL-only progress context.
type URLProgress struct {
	URL string `json:",omitempty"`
}

// URLErrorProgress carries a URL + error progress context.
type URLErrorProgress struct {
	URL   string `json:",omitempty"`
	Error string `json:",omitempty"`
}

// URLStatusProgress carries a URL + HTTP status code progress context.
type URLStatusProgress struct {
	URL    string `json:",omitempty"`
	Status int    `json:",omitempty"`
}

// SiteNameProgress carries site discovery progress context.
type SiteNameProgress struct {
	URL      string `json:",omitempty"`
	SiteName string `json:",omitempty"`
}

// AuthInitProgress carries authentication init progress context.
type AuthInitProgress struct {
	URL      string `json:",omitempty"`
	Username string `json:",omitempty"`
}

// AuthHintProgress carries authentication hint progress context.
type AuthHintProgress struct {
	URL  string `json:",omitempty"`
	Hint string `json:",omitempty"`
}

// AuthBodyProgress carries authentication response body progress context.
type AuthBodyProgress struct {
	URL  string `json:",omitempty"`
	Body string `json:",omitempty"`
}

// UserAuthProgress carries authenticated user progress context.
type UserAuthProgress struct {
	URL    string   `json:",omitempty"`
	UserID int      `json:",omitempty"`
	Roles  []string `json:",omitempty"`
}

// UserRolesProgress carries user roles check progress context.
type UserRolesProgress struct {
	URL       string   `json:",omitempty"`
	UserRoles []string `json:",omitempty"`
}

// WriteTestProgress carries write test progress context.
type WriteTestProgress struct {
	URL        string `json:",omitempty"`
	TestPostID int    `json:",omitempty"`
}

// --- Upload progress structs (used in UploadPluginViaUploader) ---

// UploadInitProgress carries upload initiation progress context.
type UploadInitProgress struct {
	ZipSize   int64  `json:",omitempty"`
	ZipPath   string `json:",omitempty"`
	Namespace string `json:",omitempty"`
	Endpoint  string `json:",omitempty"`
	URL       string `json:",omitempty"`
	Method    string `json:",omitempty"`
}

// UploadBodyProgress carries multipart body ready progress context.
type UploadBodyProgress struct {
	Slug     string `json:",omitempty"`
	Activate bool   `json:",omitempty"`
	ZipSize  int64  `json:",omitempty"`
	BodySize int    `json:",omitempty"`
}

// ResponseProgress carries HTTP response progress context.
type ResponseProgress struct {
	URL    string `json:",omitempty"`
	Status int    `json:",omitempty"`
	Body   string `json:",omitempty"`
}

// --- Legacy upload progress structs (used in UploadPluginZip) ---

// TokenProgress carries mutation token progress context.
type TokenProgress struct {
	TokenLength int `json:",omitempty"`
}

// ZipUploadProgress carries ZIP upload progress context.
type ZipUploadProgress struct {
	ZipSize  int64  `json:",omitempty"`
	ZipFile  string `json:",omitempty"`
	Endpoint string `json:",omitempty"`
}

// --- Sync progress structs (used in SyncPluginFilesViaUploader) ---

// SyncInitProgress carries sync initiation progress context.
type SyncInitProgress struct {
	Slug      string `json:",omitempty"`
	FileCount int    `json:",omitempty"`
	Namespace string `json:",omitempty"`
}
