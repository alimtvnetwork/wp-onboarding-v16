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
    define('RISEUP_VERSION', '1.45.0');
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
if (!defined('RISEUP_STACKTRACE_FILENAME')) {
    define('RISEUP_STACKTRACE_FILENAME', 'stacktrace.txt');
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
// Plugin files listing endpoint - fixed URL, slug passed in JSON body
if (!defined('RISEUP_ENDPOINT_PLUGIN_FILES')) {
    define('RISEUP_ENDPOINT_PLUGIN_FILES', 'plugins/files');
}
// Plugin file content endpoint - fixed URL, slug passed in JSON body
if (!defined('RISEUP_ENDPOINT_PLUGIN_FILE')) {
    define('RISEUP_ENDPOINT_PLUGIN_FILE', 'plugins/file');
}
// Plugin enable/disable/delete endpoints - fixed URLs, slug passed in JSON body
if (!defined('RISEUP_ENDPOINT_PLUGIN_ENABLE')) {
    define('RISEUP_ENDPOINT_PLUGIN_ENABLE', 'plugins/enable');
}
if (!defined('RISEUP_ENDPOINT_PLUGIN_DISABLE')) {
    define('RISEUP_ENDPOINT_PLUGIN_DISABLE', 'plugins/disable');
}
if (!defined('RISEUP_ENDPOINT_PLUGIN_DELETE')) {
    define('RISEUP_ENDPOINT_PLUGIN_DELETE', 'plugins/delete');
}
// Plugin existence check endpoint - lightweight pre-flight check, slug in JSON body
if (!defined('RISEUP_ENDPOINT_PLUGIN_EXISTS')) {
    define('RISEUP_ENDPOINT_PLUGIN_EXISTS', 'plugins/exists');
}
// Plugin export endpoint - fixed URL, slug passed in JSON body
if (!defined('RISEUP_ENDPOINT_PLUGIN_EXPORT')) {
    define('RISEUP_ENDPOINT_PLUGIN_EXPORT', 'plugins/export');
}
// OpenAPI specification endpoint
if (!defined('RISEUP_ENDPOINT_OPENAPI')) {
    define('RISEUP_ENDPOINT_OPENAPI', 'openapi');
}
// OPcache reset endpoint (used after self-updates to flush stale bytecode)
if (!defined('RISEUP_ENDPOINT_OPCACHE_RESET')) {
    define('RISEUP_ENDPOINT_OPCACHE_RESET', 'opcache-reset');
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
if (!defined('RISEUP_ACTION_EXPORT_PLUGIN')) {
    define('RISEUP_ACTION_EXPORT_PLUGIN', 'export_plugin');
}
if (!defined('RISEUP_ACTION_UPLOAD_INITIATED')) {
    define('RISEUP_ACTION_UPLOAD_INITIATED', 'upload_initiated');
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

// =============================================================================
// AUTO-UPDATE CONFIGURATION
// =============================================================================

if (!defined('RISEUP_UPDATE_CACHE_DAYS_DEFAULT')) {
    define('RISEUP_UPDATE_CACHE_DAYS_DEFAULT', 7);
}
if (!defined('RISEUP_UPDATE_MAX_REDIRECTS')) {
    define('RISEUP_UPDATE_MAX_REDIRECTS', 5);
}

// =============================================================================
// AUTO-UPDATE ACTIONS (for transaction logging)
// =============================================================================

if (!defined('RISEUP_ACTION_UPDATE_CHECK')) {
    define('RISEUP_ACTION_UPDATE_CHECK', 'update_check');
}
if (!defined('RISEUP_ACTION_UPDATE_RESOLVE')) {
    define('RISEUP_ACTION_UPDATE_RESOLVE', 'update_resolve');
}
if (!defined('RISEUP_ACTION_UPDATE_DOWNLOAD')) {
    define('RISEUP_ACTION_UPDATE_DOWNLOAD', 'update_download');
}
if (!defined('RISEUP_ACTION_UPDATE_INSTALL')) {
    define('RISEUP_ACTION_UPDATE_INSTALL', 'update_install');
}

// =============================================================================
// AGENT MANAGEMENT CONSTANTS
// =============================================================================

if (!defined('RISEUP_TABLE_AGENT_SITES')) {
    define('RISEUP_TABLE_AGENT_SITES', 'agent_sites');
}
if (!defined('RISEUP_TABLE_AGENT_ACTIONS')) {
    define('RISEUP_TABLE_AGENT_ACTIONS', 'agent_actions');
}

// Agent action types
if (!defined('RISEUP_ACTION_AGENT_ADD')) {
    define('RISEUP_ACTION_AGENT_ADD', 'agent_add');
}
if (!defined('RISEUP_ACTION_AGENT_REMOVE')) {
    define('RISEUP_ACTION_AGENT_REMOVE', 'agent_remove');
}
if (!defined('RISEUP_ACTION_AGENT_TEST')) {
    define('RISEUP_ACTION_AGENT_TEST', 'agent_test');
}
if (!defined('RISEUP_ACTION_AGENT_SYNC')) {
    define('RISEUP_ACTION_AGENT_SYNC', 'agent_sync');
}
if (!defined('RISEUP_ACTION_AGENT_PLUGIN_ENABLE')) {
    define('RISEUP_ACTION_AGENT_PLUGIN_ENABLE', 'agent_plugin_enable');
}
if (!defined('RISEUP_ACTION_AGENT_PLUGIN_DISABLE')) {
    define('RISEUP_ACTION_AGENT_PLUGIN_DISABLE', 'agent_plugin_disable');
}
if (!defined('RISEUP_ACTION_AGENT_PLUGIN_DELETE')) {
    define('RISEUP_ACTION_AGENT_PLUGIN_DELETE', 'agent_plugin_delete');
}
if (!defined('RISEUP_ACTION_AGENT_PLUGIN_UPDATE')) {
    define('RISEUP_ACTION_AGENT_PLUGIN_UPDATE', 'agent_plugin_update');
}

// Agent status values
if (!defined('RISEUP_AGENT_STATUS_PENDING')) {
    define('RISEUP_AGENT_STATUS_PENDING', 'pending');
}
if (!defined('RISEUP_AGENT_STATUS_CONNECTED')) {
    define('RISEUP_AGENT_STATUS_CONNECTED', 'connected');
}
if (!defined('RISEUP_AGENT_STATUS_ERROR')) {
    define('RISEUP_AGENT_STATUS_ERROR', 'error');
}

// Agent REST endpoints - ALL fixed paths, IDs passed in JSON body
if (!defined('RISEUP_ENDPOINT_AGENTS')) {
    define('RISEUP_ENDPOINT_AGENTS', 'agents');
}
if (!defined('RISEUP_ENDPOINT_AGENT_TEST')) {
    define('RISEUP_ENDPOINT_AGENT_TEST', 'agents/test');
}
if (!defined('RISEUP_ENDPOINT_AGENT_SYNC')) {
    define('RISEUP_ENDPOINT_AGENT_SYNC', 'agents/sync');
}
if (!defined('RISEUP_ENDPOINT_AGENT_ACTION')) {
    define('RISEUP_ENDPOINT_AGENT_ACTION', 'agents/action');
}
if (!defined('RISEUP_ENDPOINT_AGENT_HISTORY')) {
    define('RISEUP_ENDPOINT_AGENT_HISTORY', 'agents/history');
}
// Plural aliases used by register_routes (must match exactly)
if (!defined('RISEUP_ENDPOINT_AGENTS_LIST')) {
    define('RISEUP_ENDPOINT_AGENTS_LIST', 'agents');
}
if (!defined('RISEUP_ENDPOINT_AGENTS_ADD')) {
    define('RISEUP_ENDPOINT_AGENTS_ADD', 'agents/add');
}
if (!defined('RISEUP_ENDPOINT_AGENTS_REMOVE')) {
    define('RISEUP_ENDPOINT_AGENTS_REMOVE', 'agents/remove');
}
if (!defined('RISEUP_ENDPOINT_AGENTS_TEST')) {
    define('RISEUP_ENDPOINT_AGENTS_TEST', 'agents/test');
}
if (!defined('RISEUP_ENDPOINT_AGENTS_SYNC')) {
    define('RISEUP_ENDPOINT_AGENTS_SYNC', 'agents/sync');
}
if (!defined('RISEUP_ENDPOINT_AGENTS_PLUGINS')) {
    define('RISEUP_ENDPOINT_AGENTS_PLUGINS', 'agents/plugins');
}

// =============================================================================
// TRIGGERED_BY VALUES (for enhanced transaction logging)
// =============================================================================

if (!defined('RISEUP_TRIGGERED_BY_API')) {
    define('RISEUP_TRIGGERED_BY_API', 'api');
}
if (!defined('RISEUP_TRIGGERED_BY_DASHBOARD')) {
    define('RISEUP_TRIGGERED_BY_DASHBOARD', 'dashboard');
}
if (!defined('RISEUP_TRIGGERED_BY_AGENT')) {
    define('RISEUP_TRIGGERED_BY_AGENT', 'agent_push');
}
if (!defined('RISEUP_TRIGGERED_BY_CRON')) {
    define('RISEUP_TRIGGERED_BY_CRON', 'cron');
}
if (!defined('RISEUP_TRIGGERED_BY_CLI')) {
    define('RISEUP_TRIGGERED_BY_CLI', 'cli');
}

// =============================================================================
// SNAPSHOT SYSTEM CONSTANTS
// =============================================================================

// Snapshot providers
if (!defined('RISEUP_SNAPSHOT_PROVIDER_WP_RESET')) {
    define('RISEUP_SNAPSHOT_PROVIDER_WP_RESET', 'wp_reset');
}
if (!defined('RISEUP_SNAPSHOT_PROVIDER_UPDRAFT')) {
    define('RISEUP_SNAPSHOT_PROVIDER_UPDRAFT', 'updraft');
}
if (!defined('RISEUP_SNAPSHOT_PROVIDER_NATIVE')) {
    define('RISEUP_SNAPSHOT_PROVIDER_NATIVE', 'native');
}
if (!defined('RISEUP_SNAPSHOT_PROVIDER_AUTO')) {
    define('RISEUP_SNAPSHOT_PROVIDER_AUTO', 'auto');
}

// Snapshot status values
if (!defined('RISEUP_SNAPSHOT_STATUS_PENDING')) {
    define('RISEUP_SNAPSHOT_STATUS_PENDING', 'pending');
}
if (!defined('RISEUP_SNAPSHOT_STATUS_SCHEDULED')) {
    define('RISEUP_SNAPSHOT_STATUS_SCHEDULED', 'scheduled');
}
if (!defined('RISEUP_SNAPSHOT_STATUS_RUNNING')) {
    define('RISEUP_SNAPSHOT_STATUS_RUNNING', 'running');
}
if (!defined('RISEUP_SNAPSHOT_STATUS_COMPLETE')) {
    define('RISEUP_SNAPSHOT_STATUS_COMPLETE', 'complete');
}
if (!defined('RISEUP_SNAPSHOT_STATUS_FAILED')) {
    define('RISEUP_SNAPSHOT_STATUS_FAILED', 'failed');
}

// Snapshot scope values
if (!defined('RISEUP_SNAPSHOT_SCOPE_ALL')) {
    define('RISEUP_SNAPSHOT_SCOPE_ALL', 'all');
}
if (!defined('RISEUP_SNAPSHOT_SCOPE_WORDPRESS')) {
    define('RISEUP_SNAPSHOT_SCOPE_WORDPRESS', 'wordpress');
}
if (!defined('RISEUP_SNAPSHOT_SCOPE_CONTENT')) {
    define('RISEUP_SNAPSHOT_SCOPE_CONTENT', 'content');
}
if (!defined('RISEUP_SNAPSHOT_SCOPE_CUSTOM')) {
    define('RISEUP_SNAPSHOT_SCOPE_CUSTOM', 'custom');
}

// Snapshot schedule frequencies
if (!defined('RISEUP_SNAPSHOT_FREQ_MANUAL')) {
    define('RISEUP_SNAPSHOT_FREQ_MANUAL', 'manual');
}
if (!defined('RISEUP_SNAPSHOT_FREQ_DAILY')) {
    define('RISEUP_SNAPSHOT_FREQ_DAILY', 'daily');
}
if (!defined('RISEUP_SNAPSHOT_FREQ_WEEKLY')) {
    define('RISEUP_SNAPSHOT_FREQ_WEEKLY', 'weekly');
}
if (!defined('RISEUP_SNAPSHOT_FREQ_MONTHLY')) {
    define('RISEUP_SNAPSHOT_FREQ_MONTHLY', 'monthly');
}

// Snapshot actions (for transaction logging)
if (!defined('RISEUP_ACTION_SNAPSHOT_CREATE')) {
    define('RISEUP_ACTION_SNAPSHOT_CREATE', 'snapshot_create');
}
if (!defined('RISEUP_ACTION_SNAPSHOT_RESTORE')) {
    define('RISEUP_ACTION_SNAPSHOT_RESTORE', 'snapshot_restore');
}
if (!defined('RISEUP_ACTION_SNAPSHOT_DELETE')) {
    define('RISEUP_ACTION_SNAPSHOT_DELETE', 'snapshot_delete');
}
if (!defined('RISEUP_ACTION_SNAPSHOT_EXPORT')) {
    define('RISEUP_ACTION_SNAPSHOT_EXPORT', 'snapshot_export');
}
if (!defined('RISEUP_ACTION_SNAPSHOT_IMPORT')) {
    define('RISEUP_ACTION_SNAPSHOT_IMPORT', 'snapshot_import');
}

// Snapshot REST endpoints - ALL fixed paths, IDs passed in JSON body
if (!defined('RISEUP_ENDPOINT_SNAPSHOTS')) {
    define('RISEUP_ENDPOINT_SNAPSHOTS', 'snapshots/list');
}
// Alias used by register_routes
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_LIST')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_LIST', 'snapshots/list');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_SCHEDULE')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_SCHEDULE', 'snapshots/schedule');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_INFO')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_INFO', 'snapshots/info');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_DELETE')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_DELETE', 'snapshots/delete');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_RESTORE')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_RESTORE', 'snapshots/restore');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_EXPORT')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_EXPORT', 'snapshots/export');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_IMPORT')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_IMPORT', 'snapshots/import');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_SETTINGS')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_SETTINGS', 'snapshots/settings');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_PROVIDERS')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_PROVIDERS', 'snapshots/providers');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_TABLES')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_TABLES', 'snapshots/tables');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_DEPENDENCIES')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_DEPENDENCIES', 'snapshots/dependencies');
}
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_EXPORT_PERTABLE')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_EXPORT_PERTABLE', 'snapshots/export-pertable');
}

