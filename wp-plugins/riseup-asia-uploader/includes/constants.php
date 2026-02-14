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
// ACTIONS — backward-compat aliases (canonical source: ActionType enum)
// =============================================================================
use RiseupAsia\Enums\ActionType;

if (!defined('ACTION_UPLOAD'))           { define('ACTION_UPLOAD',           ActionType::Upload->value); }
if (!defined('ACTION_UPLOAD_ACTIVE'))    { define('ACTION_UPLOAD_ACTIVE',    ActionType::UploadActive->value); }
if (!defined('ACTION_UPLOAD_INITIATED')) { define('ACTION_UPLOAD_INITIATED', ActionType::UploadInitiated->value); }
if (!defined('ACTION_ENABLE'))           { define('ACTION_ENABLE',           ActionType::Enable->value); }
if (!defined('ACTION_DISABLE'))          { define('ACTION_DISABLE',          ActionType::Disable->value); }
if (!defined('ACTION_DELETE'))           { define('ACTION_DELETE',           ActionType::Delete->value); }
if (!defined('ACTION_FILE_REPLACE'))     { define('ACTION_FILE_REPLACE',     ActionType::FileReplace->value); }
if (!defined('ACTION_FILE_DELETE'))      { define('ACTION_FILE_DELETE',      ActionType::FileDelete->value); }
if (!defined('ACTION_SYNC'))             { define('ACTION_SYNC',             ActionType::Sync->value); }
if (!defined('ACTION_POST_CREATE'))      { define('ACTION_POST_CREATE',      ActionType::PostCreate->value); }
if (!defined('ACTION_POST_UPDATE'))      { define('ACTION_POST_UPDATE',      ActionType::PostUpdate->value); }
if (!defined('ACTION_CATEGORY_CREATE'))  { define('ACTION_CATEGORY_CREATE',  ActionType::CategoryCreate->value); }
if (!defined('ACTION_MEDIA_UPLOAD'))     { define('ACTION_MEDIA_UPLOAD',     ActionType::MediaUpload->value); }
if (!defined('ACTION_AUTH_FAILED'))      { define('ACTION_AUTH_FAILED',      ActionType::AuthFailed->value); }
if (!defined('ACTION_EXPORT_SELF'))      { define('ACTION_EXPORT_SELF',      ActionType::ExportSelf->value); }
if (!defined('ACTION_EXPORT_PLUGIN'))    { define('ACTION_EXPORT_PLUGIN',    ActionType::ExportPlugin->value); }

// =============================================================================
// STATUS VALUES
// =============================================================================
// STATUS VALUES — backward-compat aliases (canonical source: StatusType enum)
// =============================================================================
use RiseupAsia\Enums\StatusType;

if (!defined('STATUS_SUCCESS')) { define('STATUS_SUCCESS', StatusType::Success->value); }
if (!defined('STATUS_FAILED'))  { define('STATUS_FAILED',  StatusType::Failed->value); }

// =============================================================================
// POST STATUS VALUES — backward-compat aliases (canonical source: PostStatusType enum)
// =============================================================================
use RiseupAsia\Enums\PostStatusType;

if (!defined('POST_STATUS_PUBLISH')) { define('POST_STATUS_PUBLISH', PostStatusType::Publish->value); }
if (!defined('POST_STATUS_DRAFT'))   { define('POST_STATUS_DRAFT',   PostStatusType::Draft->value); }
if (!defined('POST_STATUS_PENDING')) { define('POST_STATUS_PENDING', PostStatusType::Pending->value); }

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
// CAPABILITIES — Now in RiseupAsia\Enums\CapabilityType, kept as aliases for backward compat
// =============================================================================

