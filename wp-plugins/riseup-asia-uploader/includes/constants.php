<?php
/**
 * Riseup Asia Uploader - Plugin Constants
 *
 * Contains only non-deprecated runtime configuration constants.
 * All identity, path, REST, and option constants have been migrated
 * to PHP 8.2+ backed enums in includes/Enums/.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 * @see     PluginConfigType, OptionNameType, PathSubdirType
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================
if (!defined('DB_WAL_MODE')) {
    define('DB_WAL_MODE', true);
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
// SNAPSHOT CLEANUP CONSTANTS
// =============================================================================

if (!defined('SNAPSHOT_STUCK_HOURS')) {
    define('SNAPSHOT_STUCK_HOURS', 24);
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