// Snapshot table names
if (!defined('RISEUP_TABLE_SNAPSHOTS')) {
    define('RISEUP_TABLE_SNAPSHOTS', 'snapshots');
}
if (!defined('RISEUP_TABLE_SNAPSHOT_PROGRESS')) {
    define('RISEUP_TABLE_SNAPSHOT_PROGRESS', 'snapshot_progress');
}

// Snapshot folders
if (!defined('RISEUP_SNAPSHOTS_SUBDIR')) {
    define('RISEUP_SNAPSHOTS_SUBDIR', 'snapshots');
}

// Snapshot defaults
if (!defined('RISEUP_SNAPSHOT_BATCH_SIZE')) {
    define('RISEUP_SNAPSHOT_BATCH_SIZE', 1000);
}
if (!defined('RISEUP_SNAPSHOT_MAX_SIZE_MB')) {
    define('RISEUP_SNAPSHOT_MAX_SIZE_MB', 500);
}
if (!defined('RISEUP_SNAPSHOT_RETENTION_DAYS_DEFAULT')) {
    define('RISEUP_SNAPSHOT_RETENTION_DAYS_DEFAULT', 30);
}
if (!defined('RISEUP_SNAPSHOT_RETENTION_COUNT_DEFAULT')) {
    define('RISEUP_SNAPSHOT_RETENTION_COUNT_DEFAULT', 10);
}

