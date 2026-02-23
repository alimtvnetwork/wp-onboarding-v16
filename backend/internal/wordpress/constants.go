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
// NOTE: Status Values have been migrated to status_type.go (StatusType).
// NOTE: Post Status Values have been migrated to post_status_type.go (PostStatusType).
// NOTE: HTTP Headers have been migrated to header_type.go (HeaderType).
// NOTE: Content Types have been migrated to content_type.go (ContentTypeValue).
// NOTE: Plugin Status Values have been migrated to plugin_status_type.go (PluginStatusType).
// NOTE: Upload Source Values have been migrated to upload_source_type.go (UploadSourceType).

// NOTE: Error Messages have been migrated to response_message_type.go (ResponseMessageType).
// NOTE: Response Keys have been migrated to response_key_type.go (ResponseKeyType).

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

	// WPCorePostById is the endpoint for a specific post (format: /wp/v2/posts/%d).
	WPCorePostById = "/wp/v2/posts/%d"
)
