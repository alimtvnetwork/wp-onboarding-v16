// Package wordpress provides typed action constants for transaction logging.
//
// ActionType replaces untyped string constants for action identifiers.
// Each constant matches the PHP ActionType enum values for cross-language consistency.
package wordpress

import "strings"

// ActionType is a typed string representing a transaction logging action.
type ActionType string

// =============================================================================
// CORE PLUGIN ACTIONS
// =============================================================================

const (
	// ActionUpload represents a plugin upload action.
	ActionUpload ActionType = "upload"

	// ActionUploadActive represents upload + activate action.
	ActionUploadActive ActionType = "upload_active"

	// ActionEnable represents a plugin activation action.
	ActionEnable ActionType = "enable"

	// ActionDisable represents a plugin deactivation action.
	ActionDisable ActionType = "disable"

	// ActionDelete represents a plugin deletion action.
	ActionDelete ActionType = "delete"

	// ActionFileReplace represents a single file replacement action.
	ActionFileReplace ActionType = "file_replace"

	// ActionFileDelete represents a single file deletion action.
	ActionFileDelete ActionType = "file_delete"

	// ActionSync represents a delta file sync action.
	ActionSync ActionType = "sync"
)

// =============================================================================
// POST/CONTENT ACTIONS
// =============================================================================

const (
	// ActionPostCreate represents a blog post creation action.
	ActionPostCreate ActionType = "post_create"

	// ActionPostUpdate represents a blog post update action.
	ActionPostUpdate ActionType = "post_update"

	// ActionCategoryCreate represents a category creation action.
	ActionCategoryCreate ActionType = "category_create"

	// ActionMediaUpload represents a media library upload action.
	ActionMediaUpload ActionType = "media_upload"
)

// =============================================================================
// AUTH ACTIONS
// =============================================================================

const (
	// ActionAuthFailed represents an authentication failure.
	ActionAuthFailed ActionType = "auth_failed"
)

// =============================================================================
// EXPORT ACTIONS
// =============================================================================

const (
	// ActionExportSelf represents exporting the plugin itself.
	ActionExportSelf ActionType = "export_self"

	// ActionExportPlugin represents exporting an arbitrary plugin as ZIP.
	ActionExportPlugin ActionType = "export_plugin"
)

// IsEqual checks if this action equals the given action.
func (a ActionType) IsEqual(other ActionType) bool {
	return a == other
}

// String returns the raw string value of the action.
func (a ActionType) String() string {
	return string(a)
}

// IsSnapshot checks if this is a snapshot-related action.
func (a ActionType) IsSnapshot() bool {
	return strings.HasPrefix(string(a), "snapshot_")
}

// IsAgent checks if this is an agent-related action.
func (a ActionType) IsAgent() bool {
	return strings.HasPrefix(string(a), "agent_")
}

// IsUpdate checks if this is an update-related action.
func (a ActionType) IsUpdate() bool {
	return strings.HasPrefix(string(a), "update_")
}

// IsLifecycle checks if this is a plugin lifecycle action (enable/disable/delete).
func (a ActionType) IsLifecycle() bool {
	return a.IsEqual(ActionEnable) || a.IsEqual(ActionDisable) || a.IsEqual(ActionDelete)
}
