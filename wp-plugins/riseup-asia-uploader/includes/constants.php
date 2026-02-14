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
    define('MIN_PHP_VERSION', '8.2');
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

// RESPONSE MESSAGES — migrated to ResponseMessageType enum

// HTTP STATUS CODES — migrated to HttpStatusType enum

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

// SNAPSHOT ERROR CODES — migrated to SnapshotErrorType enum

// ENDPOINT_ERROR_LOGS, ENDPOINT_ERROR_SESSIONS — migrated to EndpointType enum

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

if (!defined('OPTION_LOG_RETRIEVAL')) {
    define('OPTION_LOG_RETRIEVAL', 'riseup_log_retrieval_settings');
}

if (!defined('LOG_RETRIEVAL_MAX_LINES')) {
    define('LOG_RETRIEVAL_MAX_LINES', 500);
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
