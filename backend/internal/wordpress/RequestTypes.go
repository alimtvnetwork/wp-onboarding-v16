// Package wordpress — typed request body structs for WordPress API calls.
// These replace inline map[string]string literals at call sites,
// ensuring type safety per the Generic Enforce Pattern (GE-1).
package wordpress

// PluginSlugRequest is the request body for plugin-slug-only API calls.
// Used by: activate, deactivate, delete, plugin-exists, files-list, export, sync-manifest.
type PluginSlugRequest struct {
	Plugin string `json:"plugin"` // external key (Riseup Asia Uploader API)
}

// PluginFileRequest is the request body for single-file read API calls.
type PluginFileRequest struct {
	Plugin string `json:"plugin"` // external key (Riseup Asia Uploader API)
	Path   string `json:"path"`   // external key
}

// PluginFileDeleteRequest is the request body for file deletion API calls.
type PluginFileDeleteRequest struct {
	Plugin string `json:"plugin"` // external key (Riseup Asia Uploader API)
	Path   string `json:"path"`   // external key
	Action string `json:"action"` // external key
}

// PluginFileReplaceRequest is the request body for file replacement API calls.
type PluginFileReplaceRequest struct {
	Plugin  string `json:"plugin"`  // external key (Riseup Asia Uploader API)
	Path    string `json:"path"`    // external key
	Content string `json:"content"` // external key (base64 encoded)
}

// SyncRequestBody is the request body for delta sync API calls.
type SyncRequestBody struct {
	Plugin string     `json:"plugin"` // external key (Riseup Asia Uploader API)
	Files  []SyncFile `json:"files"`  // external key
}
