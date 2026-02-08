package wordpress

// =============================================================================
// REST API NAMESPACES
// =============================================================================

const (
	// RiseupAsiaNamespace is the REST API namespace for the Riseup Asia Uploader plugin.
	RiseupAsiaNamespace = "riseup-asia-uploader/v1"

	// RiseUpUploaderNamespace is the legacy namespace (kept for backward compatibility).
	RiseUpUploaderNamespace = "riseup-uploader/v1"

	// OnboardNamespace is the legacy REST API namespace for the Onboard plugin.
	OnboardNamespace = "onboard-plugin/v1"

	// PluginUploaderNamespace is deprecated, use RiseupAsiaNamespace.
	// Kept for backward compatibility.
	PluginUploaderNamespace = "plugin-uploader/v1"
)

// =============================================================================
// REST API ENDPOINTS
// =============================================================================

const (
	// EndpointStatus checks plugin availability and version.
	EndpointStatus = "/status"

	// EndpointUpload handles Base64 ZIP plugin uploads.
	EndpointUpload = "/upload"

	// EndpointUploadActive uploads and activates a plugin in one call.
	EndpointUploadActive = "/upload-active"

	// EndpointPlugins lists all installed plugins.
	EndpointPlugins = "/plugins"

	// EndpointPluginInfo gets info for a specific plugin (fixed URL, slug in JSON body).
	EndpointPluginInfo = "/plugins/info"

	// EndpointEnable activates a plugin (fixed URL, slug in JSON body).
	EndpointEnable = "/plugins/enable"

	// EndpointDisable deactivates a plugin (fixed URL, slug in JSON body).
	EndpointDisable = "/plugins/disable"

	// EndpointDelete removes a plugin (fixed URL, slug in JSON body).
	EndpointDelete = "/plugins/delete"

	// EndpointFiles handles file listing (fixed URL, slug in JSON body).
	EndpointFiles = "/plugins/files"

	// EndpointFile handles single file content retrieval (fixed URL, slug+path in JSON body).
	EndpointFile = "/plugins/file"

	// EndpointSync handles delta file sync (fixed URL, slug in JSON body).
	EndpointSync = "/plugins/sync"

	// EndpointLogs queries transaction logs.
	EndpointLogs = "/logs"

	// EndpointLogsStats gets log statistics.
	EndpointLogsStats = "/logs/stats"

	// EndpointPosts handles blog post operations.
	EndpointPosts = "/posts"

	// EndpointPostsById handles single post operations (format: /posts/%d).
	EndpointPostsById = "/posts/%d"

	// EndpointCategories handles category operations.
	EndpointCategories = "/categories"

	// EndpointMedia handles media library uploads.
	EndpointMedia = "/media"

	// EndpointExportSelf exports the Rise Up Asia plugin as a ZIP.
	EndpointExportSelf = "/export-self"

	// EndpointExportPlugin exports any plugin as a base64-encoded ZIP (fixed URL, slug in JSON body).
	EndpointExportPlugin = "/plugins/export"

	// EndpointSyncManifest fetches the cached file manifest for sync comparison (fixed URL, slug in JSON body).
	EndpointSyncManifest = "/plugins/sync-manifest"

	// EndpointErrorLogs retrieves PHP error and log files as JSON.
	EndpointErrorLogs = "/error-logs"
)

// =============================================================================
// SNAPSHOT ENDPOINTS (fixed URLs, IDs in JSON body)
// =============================================================================

const (
	// EndpointSnapshotsList lists all snapshots.
	EndpointSnapshotsList = "/snapshots/list"

	// EndpointSnapshotsSchedule creates/schedules a snapshot.
	EndpointSnapshotsSchedule = "/snapshots/schedule"

	// EndpointSnapshotsInfo gets details for a specific snapshot (ID in JSON body).
	EndpointSnapshotsInfo = "/snapshots/info"

	// EndpointSnapshotsDelete deletes a snapshot (ID in JSON body).
	EndpointSnapshotsDelete = "/snapshots/delete"

	// EndpointSnapshotsRestore restores a snapshot (ID in JSON body).
	EndpointSnapshotsRestore = "/snapshots/restore"

	// EndpointSnapshotsExport exports a snapshot as ZIP (ID in JSON body).
	EndpointSnapshotsExport = "/snapshots/export"

	// EndpointSnapshotsSettings manages snapshot settings.
	EndpointSnapshotsSettings = "/snapshots/settings"

	// EndpointSnapshotsProviders lists available snapshot providers.
	EndpointSnapshotsProviders = "/snapshots/providers"

	// EndpointSnapshotsTables lists available database tables.
	EndpointSnapshotsTables = "/snapshots/tables"

	// EndpointSnapshotsFullBackup triggers end-to-end full backup orchestration.
	EndpointSnapshotsFullBackup = "/snapshots/full-backup"

	// EndpointSnapshotsIncremental triggers an incremental backup against the latest master.
	EndpointSnapshotsIncremental = "/snapshots/incremental"

	// EndpointSnapshotsImport imports a snapshot from an uploaded ZIP file.
	EndpointSnapshotsImport = "/snapshots/import"

	// EndpointSnapshotsCleanup triggers cleanup of old/orphan/stuck snapshots.
	EndpointSnapshotsCleanup = "/snapshots/cleanup"
)
// =============================================================================
// ACTION TYPES (match PHP constants)
// =============================================================================

