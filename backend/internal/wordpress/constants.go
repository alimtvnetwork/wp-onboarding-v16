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

// NOTE: REST API Endpoints have been migrated to endpoint_type.go (EndpointType).
// NOTE: Action Types have been migrated to action_type.go (ActionType).

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

// =============================================================================
// UPLOAD SOURCE ENUM
// =============================================================================

const (
	// UploadSourceScript indicates upload via PowerShell/deployment script.
	UploadSourceScript = "upload_script"

	// UploadSourceRestAPI indicates upload via direct REST API call.
	UploadSourceRestAPI = "rest_api"

	// UploadSourceAdminUI indicates upload via WordPress admin panel.
	UploadSourceAdminUI = "admin_ui"

	// UploadSourceWPCLI indicates upload via WP-CLI command.
	UploadSourceWPCLI = "wp_cli"
)