// Snapshot cron hooks
if (!defined('RISEUP_CRON_SNAPSHOT_SCHEDULED')) {
    define('RISEUP_CRON_SNAPSHOT_SCHEDULED', 'riseup_snapshot_scheduled');
}
if (!defined('RISEUP_CRON_SNAPSHOT_IMMEDIATE')) {
    define('RISEUP_CRON_SNAPSHOT_IMMEDIATE', 'riseup_snapshot_immediate');
}
if (!defined('RISEUP_CRON_SNAPSHOT_CLEANUP')) {
    define('RISEUP_CRON_SNAPSHOT_CLEANUP', 'riseup_snapshot_cleanup');
}
if (!defined('RISEUP_CRON_SNAPSHOT_TABLE')) {
    define('RISEUP_CRON_SNAPSHOT_TABLE', 'riseup_snapshot_table');
}

// Snapshot error codes
if (!defined('RISEUP_ERR_SNAPSHOT_LOCK_EXISTS')) {
    define('RISEUP_ERR_SNAPSHOT_LOCK_EXISTS', 'SNAPSHOT_LOCK_EXISTS');
}
if (!defined('RISEUP_ERR_SNAPSHOT_NOT_FOUND')) {
    define('RISEUP_ERR_SNAPSHOT_NOT_FOUND', 'SNAPSHOT_NOT_FOUND');
}
if (!defined('RISEUP_ERR_SNAPSHOT_CORRUPT')) {
    define('RISEUP_ERR_SNAPSHOT_CORRUPT', 'SNAPSHOT_CORRUPT');
}
if (!defined('RISEUP_ERR_SNAPSHOT_TOO_LARGE')) {
    define('RISEUP_ERR_SNAPSHOT_TOO_LARGE', 'SNAPSHOT_TOO_LARGE');
}
if (!defined('RISEUP_ERR_RESTORE_FAILED')) {
    define('RISEUP_ERR_RESTORE_FAILED', 'RESTORE_FAILED');
}
if (!defined('RISEUP_ERR_RESTORE_NO_CONFIRM')) {
    define('RISEUP_ERR_RESTORE_NO_CONFIRM', 'RESTORE_NO_CONFIRM');
}
if (!defined('RISEUP_ERR_PROVIDER_NOT_AVAILABLE')) {
    define('RISEUP_ERR_PROVIDER_NOT_AVAILABLE', 'PROVIDER_NOT_AVAILABLE');
}

