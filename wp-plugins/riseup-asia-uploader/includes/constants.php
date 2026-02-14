<?php
/**
 * Riseup Asia Uploader - Plugin Constants
 *
 * All string constants centralized to avoid magic strings.
 * These can be overridden by defining them before this file is loaded.
 *
 * NAMING CONVENTION: Constants do NOT use the RISEUP_ prefix.
 * Categorized constants live in Enum classes (HookEnum, CapabilityEnum, etc.).
 * Only non-categorized constants remain here as define() calls.
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

if (!defined('PLUGIN_VERSION')) {
    define('PLUGIN_VERSION', '1.57.0');
}
if (!defined('PLUGIN_SLUG')) {
    define('PLUGIN_SLUG', 'riseup-asia-uploader');
}
if (!defined('PLUGIN_NAME')) {
    define('PLUGIN_NAME', 'Riseup Asia Uploader');
}
if (!defined('MIN_WP_VERSION')) {
    define('MIN_WP_VERSION', '5.6');
}
if (!defined('MIN_PHP_VERSION')) {
    define('MIN_PHP_VERSION', '7.4');
}

// =============================================================================
// PATHS - Only UPLOADS_SUBDIR (plugin identity slug) remains here.
// Other path constants migrated to PathSubdirType, PathDatabaseType,
// PathLogFileType, and PathConfigType enums.
// =============================================================================

if (!defined('UPLOADS_SUBDIR')) {
    define('UPLOADS_SUBDIR', 'riseup-asia-uploader');
}

// =============================================================================
// REST API CONFIGURATION
// =============================================================================

if (!defined('API_NAMESPACE')) {
    define('API_NAMESPACE', 'riseup-asia-uploader');
}
if (!defined('API_VERSION')) {
    define('API_VERSION', 'v1');
}
if (!defined('API_FULL_NAMESPACE')) {
    define('API_FULL_NAMESPACE', API_NAMESPACE . '/' . API_VERSION);
}

// Legacy namespace support (for backward compatibility)
if (!defined('LEGACY_NAMESPACE')) {
    define('LEGACY_NAMESPACE', 'riseup-uploader/v1');
}

// =============================================================================
// REST API ENDPOINTS — migrated to EndpointType enum
// =============================================================================

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================
if (!defined('DB_WAL_MODE')) {
    define('DB_WAL_MODE', true);
}

// =============================================================================
// RESPONSE MESSAGES
// =============================================================================

if (!defined('MSG_SUCCESS')) {
    define('MSG_SUCCESS', 'Operation completed successfully');
}
if (!defined('MSG_UNAUTHORIZED')) {
    define('MSG_UNAUTHORIZED', 'Authentication required');
}
if (!defined('MSG_FORBIDDEN')) {
    define('MSG_FORBIDDEN', 'Insufficient permissions');
}
if (!defined('MSG_INVALID_REQUEST')) {
    define('MSG_INVALID_REQUEST', 'Invalid request data');
}
if (!defined('MSG_PLUGIN_NOT_FOUND')) {
    define('MSG_PLUGIN_NOT_FOUND', 'Plugin not found');
}
if (!defined('MSG_UPLOAD_FAILED')) {
    define('MSG_UPLOAD_FAILED', 'Upload failed');
}
if (!defined('MSG_ACTIVATION_FAILED')) {
    define('MSG_ACTIVATION_FAILED', 'Plugin activation failed');
}
if (!defined('MSG_DEACTIVATION_FAILED')) {
    define('MSG_DEACTIVATION_FAILED', 'Plugin deactivation failed');
}
if (!defined('MSG_DELETE_FAILED')) {
    define('MSG_DELETE_FAILED', 'Plugin deletion failed');
}
if (!defined('MSG_POST_CREATE_FAILED')) {
    define('MSG_POST_CREATE_FAILED', 'Post creation failed');
}
if (!defined('MSG_POST_UPDATE_FAILED')) {
    define('MSG_POST_UPDATE_FAILED', 'Post update failed');
}
if (!defined('MSG_CATEGORY_CREATE_FAILED')) {
    define('MSG_CATEGORY_CREATE_FAILED', 'Category creation failed');
}
if (!defined('MSG_MEDIA_UPLOAD_FAILED')) {
    define('MSG_MEDIA_UPLOAD_FAILED', 'Media upload failed');
}
if (!defined('MSG_DB_ERROR')) {
    define('MSG_DB_ERROR', 'Database error');
}
if (!defined('MSG_FILE_IGNORED')) {
    define('MSG_FILE_IGNORED', 'File ignored by .uploadignore');
}

// =============================================================================
// PAGINATION DEFAULTS
// =============================================================================

if (!defined('DEFAULT_LIMIT')) {
    define('DEFAULT_LIMIT', 50);
}
if (!defined('MAX_LIMIT')) {
    define('MAX_LIMIT', 500);
}

// =============================================================================
// IGNORE FILE
// =============================================================================

if (!defined('IGNORE_FILENAME')) {
    define('IGNORE_FILENAME', '.uploadignore');
}

// =============================================================================
// HTTP STATUS CODES
// =============================================================================

if (!defined('HTTP_OK')) {
    define('HTTP_OK', 200);
}
if (!defined('HTTP_CREATED')) {
    define('HTTP_CREATED', 201);
}
if (!defined('HTTP_BAD_REQUEST')) {
    define('HTTP_BAD_REQUEST', 400);
}
if (!defined('HTTP_UNAUTHORIZED')) {
    define('HTTP_UNAUTHORIZED', 401);
}
if (!defined('HTTP_FORBIDDEN')) {
    define('HTTP_FORBIDDEN', 403);
}
if (!defined('HTTP_NOT_FOUND')) {
    define('HTTP_NOT_FOUND', 404);
}
if (!defined('HTTP_SERVER_ERROR')) {
    define('HTTP_SERVER_ERROR', 500);
}

// =============================================================================
// LOGGING PREFIX
// =============================================================================

if (!defined('LOG_PREFIX')) {
    define('LOG_PREFIX', '[Riseup Asia]');
}

// =============================================================================
// AUTO-UPDATE CONFIGURATION
// =============================================================================

if (!defined('UPDATE_CACHE_DAYS_DEFAULT')) {
    define('UPDATE_CACHE_DAYS_DEFAULT', 7);
}
if (!defined('UPDATE_MAX_REDIRECTS')) {
    define('UPDATE_MAX_REDIRECTS', 5);
}

// =============================================================================
// SNAPSHOT DEFAULTS (numeric/config — not migrated to enums)
// =============================================================================

if (!defined('SNAPSHOT_BATCH_SIZE')) {
    define('SNAPSHOT_BATCH_SIZE', 1000);
}
if (!defined('SNAPSHOT_MAX_SIZE_MB')) {
    define('SNAPSHOT_MAX_SIZE_MB', 500);
}
if (!defined('SNAPSHOT_RETENTION_DAYS_DEFAULT')) {
    define('SNAPSHOT_RETENTION_DAYS_DEFAULT', 30);
}
if (!defined('SNAPSHOT_RETENTION_COUNT_DEFAULT')) {
    define('SNAPSHOT_RETENTION_COUNT_DEFAULT', 10);
}

// Worker pool configuration
if (!defined('SNAPSHOT_WORKER_POOL_MIN')) {
    define('SNAPSHOT_WORKER_POOL_MIN', 1);
}
if (!defined('SNAPSHOT_WORKER_POOL_MAX')) {
    define('SNAPSHOT_WORKER_POOL_MAX', 10);
}
if (!defined('SNAPSHOT_WORKER_POOL_DEFAULT')) {
    define('SNAPSHOT_WORKER_POOL_DEFAULT', 5);
}

// CRON_SNAPSHOT_* constants removed — use HookType::CronSnapshot*->value instead.

// =============================================================================
// SNAPSHOT ERROR CODES
// =============================================================================

if (!defined('ERR_SNAPSHOT_LOCK_EXISTS')) {
    define('ERR_SNAPSHOT_LOCK_EXISTS', 'SNAPSHOT_LOCK_EXISTS');
}
if (!defined('ERR_SNAPSHOT_NOT_FOUND')) {
    define('ERR_SNAPSHOT_NOT_FOUND', 'SNAPSHOT_NOT_FOUND');
}
if (!defined('ERR_SNAPSHOT_CORRUPT')) {
    define('ERR_SNAPSHOT_CORRUPT', 'SNAPSHOT_CORRUPT');
}
if (!defined('ERR_SNAPSHOT_TOO_LARGE')) {
    define('ERR_SNAPSHOT_TOO_LARGE', 'SNAPSHOT_TOO_LARGE');
}
if (!defined('ERR_RESTORE_FAILED')) {
    define('ERR_RESTORE_FAILED', 'RESTORE_FAILED');
}
if (!defined('ERR_RESTORE_NO_CONFIRM')) {
    define('ERR_RESTORE_NO_CONFIRM', 'RESTORE_NO_CONFIRM');
}
if (!defined('ERR_PROVIDER_NOT_AVAILABLE')) {
    define('ERR_PROVIDER_NOT_AVAILABLE', 'PROVIDER_NOT_AVAILABLE');
}

// WordPress options key for snapshot settings
if (!defined('OPTION_SNAPSHOT_SETTINGS')) {
    define('OPTION_SNAPSHOT_SETTINGS', 'riseup_snapshot_settings');
}

// =============================================================================
// SNAPSHOT CLEANUP CONSTANTS
// =============================================================================

if (!defined('SNAPSHOT_STUCK_HOURS')) {
    define('SNAPSHOT_STUCK_HOURS', 24);
}

if (!defined('ERR_INCREMENTAL_NO_PARENT')) {
    define('ERR_INCREMENTAL_NO_PARENT', 'INCREMENTAL_NO_PARENT');
}

// =============================================================================
// SNAPSHOT ZIP EXPORT ERROR CODES
// =============================================================================

if (!defined('ERR_EXPORT_NOT_FOUND')) {
    define('ERR_EXPORT_NOT_FOUND', 'EXPORT_NOT_FOUND');
}
if (!defined('ERR_EXPORT_BUILD_FAILED')) {
    define('ERR_EXPORT_BUILD_FAILED', 'EXPORT_BUILD_FAILED');
}
if (!defined('ERR_EXPORT_TOKEN_INVALID')) {
    define('ERR_EXPORT_TOKEN_INVALID', 'EXPORT_TOKEN_INVALID');
}

// =============================================================================
// PHP LOG RETRIEVAL ENDPOINT
// =============================================================================

if (!defined('ENDPOINT_ERROR_LOGS')) {
    define('ENDPOINT_ERROR_LOGS', 'error-logs');
}

if (!defined('OPTION_LOG_RETRIEVAL')) {
    define('OPTION_LOG_RETRIEVAL', 'riseup_log_retrieval_settings');
}

if (!defined('LOG_RETRIEVAL_MAX_LINES')) {
    define('LOG_RETRIEVAL_MAX_LINES', 500);
}

if (!defined('ENDPOINT_ERROR_SESSIONS')) {
    define('ENDPOINT_ERROR_SESSIONS', 'error-sessions');
}
