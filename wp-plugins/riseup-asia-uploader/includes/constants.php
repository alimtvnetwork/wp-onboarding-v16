<?php
/**
 * Riseup Asia Uploader - Plugin Constants
 *
 * All string constants centralized to avoid magic strings.
 * These can be overridden by defining them before this file is loaded.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// PLUGIN IDENTITY
// =============================================================================

if (!defined('RISEUP_VERSION')) {
    define('RISEUP_VERSION', '1.4.0');
}
if (!defined('RISEUP_SLUG')) {
    define('RISEUP_SLUG', 'riseup-asia-uploader');
}
if (!defined('RISEUP_NAME')) {
    define('RISEUP_NAME', 'Riseup Asia Uploader');
}
if (!defined('RISEUP_MIN_WP_VERSION')) {
    define('RISEUP_MIN_WP_VERSION', '5.6');
}
if (!defined('RISEUP_MIN_PHP_VERSION')) {
    define('RISEUP_MIN_PHP_VERSION', '7.4');
}

// =============================================================================
// PATHS - All relative to wp-content/uploads/{plugin-slug}/
// =============================================================================

if (!defined('RISEUP_UPLOADS_SUBDIR')) {
    define('RISEUP_UPLOADS_SUBDIR', 'riseup-asia-uploader');
}
if (!defined('RISEUP_LOGS_SUBDIR')) {
    define('RISEUP_LOGS_SUBDIR', 'logs');
}
if (!defined('RISEUP_LOG_FILENAME')) {
    define('RISEUP_LOG_FILENAME', 'log.txt');
}
if (!defined('RISEUP_ERROR_LOG_FILENAME')) {
    define('RISEUP_ERROR_LOG_FILENAME', 'error.txt');
}
if (!defined('RISEUP_DB_FILENAME')) {
    define('RISEUP_DB_FILENAME', 'riseup-asia-uploader.db');
}
if (!defined('RISEUP_TEMP_SUBDIR')) {
    define('RISEUP_TEMP_SUBDIR', 'temp');
}

// =============================================================================
// REST API CONFIGURATION
// =============================================================================

if (!defined('RISEUP_API_NAMESPACE')) {
    define('RISEUP_API_NAMESPACE', 'riseup-asia-uploader');
}
if (!defined('RISEUP_API_VERSION')) {
    define('RISEUP_API_VERSION', 'v1');
}
if (!defined('RISEUP_API_FULL_NAMESPACE')) {
    define('RISEUP_API_FULL_NAMESPACE', RISEUP_API_NAMESPACE . '/' . RISEUP_API_VERSION);
}

// Legacy namespace support (for backward compatibility)
if (!defined('RISEUP_LEGACY_NAMESPACE')) {
    define('RISEUP_LEGACY_NAMESPACE', 'riseup-uploader/v1');
}

// =============================================================================
// REST API ENDPOINTS (plain strings, no regex)
// =============================================================================

if (!defined('RISEUP_ENDPOINT_STATUS')) {
    define('RISEUP_ENDPOINT_STATUS', 'status');
}
if (!defined('RISEUP_ENDPOINT_UPLOAD')) {
    define('RISEUP_ENDPOINT_UPLOAD', 'upload');
}
if (!defined('RISEUP_ENDPOINT_PLUGINS')) {
    define('RISEUP_ENDPOINT_PLUGINS', 'plugins');
}
if (!defined('RISEUP_ENDPOINT_EXPORT_SELF')) {
    define('RISEUP_ENDPOINT_EXPORT_SELF', 'export-self');
}
if (!defined('RISEUP_ENDPOINT_POSTS')) {
    define('RISEUP_ENDPOINT_POSTS', 'posts');
}
if (!defined('RISEUP_ENDPOINT_CATEGORIES')) {
    define('RISEUP_ENDPOINT_CATEGORIES', 'categories');
}
if (!defined('RISEUP_ENDPOINT_LOGS')) {
    define('RISEUP_ENDPOINT_LOGS', 'logs');
}
if (!defined('RISEUP_ENDPOINT_LOGS_STATS')) {
    define('RISEUP_ENDPOINT_LOGS_STATS', 'logs/stats');
}
// Plugin files listing endpoint - expects slug as path parameter
if (!defined('RISEUP_ENDPOINT_PLUGIN_FILES')) {
    define('RISEUP_ENDPOINT_PLUGIN_FILES', 'plugins/(?P<slug>[a-zA-Z0-9_-]+)/files');
}
// Plugin file content endpoint - expects slug as path parameter
if (!defined('RISEUP_ENDPOINT_PLUGIN_FILE')) {
    define('RISEUP_ENDPOINT_PLUGIN_FILE', 'plugins/(?P<slug>[a-zA-Z0-9_-]+)/file');
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================

if (!defined('RISEUP_TABLE_TRANSACTIONS')) {
    define('RISEUP_TABLE_TRANSACTIONS', 'transactions');
}
if (!defined('RISEUP_DB_WAL_MODE')) {
    define('RISEUP_DB_WAL_MODE', true);
}

// =============================================================================
// ACTIONS (used for transaction logging)
// =============================================================================

if (!defined('RISEUP_ACTION_UPLOAD')) {
    define('RISEUP_ACTION_UPLOAD', 'upload');
}
if (!defined('RISEUP_ACTION_UPLOAD_ACTIVE')) {
    define('RISEUP_ACTION_UPLOAD_ACTIVE', 'upload_active');
}
if (!defined('RISEUP_ACTION_ENABLE')) {
    define('RISEUP_ACTION_ENABLE', 'enable');
}
if (!defined('RISEUP_ACTION_DISABLE')) {
    define('RISEUP_ACTION_DISABLE', 'disable');
}
if (!defined('RISEUP_ACTION_DELETE')) {
    define('RISEUP_ACTION_DELETE', 'delete');
}
if (!defined('RISEUP_ACTION_FILE_REPLACE')) {
    define('RISEUP_ACTION_FILE_REPLACE', 'file_replace');
}
if (!defined('RISEUP_ACTION_FILE_DELETE')) {
    define('RISEUP_ACTION_FILE_DELETE', 'file_delete');
}
if (!defined('RISEUP_ACTION_SYNC')) {
    define('RISEUP_ACTION_SYNC', 'sync');
}
if (!defined('RISEUP_ACTION_POST_CREATE')) {
    define('RISEUP_ACTION_POST_CREATE', 'post_create');
}
if (!defined('RISEUP_ACTION_POST_UPDATE')) {
    define('RISEUP_ACTION_POST_UPDATE', 'post_update');
}
if (!defined('RISEUP_ACTION_CATEGORY_CREATE')) {
    define('RISEUP_ACTION_CATEGORY_CREATE', 'category_create');
}
if (!defined('RISEUP_ACTION_MEDIA_UPLOAD')) {
    define('RISEUP_ACTION_MEDIA_UPLOAD', 'media_upload');
}
if (!defined('RISEUP_ACTION_AUTH_FAILED')) {
    define('RISEUP_ACTION_AUTH_FAILED', 'auth_failed');
}
if (!defined('RISEUP_ACTION_EXPORT_SELF')) {
    define('RISEUP_ACTION_EXPORT_SELF', 'export_self');
}

// =============================================================================
// STATUS VALUES
// =============================================================================

if (!defined('RISEUP_STATUS_SUCCESS')) {
    define('RISEUP_STATUS_SUCCESS', 'success');
}
if (!defined('RISEUP_STATUS_FAILED')) {
    define('RISEUP_STATUS_FAILED', 'failed');
}

// =============================================================================
// POST STATUS VALUES
// =============================================================================

if (!defined('RISEUP_POST_STATUS_PUBLISH')) {
    define('RISEUP_POST_STATUS_PUBLISH', 'publish');
}
if (!defined('RISEUP_POST_STATUS_DRAFT')) {
    define('RISEUP_POST_STATUS_DRAFT', 'draft');
}
if (!defined('RISEUP_POST_STATUS_PENDING')) {
    define('RISEUP_POST_STATUS_PENDING', 'pending');
}

// =============================================================================
// RESPONSE MESSAGES
// =============================================================================

if (!defined('RISEUP_MSG_SUCCESS')) {
    define('RISEUP_MSG_SUCCESS', 'Operation completed successfully');
}
if (!defined('RISEUP_MSG_UNAUTHORIZED')) {
    define('RISEUP_MSG_UNAUTHORIZED', 'Authentication required');
}
if (!defined('RISEUP_MSG_FORBIDDEN')) {
    define('RISEUP_MSG_FORBIDDEN', 'Insufficient permissions');
}
if (!defined('RISEUP_MSG_INVALID_REQUEST')) {
    define('RISEUP_MSG_INVALID_REQUEST', 'Invalid request data');
}
if (!defined('RISEUP_MSG_PLUGIN_NOT_FOUND')) {
    define('RISEUP_MSG_PLUGIN_NOT_FOUND', 'Plugin not found');
}
if (!defined('RISEUP_MSG_UPLOAD_FAILED')) {
    define('RISEUP_MSG_UPLOAD_FAILED', 'Upload failed');
}
if (!defined('RISEUP_MSG_ACTIVATION_FAILED')) {
    define('RISEUP_MSG_ACTIVATION_FAILED', 'Plugin activation failed');
}
if (!defined('RISEUP_MSG_DEACTIVATION_FAILED')) {
    define('RISEUP_MSG_DEACTIVATION_FAILED', 'Plugin deactivation failed');
}
if (!defined('RISEUP_MSG_DELETE_FAILED')) {
    define('RISEUP_MSG_DELETE_FAILED', 'Plugin deletion failed');
}
if (!defined('RISEUP_MSG_POST_CREATE_FAILED')) {
    define('RISEUP_MSG_POST_CREATE_FAILED', 'Post creation failed');
}
if (!defined('RISEUP_MSG_POST_UPDATE_FAILED')) {
    define('RISEUP_MSG_POST_UPDATE_FAILED', 'Post update failed');
}
if (!defined('RISEUP_MSG_CATEGORY_CREATE_FAILED')) {
    define('RISEUP_MSG_CATEGORY_CREATE_FAILED', 'Category creation failed');
}
if (!defined('RISEUP_MSG_MEDIA_UPLOAD_FAILED')) {
    define('RISEUP_MSG_MEDIA_UPLOAD_FAILED', 'Media upload failed');
}
if (!defined('RISEUP_MSG_DB_ERROR')) {
    define('RISEUP_MSG_DB_ERROR', 'Database error');
}
if (!defined('RISEUP_MSG_FILE_IGNORED')) {
    define('RISEUP_MSG_FILE_IGNORED', 'File ignored by .uploadignore');
}

// =============================================================================
// CAPABILITIES
// =============================================================================

if (!defined('RISEUP_CAP_MANAGE_PLUGINS')) {
    define('RISEUP_CAP_MANAGE_PLUGINS', 'activate_plugins');
}
if (!defined('RISEUP_CAP_MANAGE_POSTS')) {
    define('RISEUP_CAP_MANAGE_POSTS', 'publish_posts');
}
if (!defined('RISEUP_CAP_UPLOAD_MEDIA')) {
    define('RISEUP_CAP_UPLOAD_MEDIA', 'upload_files');
}
if (!defined('RISEUP_CAP_VIEW_LOGS')) {
    define('RISEUP_CAP_VIEW_LOGS', 'manage_options');
}

// =============================================================================
// PAGINATION DEFAULTS
// =============================================================================

if (!defined('RISEUP_DEFAULT_LIMIT')) {
    define('RISEUP_DEFAULT_LIMIT', 50);
}
if (!defined('RISEUP_MAX_LIMIT')) {
    define('RISEUP_MAX_LIMIT', 500);
}

// =============================================================================
// IGNORE FILE
// =============================================================================

if (!defined('RISEUP_IGNORE_FILENAME')) {
    define('RISEUP_IGNORE_FILENAME', '.uploadignore');
}

// =============================================================================
// HTTP STATUS CODES
// =============================================================================

if (!defined('RISEUP_HTTP_OK')) {
    define('RISEUP_HTTP_OK', 200);
}
if (!defined('RISEUP_HTTP_CREATED')) {
    define('RISEUP_HTTP_CREATED', 201);
}
if (!defined('RISEUP_HTTP_BAD_REQUEST')) {
    define('RISEUP_HTTP_BAD_REQUEST', 400);
}
if (!defined('RISEUP_HTTP_UNAUTHORIZED')) {
    define('RISEUP_HTTP_UNAUTHORIZED', 401);
}
if (!defined('RISEUP_HTTP_FORBIDDEN')) {
    define('RISEUP_HTTP_FORBIDDEN', 403);
}
if (!defined('RISEUP_HTTP_NOT_FOUND')) {
    define('RISEUP_HTTP_NOT_FOUND', 404);
}
if (!defined('RISEUP_HTTP_SERVER_ERROR')) {
    define('RISEUP_HTTP_SERVER_ERROR', 500);
}

// =============================================================================
// LOG LEVELS
// =============================================================================

if (!defined('RISEUP_LOG_LEVEL_DEBUG')) {
    define('RISEUP_LOG_LEVEL_DEBUG', 'DEBUG');
}
if (!defined('RISEUP_LOG_LEVEL_INFO')) {
    define('RISEUP_LOG_LEVEL_INFO', 'INFO');
}
if (!defined('RISEUP_LOG_LEVEL_WARN')) {
    define('RISEUP_LOG_LEVEL_WARN', 'WARN');
}
if (!defined('RISEUP_LOG_LEVEL_ERROR')) {
    define('RISEUP_LOG_LEVEL_ERROR', 'ERROR');
}

// =============================================================================
// LOGGING PREFIX
// =============================================================================

if (!defined('RISEUP_LOG_PREFIX')) {
    define('RISEUP_LOG_PREFIX', '[Riseup Asia]');
}