const (
	// ActionUpload represents a plugin upload action.
	ActionUpload = "upload"

	// ActionUploadActive represents upload + activate action.
	ActionUploadActive = "upload_active"

	// ActionEnable represents a plugin activation action.
	ActionEnable = "enable"

	// ActionDisable represents a plugin deactivation action.
	ActionDisable = "disable"

	// ActionDelete represents a plugin deletion action.
	ActionDelete = "delete"

	// ActionFileReplace represents a single file replacement action.
	ActionFileReplace = "file_replace"

	// ActionFileDelete represents a single file deletion action.
	ActionFileDelete = "file_delete"

	// ActionSync represents a delta file sync action.
	ActionSync = "sync"

	// ActionPostCreate represents a blog post creation action.
	ActionPostCreate = "post_create"

	// ActionPostUpdate represents a blog post update action.
	ActionPostUpdate = "post_update"

	// ActionCategoryCreate represents a category creation action.
	ActionCategoryCreate = "category_create"

	// ActionMediaUpload represents a media library upload action.
	ActionMediaUpload = "media_upload"

	// ActionAuthFailed represents an authentication failure.
	ActionAuthFailed = "auth_failed"

	// ActionExportSelf represents exporting the plugin itself.
	ActionExportSelf = "export_self"

	// ActionExportPlugin represents exporting an arbitrary plugin as ZIP.
	ActionExportPlugin = "export_plugin"
)

// =============================================================================
// STATUS VALUES
// =============================================================================

const (
	// StatusSuccess indicates the operation succeeded.
	StatusSuccess = "success"

	// StatusFailed indicates the operation failed.
	StatusFailed = "failed"
)

// =============================================================================
// POST STATUS VALUES
// =============================================================================

const (
	// PostStatusPublish represents a published post.
	PostStatusPublish = "publish"

	// PostStatusDraft represents a draft post.
	PostStatusDraft = "draft"

	// PostStatusPending represents a pending review post.
	PostStatusPending = "pending"
)

// =============================================================================
// HTTP HEADERS
// =============================================================================

const (
	// HeaderAuthorization is the HTTP Authorization header.
	HeaderAuthorization = "Authorization"

	// HeaderContentType is the HTTP Content-Type header.
	HeaderContentType = "Content-Type"

	// HeaderUserAgent is the HTTP User-Agent header.
	HeaderUserAgent = "User-Agent"

	// HeaderSourceMachine is a custom header identifying the source machine (hostname).
	// This enables audit trails on remote WordPress sites to track which server triggered actions.
	HeaderSourceMachine = "X-Riseup-Source-Machine"

	// UserAgentValue is the default User-Agent for WordPress API requests.
	UserAgentValue = "WP-Plugin-Publish/1.0"
)

// =============================================================================
// CONTENT TYPES
// =============================================================================

const (
	// ContentTypeJSON is the JSON content type.
	ContentTypeJSON = "application/json"

	// ContentTypeMultipart is the multipart form-data content type.
	ContentTypeMultipart = "multipart/form-data"

	// ContentTypeFormURLEncoded is the URL-encoded form content type.
	ContentTypeFormURLEncoded = "application/x-www-form-urlencoded"
)

// =============================================================================
// ERROR MESSAGES
// =============================================================================

const (
	// ErrMsgUnauthorized is returned when authentication fails.
	ErrMsgUnauthorized = "Authentication required"

	// ErrMsgForbidden is returned when the user lacks permission.
	ErrMsgForbidden = "Insufficient permissions"

	// ErrMsgPluginNotFound is returned when a plugin is not found.
	ErrMsgPluginNotFound = "Plugin not found"

	// ErrMsgUploadFailed is returned when plugin upload fails.
	ErrMsgUploadFailed = "Upload failed"

	// ErrMsgActivationFailed is returned when plugin activation fails.
	ErrMsgActivationFailed = "Plugin activation failed"

	// ErrMsgInvalidRequest is returned for malformed requests.
	ErrMsgInvalidRequest = "Invalid request data"

	// ErrMsgFileIgnored is returned when a file is ignored by .uploadignore.
	ErrMsgFileIgnored = "File ignored by .uploadignore"
)

// =============================================================================
// DEFAULT VALUES
// =============================================================================

const (
	// DefaultLimit is the default pagination limit.
	DefaultLimit = 50

	// MaxLimit is the maximum pagination limit.
	MaxLimit = 500
)

// =============================================================================
// IGNORE FILE
// =============================================================================

const (
	// UploadIgnoreFilename is the name of the ignore file.
	UploadIgnoreFilename = ".uploadignore"
)

// =============================================================================
// WORDPRESS CORE API ENDPOINTS (not Riseup Asia plugin)
// =============================================================================

const (
	// WPCoreAPIRoot is the root path for WordPress REST API.
	WPCoreAPIRoot = "/wp-json"

	// WPCoreUsersMe is the endpoint for current user info.
	WPCoreUsersMe = "/wp/v2/users/me"

	// WPCorePlugins is the endpoint for WordPress core plugins API.
	WPCorePlugins = "/wp/v2/plugins"

	// WPCorePluginBySlug is the endpoint for a specific plugin (format: /wp/v2/plugins/%s).
	WPCorePluginBySlug = "/wp/v2/plugins/%s"

	// WPCorePosts is the endpoint for posts.
	WPCorePosts = "/wp/v2/posts"

	// WPCorePostByID is the endpoint for a specific post (format: /wp/v2/posts/%d).
	WPCorePostByID = "/wp/v2/posts/%d"
)

// =============================================================================
// PLUGIN STATUS VALUES
// =============================================================================

const (
	// PluginStatusActive is the active status for a plugin.
	PluginStatusActive = "active"

	// PluginStatusInactive is the inactive status for a plugin.
	PluginStatusInactive = "inactive"
)