// Snapshot trigger sources
if (!defined('RISEUP_SNAPSHOT_TRIGGER_MANUAL')) {
    define('RISEUP_SNAPSHOT_TRIGGER_MANUAL', 'manual');
}
if (!defined('RISEUP_SNAPSHOT_TRIGGER_CRON')) {
    define('RISEUP_SNAPSHOT_TRIGGER_CRON', 'cron');
}
if (!defined('RISEUP_SNAPSHOT_TRIGGER_API')) {
    define('RISEUP_SNAPSHOT_TRIGGER_API', 'api');
}

// WordPress options key for snapshot settings
if (!defined('RISEUP_OPTION_SNAPSHOT_SETTINGS')) {
    define('RISEUP_OPTION_SNAPSHOT_SETTINGS', 'riseup_snapshot_settings');
}

// =============================================================================
// SNAPSHOT CLEANUP CONSTANTS
// =============================================================================

// Cleanup timing
if (!defined('RISEUP_SNAPSHOT_STUCK_HOURS')) {
    define('RISEUP_SNAPSHOT_STUCK_HOURS', 24);
}

// Retention type options
if (!defined('RISEUP_RETENTION_TYPE_DAYS')) {
    define('RISEUP_RETENTION_TYPE_DAYS', 'days');
}
if (!defined('RISEUP_RETENTION_TYPE_COUNT')) {
    define('RISEUP_RETENTION_TYPE_COUNT', 'count');
}
if (!defined('RISEUP_RETENTION_TYPE_NONE')) {
    define('RISEUP_RETENTION_TYPE_NONE', 'none');
}

