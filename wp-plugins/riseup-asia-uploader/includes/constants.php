<?php
/**
 * Riseup Asia Uploader - Plugin Constants
 *
 * MIGRATION STATUS: Most constants have been migrated to PHP 8.2+ backed enums.
 * See includes/Enums/ for the canonical sources. Constants here are retained
 * only for backward compatibility with external consumers.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 * @deprecated Use PluginConfigType, OptionNameType, and other enum classes instead.
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// PLUGIN IDENTITY — @see PluginConfigType enum
// =============================================================================

/** @deprecated Use PluginConfigType::Version->value */
if (!defined('PLUGIN_VERSION')) {
    define('PLUGIN_VERSION', '1.57.0');
}
/** @deprecated Use PluginConfigType::Slug->value */
if (!defined('PLUGIN_SLUG')) {
    define('PLUGIN_SLUG', 'riseup-asia-uploader');
}
/** @deprecated Use PluginConfigType::Name->value */
if (!defined('PLUGIN_NAME')) {
    define('PLUGIN_NAME', 'Riseup Asia Uploader');
}
/** @deprecated Use PluginConfigType::MinWpVersion->value */
if (!defined('MIN_WP_VERSION')) {
    define('MIN_WP_VERSION', '5.6');
}
/** @deprecated Use PluginConfigType::MinPhpVersion->value */
if (!defined('MIN_PHP_VERSION')) {
    define('MIN_PHP_VERSION', '8.2');
}

// =============================================================================
// PATHS — @see PathSubdirType, PathDatabaseType, PathLogFileType, PathConfigType enums
// UPLOADS_SUBDIR retained as plugin identity slug.
// =============================================================================

/** @deprecated Use PluginConfigType::UploadsSubdir->value */
if (!defined('UPLOADS_SUBDIR')) {
    define('UPLOADS_SUBDIR', 'riseup-asia-uploader');
}

// =============================================================================
// REST API CONFIGURATION — @see PluginConfigType enum
// =============================================================================

/** @deprecated Use PluginConfigType::ApiNamespace->value */
if (!defined('API_NAMESPACE')) {
    define('API_NAMESPACE', 'riseup-asia-uploader');
}
/** @deprecated Use PluginConfigType::ApiVersion->value */
if (!defined('API_VERSION')) {
    define('API_VERSION', 'v1');
}
/** @deprecated Use PluginConfigType::apiFullNamespace() */
if (!defined('API_FULL_NAMESPACE')) {
    define('API_FULL_NAMESPACE', API_NAMESPACE . '/' . API_VERSION);
}
/** @deprecated Use PluginConfigType::LegacyNamespace->value */
if (!defined('LEGACY_NAMESPACE')) {
    define('LEGACY_NAMESPACE', 'riseup-uploader/v1');
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================
if (!defined('DB_WAL_MODE')) {
    define('DB_WAL_MODE', true);
}

// =============================================================================
// LOGGING PREFIX — @see PluginConfigType::LogPrefix
// =============================================================================

/** @deprecated Use PluginConfigType::LogPrefix->value */
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

// =============================================================================
// WORDPRESS OPTIONS — @see OptionNameType enum
// =============================================================================

/** @deprecated Use OptionNameType::SnapshotSettings->value */
if (!defined('OPTION_SNAPSHOT_SETTINGS')) {
    define('OPTION_SNAPSHOT_SETTINGS', 'riseup_snapshot_settings');
}

// =============================================================================
// SNAPSHOT CLEANUP CONSTANTS
// =============================================================================

if (!defined('SNAPSHOT_STUCK_HOURS')) {
    define('SNAPSHOT_STUCK_HOURS', 24);
}

/** @deprecated Use OptionNameType::LogRetrieval->value */
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
// IGNORE FILE — @see PluginConfigType::IgnoreFilename
// =============================================================================

/** @deprecated Use PluginConfigType::IgnoreFilename->value */
if (!defined('IGNORE_FILENAME')) {
    define('IGNORE_FILENAME', '.uploadignore');
}
