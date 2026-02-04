package wordpress

// =============================================================================
// REST API NAMESPACES
// =============================================================================

const (
	// RiseUpUploaderNamespace is the REST API namespace for the Rise Up Uploader plugin.
	RiseUpUploaderNamespace = "riseup-uploader/v1"

	// OnboardNamespace is the legacy REST API namespace for the Onboard plugin.
	OnboardNamespace = "onboard-plugin/v1"

	// PluginUploaderNamespace is deprecated, use RiseUpUploaderNamespace.
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

	// EndpointPlugins lists all installed plugins.
	EndpointPlugins = "/plugins"

	// EndpointPluginInfo gets info for a specific plugin (format: /plugins/%s).
	EndpointPluginInfo = "/plugins/%s"

	// EndpointEnable activates a plugin (format: /plugins/%s/enable).
	EndpointEnable = "/plugins/%s/enable"

	// EndpointDisable deactivates a plugin (format: /plugins/%s/disable).
	EndpointDisable = "/plugins/%s/disable"

	// EndpointDelete removes a plugin (format: /plugins/%s/delete).
	EndpointDelete = "/plugins/%s/delete"

	// EndpointFiles handles file operations (format: /plugins/%s/files).
	EndpointFiles = "/plugins/%s/files"

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
)

// =============================================================================
// ACTION TYPES (match PHP constants)
// =============================================================================

const (
	// ActionUpload represents a plugin upload action.
	ActionUpload = "upload"

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

	// ActionPostCreate represents a blog post creation action.
	ActionPostCreate = "post_create"

	// ActionPostUpdate represents a blog post update action.
	ActionPostUpdate = "post_update"

	// ActionCategoryCreate represents a category creation action.
	ActionCategoryCreate = "category_create"

	// ActionAuthFailed represents an authentication failure.
	ActionAuthFailed = "auth_failed"
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