// Cleanup action for transaction logging
if (!defined('RISEUP_ACTION_SNAPSHOT_CLEANUP')) {
    define('RISEUP_ACTION_SNAPSHOT_CLEANUP', 'snapshot_cleanup');
}

// =============================================================================
// FILE CACHE CONSTANTS (Phase 41 - Sync System)
// =============================================================================

// File cache table name
if (!defined('RISEUP_TABLE_FILE_CACHE')) {
    define('RISEUP_TABLE_FILE_CACHE', 'file_cache');
}

// Sync manifest endpoint - fixed URL, slug passed in JSON body
if (!defined('RISEUP_ENDPOINT_SYNC_MANIFEST')) {
    define('RISEUP_ENDPOINT_SYNC_MANIFEST', 'plugins/sync-manifest');
}

// Sync push endpoint - fixed URL, slug + files in JSON body
if (!defined('RISEUP_ENDPOINT_SYNC')) {
    define('RISEUP_ENDPOINT_SYNC', 'plugins/sync');
}

// Sync file actions
if (!defined('RISEUP_SYNC_ACTION_REPLACE')) {
    define('RISEUP_SYNC_ACTION_REPLACE', 'replace');
}
if (!defined('RISEUP_SYNC_ACTION_DELETE')) {
    define('RISEUP_SYNC_ACTION_DELETE', 'delete');
}