if (!defined('CAP_MANAGE_PLUGINS')) {
    define('CAP_MANAGE_PLUGINS', \RiseupAsia\Enums\CapabilityType::ActivatePlugins->value);
}
if (!defined('CAP_MANAGE_POSTS')) {
    define('CAP_MANAGE_POSTS', \RiseupAsia\Enums\CapabilityType::PublishPosts->value);
}
if (!defined('CAP_UPLOAD_MEDIA')) {
    define('CAP_UPLOAD_MEDIA', \RiseupAsia\Enums\CapabilityType::UploadFiles->value);
}
if (!defined('CAP_VIEW_LOGS')) {
    define('CAP_VIEW_LOGS', \RiseupAsia\Enums\CapabilityType::ManageOptions->value);
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
// LOG LEVELS — Use LogLevelType enum (RiseupAsia\Enums\LogLevelType)
// Legacy constants removed: use LogLevelType::Info->value, etc.
// =============================================================================

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
// AUTO-UPDATE ACTIONS (for transaction logging)
// =============================================================================

if (!defined('ACTION_UPDATE_CHECK'))    { define('ACTION_UPDATE_CHECK',    ActionType::UpdateCheck->value); }
if (!defined('ACTION_UPDATE_RESOLVE'))  { define('ACTION_UPDATE_RESOLVE',  ActionType::UpdateResolve->value); }
if (!defined('ACTION_UPDATE_DOWNLOAD')) { define('ACTION_UPDATE_DOWNLOAD', ActionType::UpdateDownload->value); }
if (!defined('ACTION_UPDATE_INSTALL'))  { define('ACTION_UPDATE_INSTALL',  ActionType::UpdateInstall->value); }

// =============================================================================
// AGENT MANAGEMENT CONSTANTS
// =============================================================================

// Agent action types

// Agent action types
if (!defined('ACTION_AGENT_ADD'))            { define('ACTION_AGENT_ADD',            ActionType::AgentAdd->value); }
if (!defined('ACTION_AGENT_REMOVE'))         { define('ACTION_AGENT_REMOVE',         ActionType::AgentRemove->value); }
if (!defined('ACTION_AGENT_TEST'))           { define('ACTION_AGENT_TEST',           ActionType::AgentTest->value); }
if (!defined('ACTION_AGENT_SYNC'))           { define('ACTION_AGENT_SYNC',           ActionType::AgentSync->value); }
if (!defined('ACTION_AGENT_PLUGIN_ENABLE'))  { define('ACTION_AGENT_PLUGIN_ENABLE',  ActionType::AgentPluginEnable->value); }
if (!defined('ACTION_AGENT_PLUGIN_DISABLE')) { define('ACTION_AGENT_PLUGIN_DISABLE', ActionType::AgentPluginDisable->value); }
if (!defined('ACTION_AGENT_PLUGIN_DELETE'))  { define('ACTION_AGENT_PLUGIN_DELETE',  ActionType::AgentPluginDelete->value); }
if (!defined('ACTION_AGENT_PLUGIN_UPDATE'))  { define('ACTION_AGENT_PLUGIN_UPDATE',  ActionType::AgentPluginUpdate->value); }

// Agent status values
if (!defined('AGENT_STATUS_PENDING')) {
    define('AGENT_STATUS_PENDING', 'pending');
}
if (!defined('AGENT_STATUS_CONNECTED')) {
    define('AGENT_STATUS_CONNECTED', 'connected');
}
if (!defined('AGENT_STATUS_ERROR')) {
    define('AGENT_STATUS_ERROR', 'error');
}

// Agent REST endpoints — migrated to EndpointType enum

// =============================================================================
// TRIGGERED_BY VALUES (for enhanced transaction logging)
// =============================================================================

if (!defined('TRIGGERED_BY_API')) {
    define('TRIGGERED_BY_API', 'api');
}
if (!defined('TRIGGERED_BY_DASHBOARD')) {
    define('TRIGGERED_BY_DASHBOARD', 'dashboard');
}
if (!defined('TRIGGERED_BY_AGENT')) {
    define('TRIGGERED_BY_AGENT', 'agent_push');
}
if (!defined('TRIGGERED_BY_CRON')) {
    define('TRIGGERED_BY_CRON', 'cron');
}
if (!defined('TRIGGERED_BY_CLI')) {
    define('TRIGGERED_BY_CLI', 'cli');
}

// =============================================================================
// SNAPSHOT SYSTEM CONSTANTS
// =============================================================================

// Snapshot providers
if (!defined('SNAPSHOT_PROVIDER_WP_RESET')) {
    define('SNAPSHOT_PROVIDER_WP_RESET', 'wp_reset');
}
if (!defined('SNAPSHOT_PROVIDER_UPDRAFT')) {
    define('SNAPSHOT_PROVIDER_UPDRAFT', 'updraft');
}
if (!defined('SNAPSHOT_PROVIDER_NATIVE')) {
    define('SNAPSHOT_PROVIDER_NATIVE', 'native');
}
if (!defined('SNAPSHOT_PROVIDER_AUTO')) {
    define('SNAPSHOT_PROVIDER_AUTO', 'auto');
}

// Snapshot status values
if (!defined('SNAPSHOT_STATUS_PENDING')) {
    define('SNAPSHOT_STATUS_PENDING', 'pending');
}
if (!defined('SNAPSHOT_STATUS_SCHEDULED')) {
    define('SNAPSHOT_STATUS_SCHEDULED', 'scheduled');
}
if (!defined('SNAPSHOT_STATUS_RUNNING')) {
    define('SNAPSHOT_STATUS_RUNNING', 'running');
}
if (!defined('SNAPSHOT_STATUS_COMPLETE')) {
    define('SNAPSHOT_STATUS_COMPLETE', 'complete');
}
if (!defined('SNAPSHOT_STATUS_FAILED')) {
    define('SNAPSHOT_STATUS_FAILED', 'failed');
}

// Snapshot scope values
if (!defined('SNAPSHOT_SCOPE_ALL')) {
    define('SNAPSHOT_SCOPE_ALL', 'all');
}
if (!defined('SNAPSHOT_SCOPE_WORDPRESS')) {
    define('SNAPSHOT_SCOPE_WORDPRESS', 'wordpress');
}
if (!defined('SNAPSHOT_SCOPE_CONTENT')) {
    define('SNAPSHOT_SCOPE_CONTENT', 'content');
}
if (!defined('SNAPSHOT_SCOPE_CUSTOM')) {
    define('SNAPSHOT_SCOPE_CUSTOM', 'custom');
}

// Snapshot schedule frequencies
if (!defined('SNAPSHOT_FREQ_MANUAL')) {
    define('SNAPSHOT_FREQ_MANUAL', 'manual');
}
if (!defined('SNAPSHOT_FREQ_DAILY')) {
    define('SNAPSHOT_FREQ_DAILY', 'daily');
}
if (!defined('SNAPSHOT_FREQ_WEEKLY')) {
    define('SNAPSHOT_FREQ_WEEKLY', 'weekly');
}
if (!defined('SNAPSHOT_FREQ_MONTHLY')) {
    define('SNAPSHOT_FREQ_MONTHLY', 'monthly');
}

// Snapshot actions (backward-compat aliases — canonical source: ActionType enum)
if (!defined('ACTION_SNAPSHOT_CREATE'))  { define('ACTION_SNAPSHOT_CREATE',  ActionType::SnapshotCreate->value); }
if (!defined('ACTION_SNAPSHOT_RESTORE')) { define('ACTION_SNAPSHOT_RESTORE', ActionType::SnapshotRestore->value); }
if (!defined('ACTION_SNAPSHOT_DELETE'))  { define('ACTION_SNAPSHOT_DELETE',  ActionType::SnapshotDelete->value); }
if (!defined('ACTION_SNAPSHOT_EXPORT'))  { define('ACTION_SNAPSHOT_EXPORT',  ActionType::SnapshotExport->value); }
if (!defined('ACTION_SNAPSHOT_IMPORT'))  { define('ACTION_SNAPSHOT_IMPORT',  ActionType::SnapshotImport->value); }

// Snapshot folders — migrated to PathSubdirType::Snapshots

// Snapshot defaults
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

// Worker pool cron hook
if (!defined('CRON_SNAPSHOT_WORKER_BATCH')) {
    define('CRON_SNAPSHOT_WORKER_BATCH', 'riseup_snapshot_worker_batch');
}

// Snapshot job status values
if (!defined('SNAPSHOT_JOB_STATUS_QUEUED')) {
    define('SNAPSHOT_JOB_STATUS_QUEUED', 'queued');
}
if (!defined('SNAPSHOT_JOB_STATUS_PROCESSING')) {
    define('SNAPSHOT_JOB_STATUS_PROCESSING', 'processing');
}
if (!defined('SNAPSHOT_JOB_STATUS_COMPLETE')) {
    define('SNAPSHOT_JOB_STATUS_COMPLETE', 'complete');
}
if (!defined('SNAPSHOT_JOB_STATUS_FAILED')) {
    define('SNAPSHOT_JOB_STATUS_FAILED', 'failed');
}

// Snapshot progress REST endpoint — migrated to EndpointType enum


// Snapshot cron hooks
if (!defined('CRON_SNAPSHOT_SCHEDULED')) {
    define('CRON_SNAPSHOT_SCHEDULED', 'riseup_snapshot_scheduled');
}
if (!defined('CRON_SNAPSHOT_IMMEDIATE')) {
    define('CRON_SNAPSHOT_IMMEDIATE', 'riseup_snapshot_immediate');
}
if (!defined('CRON_SNAPSHOT_CLEANUP')) {
    define('CRON_SNAPSHOT_CLEANUP', 'riseup_snapshot_cleanup');
}
if (!defined('CRON_SNAPSHOT_RESTORE')) {
    define('CRON_SNAPSHOT_RESTORE', 'riseup_snapshot_restore');
}
if (!defined('CRON_SNAPSHOT_INCREMENTAL')) {
    define('CRON_SNAPSHOT_INCREMENTAL', 'riseup_snapshot_incremental');
}

// Snapshot error codes
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

// Snapshot trigger sources
if (!defined('SNAPSHOT_TRIGGER_MANUAL')) {
    define('SNAPSHOT_TRIGGER_MANUAL', 'manual');
}
if (!defined('SNAPSHOT_TRIGGER_CRON')) {
    define('SNAPSHOT_TRIGGER_CRON', 'cron');
}
if (!defined('SNAPSHOT_TRIGGER_API')) {
    define('SNAPSHOT_TRIGGER_API', 'api');
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

if (!defined('RETENTION_TYPE_DAYS')) {
    define('RETENTION_TYPE_DAYS', 'days');
}
if (!defined('RETENTION_TYPE_COUNT')) {
    define('RETENTION_TYPE_COUNT', 'count');
}
if (!defined('RETENTION_TYPE_NONE')) {
    define('RETENTION_TYPE_NONE', 'none');
}

if (!defined('ACTION_SNAPSHOT_CLEANUP')) { define('ACTION_SNAPSHOT_CLEANUP', ActionType::SnapshotCleanup->value); }

// =============================================================================
// FILE CACHE CONSTANTS (Phase 41 - Sync System)
// =============================================================================


if (!defined('SYNC_ACTION_REPLACE')) {
    define('SYNC_ACTION_REPLACE', 'replace');
}
if (!defined('SYNC_ACTION_DELETE')) {
    define('SYNC_ACTION_DELETE', 'delete');
}

if (!defined('ACTION_SYNC_DELETE')) { define('ACTION_SYNC_DELETE', ActionType::SyncDelete->value); }

// ENDPOINT_SNAPSHOT_FULL_BACKUP — migrated to EndpointType enum

if (!defined('ACTION_SNAPSHOT_FULL_BACKUP')) { define('ACTION_SNAPSHOT_FULL_BACKUP', ActionType::SnapshotFullBackup->value); }

// ENDPOINT_SNAPSHOT_INCREMENTAL — migrated to EndpointType enum

if (!defined('ACTION_SNAPSHOT_INCREMENTAL')) { define('ACTION_SNAPSHOT_INCREMENTAL', ActionType::SnapshotIncremental->value); }

if (!defined('SNAPSHOT_TYPE_FULL')) {
    define('SNAPSHOT_TYPE_FULL', 'full');
}
if (!defined('SNAPSHOT_TYPE_INCREMENTAL')) {
    define('SNAPSHOT_TYPE_INCREMENTAL', 'incremental');
}

if (!defined('ERR_INCREMENTAL_NO_PARENT')) {
    define('ERR_INCREMENTAL_NO_PARENT', 'INCREMENTAL_NO_PARENT');
}

if (!defined('ACTION_SNAPSHOT_RESTORE_PERTABLE')) { define('ACTION_SNAPSHOT_RESTORE_PERTABLE', ActionType::SnapshotRestorePerTable->value); }
if (!defined('ACTION_SNAPSHOT_IMPORT_PERTABLE'))  { define('ACTION_SNAPSHOT_IMPORT_PERTABLE',  ActionType::SnapshotImportPerTable->value); }

// ENDPOINT_SNAPSHOT_CLEANUP — migrated to EndpointType enum

// =============================================================================
// SNAPSHOT ZIP EXPORT SYSTEM (Feature D)
// =============================================================================

// ENDPOINT_SNAPSHOT_DOWNLOAD / DOWNLOAD_FILE — migrated to EndpointType enum

// ENDPOINT_SNAPSHOT_DOWNLOAD / DOWNLOAD_FILE — migrated to EndpointType enum

if (!defined('ACTION_SNAPSHOT_ZIP_BUILD'))    { define('ACTION_SNAPSHOT_ZIP_BUILD',    ActionType::SnapshotZipBuild->value); }
if (!defined('ACTION_SNAPSHOT_ZIP_EXPIRE'))   { define('ACTION_SNAPSHOT_ZIP_EXPIRE',   ActionType::SnapshotZipExpire->value); }
if (!defined('ACTION_SNAPSHOT_ZIP_DOWNLOAD')) { define('ACTION_SNAPSHOT_ZIP_DOWNLOAD', ActionType::SnapshotZipDownload->value); }

if (!defined('SNAPSHOT_EXPORT_STATUS_VALID')) {
    define('SNAPSHOT_EXPORT_STATUS_VALID', 'valid');
}
if (!defined('SNAPSHOT_EXPORT_STATUS_EXPIRED')) {
    define('SNAPSHOT_EXPORT_STATUS_EXPIRED', 'expired');
}
if (!defined('SNAPSHOT_EXPORT_STATUS_BUILDING')) {
    define('SNAPSHOT_EXPORT_STATUS_BUILDING', 'building');
}

// SNAPSHOT_EXPORTS_SUBDIR — migrated to PathSubdirType::Exports

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

// =============================================================================
// UPLOAD SOURCE — Now in RiseupAsia\Enums\UploadSourceType, kept as aliases for backward compat
// =============================================================================

if (!defined('UPLOAD_SOURCE_SCRIPT')) {
    define('UPLOAD_SOURCE_SCRIPT', \RiseupAsia\Enums\UploadSourceType::Script->value);
}
if (!defined('UPLOAD_SOURCE_REST_API')) {
    define('UPLOAD_SOURCE_REST_API', \RiseupAsia\Enums\UploadSourceType::RestApi->value);
}
if (!defined('UPLOAD_SOURCE_ADMIN_UI')) {
    define('UPLOAD_SOURCE_ADMIN_UI', \RiseupAsia\Enums\UploadSourceType::AdminUi->value);
}
if (!defined('UPLOAD_SOURCE_WP_CLI')) {
    define('UPLOAD_SOURCE_WP_CLI', \RiseupAsia\Enums\UploadSourceType::WpCli->value);
}

// Valid upload sources for validation
if (!defined('UPLOAD_SOURCES_VALID')) {
    define('UPLOAD_SOURCES_VALID', json_encode(\RiseupAsia\Enums\UploadSourceType::validValues()));
}