// Sync action for transaction logging
if (!defined('RISEUP_ACTION_SYNC_DELETE')) {
    define('RISEUP_ACTION_SYNC_DELETE', 'sync_delete');
}

// =============================================================================
// SNAPSHOT SETTINGS TABLE
// =============================================================================

if (!defined('RISEUP_TABLE_SNAPSHOT_SETTINGS')) {
    define('RISEUP_TABLE_SNAPSHOT_SETTINGS', 'snapshot_settings');
}

// Full backup endpoint
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_FULL_BACKUP')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_FULL_BACKUP', 'snapshots/full-backup');
}

// Snapshot actions for orchestrator
if (!defined('RISEUP_ACTION_SNAPSHOT_FULL_BACKUP')) {
    define('RISEUP_ACTION_SNAPSHOT_FULL_BACKUP', 'snapshot_full_backup');
}

// Incremental backup endpoint (Phase 6)
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_INCREMENTAL')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_INCREMENTAL', 'snapshots/incremental');
}

// Incremental backup action for transaction logging
if (!defined('RISEUP_ACTION_SNAPSHOT_INCREMENTAL')) {
    define('RISEUP_ACTION_SNAPSHOT_INCREMENTAL', 'snapshot_incremental');
}

// Restore engine action for per-table restore (Phase 7)
if (!defined('RISEUP_ACTION_SNAPSHOT_RESTORE_PERTABLE')) {
    define('RISEUP_ACTION_SNAPSHOT_RESTORE_PERTABLE', 'snapshot_restore_pertable');
}

// Import per-table action (Phase 8)
if (!defined('RISEUP_ACTION_SNAPSHOT_IMPORT_PERTABLE')) {
    define('RISEUP_ACTION_SNAPSHOT_IMPORT_PERTABLE', 'snapshot_import_pertable');
}

// Cleanup endpoint (Phase 10)
if (!defined('RISEUP_ENDPOINT_SNAPSHOT_CLEANUP')) {
    define('RISEUP_ENDPOINT_SNAPSHOT_CLEANUP', 'snapshots/cleanup');
}

// =============================================================================
// PHP LOG RETRIEVAL ENDPOINT
// =============================================================================

// Error logs endpoint - returns error.txt and log.txt content as JSON
if (!defined('RISEUP_ENDPOINT_ERROR_LOGS')) {
    define('RISEUP_ENDPOINT_ERROR_LOGS', 'error-logs');
}

// Log retrieval settings option name
if (!defined('RISEUP_OPTION_LOG_RETRIEVAL')) {
    define('RISEUP_OPTION_LOG_RETRIEVAL', 'riseup_log_retrieval_settings');
}

// Default max lines to return from log files
if (!defined('RISEUP_LOG_RETRIEVAL_MAX_LINES')) {
    define('RISEUP_LOG_RETRIEVAL_MAX_LINES', 500);
}

// Error sessions endpoint - returns structured error entries from SQLite
if (!defined('RISEUP_ENDPOINT_ERROR_SESSIONS')) {
    define('RISEUP_ENDPOINT_ERROR_SESSIONS', 'error-sessions');
}

// =============================================================================
// UPLOAD SOURCE ENUM
// =============================================================================

if (!defined('UPLOAD_SOURCE_SCRIPT')) {
    define('UPLOAD_SOURCE_SCRIPT', 'upload_script');
}
if (!defined('UPLOAD_SOURCE_REST_API')) {
    define('UPLOAD_SOURCE_REST_API', 'rest_api');
}
if (!defined('UPLOAD_SOURCE_ADMIN_UI')) {
    define('UPLOAD_SOURCE_ADMIN_UI', 'admin_ui');
}
if (!defined('UPLOAD_SOURCE_WP_CLI')) {
    define('UPLOAD_SOURCE_WP_CLI', 'wp_cli');
}

// Valid upload sources for validation
if (!defined('UPLOAD_SOURCES_VALID')) {
    define('UPLOAD_SOURCES_VALID', json_encode(array(
        UPLOAD_SOURCE_SCRIPT,
        UPLOAD_SOURCE_REST_API,
        UPLOAD_SOURCE_ADMIN_UI,
        UPLOAD_SOURCE_WP_CLI,
    )));
}
