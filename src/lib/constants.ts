// src/lib/constants.ts — Named constants and const enums for all status/action strings.
// Spec: spec/02-typescript-standards/README.md v2.0.0
// Rule: No magic strings or magic numbers — all identifiers come from here.
// Phase 3: All enum string values use PascalCase to match Go/PHP backend enums.

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

export const ConnectionStatus = {
  Connected: "Connected",
  Disconnected: "Disconnected",
  Unknown: "Unknown",
} as const;

export type ConnectionStatus = (typeof ConnectionStatus)[keyof typeof ConnectionStatus];

// ---------------------------------------------------------------------------
// Publish
// ---------------------------------------------------------------------------

export const PublishStatus = {
  Success: "Success",
  Failed: "Failed",
  Partial: "Partial",
} as const;

export type PublishStatus = (typeof PublishStatus)[keyof typeof PublishStatus];

// ---------------------------------------------------------------------------
// Publish Operation (store-level status for live operations)
// ---------------------------------------------------------------------------

export const PublishOperationStatus = {
  Pending: "Pending",
  Running: "Running",
  Success: "Success",
  Error: "Error",
} as const;

export type PublishOperationStatus = (typeof PublishOperationStatus)[keyof typeof PublishOperationStatus];

// ---------------------------------------------------------------------------
// Publish Stage
// ---------------------------------------------------------------------------

export const PublishStageName = {
  Backup: "Backup",
  Package: "Package",
  Upload: "Upload",
  Activate: "Activate",
  Cleanup: "Cleanup",
} as const;

export type PublishStageName = (typeof PublishStageName)[keyof typeof PublishStageName];

export const PublishStageStatus = {
  Pending: "Pending",
  Running: "Running",
  Success: "Success",
  Error: "Error",
  Skipped: "Skipped",
} as const;

export type PublishStageStatus = (typeof PublishStageStatus)[keyof typeof PublishStageStatus];

// ---------------------------------------------------------------------------
// Snapshot
// ---------------------------------------------------------------------------

export const SnapshotRunStatus = {
  Pending: "Pending",
  Running: "Running",
  InProgress: "InProgress",
  Completed: "Completed",
  Failed: "Failed",
  Error: "Error",
} as const;

export type SnapshotRunStatus = (typeof SnapshotRunStatus)[keyof typeof SnapshotRunStatus];

export const SnapshotExportStatus = {
  Valid: "Valid",
  Expired: "Expired",
  Building: "Building",
} as const;

export type SnapshotExportStatus = (typeof SnapshotExportStatus)[keyof typeof SnapshotExportStatus];

// ---------------------------------------------------------------------------
// Sync Status (plugin mapping sync)
// ---------------------------------------------------------------------------

export const SyncStatus = {
  Synced: "Synced",
  Ok: "Ok",
  Modified: "Modified",
  Pending: "Pending",
  Error: "Error",
  Failed: "Failed",
} as const;

export type SyncStatus = (typeof SyncStatus)[keyof typeof SyncStatus];

// ---------------------------------------------------------------------------
// Deploy Status
// ---------------------------------------------------------------------------

export const DeployStatus = {
  Idle: "Idle",
  Deploying: "Deploying",
  Completed: "Completed",
  Error: "Error",
} as const;

export type DeployStatus = (typeof DeployStatus)[keyof typeof DeployStatus];

// ---------------------------------------------------------------------------
// Connection Test Steps
// ---------------------------------------------------------------------------

export const ConnectionTestStep = {
  Start: "Start",
  Complete: "Complete",
} as const;

export type ConnectionTestStepName = (typeof ConnectionTestStep)[keyof typeof ConnectionTestStep];

export const ConnectionTestStatus = {
  Running: "Running",
  Success: "Success",
  Error: "Error",
} as const;

export type ConnectionTestStatus = (typeof ConnectionTestStatus)[keyof typeof ConnectionTestStatus];

// ---------------------------------------------------------------------------
// Cron Job
// ---------------------------------------------------------------------------

export const CronJobStatus = {
  Active: "Active",
  Paused: "Paused",
  Error: "Error",
} as const;

export type CronJobStatus = (typeof CronJobStatus)[keyof typeof CronJobStatus];

export const CronLastStatus = {
  Completed: "Completed",
  Failed: "Failed",
  Running: "Running",
} as const;

export type CronLastStatus = (typeof CronLastStatus)[keyof typeof CronLastStatus];

// ---------------------------------------------------------------------------
// Remote Plugin
// ---------------------------------------------------------------------------

export const RemotePluginStatus = {
  Active: "Active",
  Inactive: "Inactive",
} as const;

export type RemotePluginStatus = (typeof RemotePluginStatus)[keyof typeof RemotePluginStatus];

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

export const SessionStatus = {
  Running: "Running",
  Completed: "Completed",
  Error: "Error",
} as const;

export type SessionStatus = (typeof SessionStatus)[keyof typeof SessionStatus];

// ---------------------------------------------------------------------------
// E2E Testing
// ---------------------------------------------------------------------------

export const E2ECaseStatusValues = {
  Pending: "Pending",
  Running: "Running",
  Passed: "Passed",
  Failed: "Failed",
  Skipped: "Skipped",
} as const;

export const E2ERunStatusValues = {
  Pending: "Pending",
  Running: "Running",
  Completed: "Completed",
  Aborted: "Aborted",
  Failed: "Failed",
} as const;

// ---------------------------------------------------------------------------
// File Change
// ---------------------------------------------------------------------------

export const FileChangeStatus = {
  Added: "Added",
  Modified: "Modified",
  Deleted: "Deleted",
  Renamed: "Renamed",
  Synced: "Synced",
} as const;

export type FileChangeStatus = (typeof FileChangeStatus)[keyof typeof FileChangeStatus];

export const FileDirection = {
  LocalNewer: "LocalNewer",
  RemoteNewer: "RemoteNewer",
  LocalOnly: "LocalOnly",
  RemoteOnly: "RemoteOnly",
} as const;

export type FileDirection = (typeof FileDirection)[keyof typeof FileDirection];

// ---------------------------------------------------------------------------
// Activity Feed
// ---------------------------------------------------------------------------

export const ActivityTypeValues = {
  Publish: "Publish",
  Snapshot: "Snapshot",
  Plugin: "Plugin",
  Config: "Config",
  Connection: "Connection",
} as const;

// ---------------------------------------------------------------------------
// Timing Constants (milliseconds)
// ---------------------------------------------------------------------------

export const STALE_TIME_DEFAULT_MS = 60_000 as const;
export const STALE_TIME_SHORT_MS = 30_000 as const;
export const POLL_INTERVAL_DASHBOARD_MS = 30_000 as const;
export const POLL_INTERVAL_RUNNING_SNAPSHOT_MS = 5_000 as const;
export const SEVEN_DAYS_MS = 604_800_000 as const;
export const ONE_DAY_MS = 86_400_000 as const;
export const DASHBOARD_TREND_DAYS = 7 as const;
export const DASHBOARD_TREND_LIMIT = 200 as const;
export const RECENT_PUBLISHES_LIMIT = 5 as const;
export const RECENT_ERRORS_LIMIT = 10 as const;
export const PUBLISH_COOLDOWN_MS = 30_000 as const;
export const PUBLISH_LOG_MAX = 500 as const;
export const CLEANUP_DELAY_MS = 1_800_000 as const;

// ---------------------------------------------------------------------------
// Site Health
// ---------------------------------------------------------------------------

export const SiteHealthStatusValues = {
  Healthy: "Healthy",
  Degraded: "Degraded",
  Down: "Down",
  Unknown: "Unknown",
} as const;

// ---------------------------------------------------------------------------
// Snapshot Scope & Type
// ---------------------------------------------------------------------------

export const SnapshotScopeValues = {
  All: "All",
  Wordpress: "Wordpress",
  Content: "Content",
  Custom: "Custom",
} as const;

export const SnapshotTypeValues = {
  Full: "Full",
  Incremental: "Incremental",
} as const;

// ---------------------------------------------------------------------------
// Storage Mode
// ---------------------------------------------------------------------------

export const StorageModeValues = {
  Single: "Single",
  PerTable: "PerTable",
} as const;

// ---------------------------------------------------------------------------
// Publish Action Types (for formatActionLabel / getActionBadgeClasses)
// ---------------------------------------------------------------------------

export const PublishActionType = {
  PluginDisable: "PluginDisable",
  PluginEnable: "PluginEnable",
  PluginDelete: "PluginDelete",
  UploadScript: "UploadScript",
  Publish: "Publish",
  Sync: "Sync",
  Backup: "Backup",
  Restore: "Restore",
  SnapshotCreate: "SnapshotCreate",
  SnapshotRestore: "SnapshotRestore",
  SnapshotDelete: "SnapshotDelete",
  SnapshotExport: "SnapshotExport",
  SnapshotImport: "SnapshotImport",
  SnapshotCleanup: "SnapshotCleanup",
  SnapshotFullBackup: "SnapshotFullBackup",
  SnapshotIncremental: "SnapshotIncremental",
  SnapshotRestorePerTable: "SnapshotRestorePerTable",
  SnapshotImportPerTable: "SnapshotImportPerTable",
} as const;

export type PublishActionType = (typeof PublishActionType)[keyof typeof PublishActionType];

// ---------------------------------------------------------------------------
// Log / Diagnostic Levels
// ---------------------------------------------------------------------------

export const LogLevel = {
  Debug: "Debug",
  Info: "Info",
  Warn: "Warn",
  Error: "Error",
} as const;

export type LogLevel = (typeof LogLevel)[keyof typeof LogLevel];

// ---------------------------------------------------------------------------
// Session Type Labels
// ---------------------------------------------------------------------------

export const SessionType = {
  Publish: "Publish",
  Sync: "Sync",
  Connect: "Connect",
  Backup: "Backup",
  BulkPublish: "BulkPublish",
  RemotePluginAction: "RemotePluginAction",
} as const;

export type SessionType = (typeof SessionType)[keyof typeof SessionType];

// ---------------------------------------------------------------------------
// Response Message Type (mirrors Go/PHP ResponseMessageType enum)
// ---------------------------------------------------------------------------

export const ResponseMessageType = {
  Success: "Operation completed successfully",
  Unauthorized: "Authentication required",
  Forbidden: "Insufficient permissions",
  InvalidRequest: "Invalid request data",
  PluginNotFound: "Plugin not found",
  UploadFailed: "Upload failed",
  ActivationFailed: "Plugin activation failed",
  DeactivationFailed: "Plugin deactivation failed",
  DeleteFailed: "Plugin deletion failed",
  PostCreateFailed: "Post creation failed",
  PostUpdateFailed: "Post update failed",
  CategoryCreateFailed: "Category creation failed",
  MediaUploadFailed: "Media upload failed",
  DbError: "Database error",
  FileIgnored: "File ignored by .uploadignore",
  InvalidRequestBody: "Invalid request body",
  ServiceNotAvailable: "Service not available",
  InvalidId: "Invalid ID",
} as const;

export type ResponseMessageType = (typeof ResponseMessageType)[keyof typeof ResponseMessageType];

// ---------------------------------------------------------------------------
// Response Key Type (mirrors Go/PHP ResponseKeyType enum)
// ---------------------------------------------------------------------------

export const ResponseKeyType = {
  // Envelope keys
  Success: "success",
  Error: "error",
  Message: "message",
  Data: "data",
  Code: "code",
  Valid: "valid",
  Errors: "errors",
  Cached: "cached",
  Phase: "phase",
  Reason: "reason",

  // Domain collection keys
  Total: "total",
  Agents: "agents",
  Actions: "actions",
  Logs: "logs",
  Snapshots: "snapshots",
  Sql: "sql",
  Params: "params",
  Sets: "sets",
  Plugins: "plugins",
  Tables: "tables",

  // File and size keys
  Rows: "rows",
  Bytes: "bytes",
  Size: "size",
  FileSize: "file_size",
  Path: "path",
  Filename: "filename",
  Checksum: "checksum",
  Duration: "duration",
  Count: "count",
  Files: "files",
  Directory: "directory",
  Scope: "scope",
  Exported: "exported",
  Entry: "entry",
  Computed: "computed",
  Removed: "removed",

  // Snapshot-domain keys
  SnapshotId: "snapshot_id",
  Sequence: "sequence",
  FolderName: "folder_name",
  TablesChanged: "tables_changed",
  TotalRows: "total_rows",
  TotalNewRows: "total_new_rows",
  ZipSize: "zip_size",
  BackupId: "backup_id",
  ZipFailed: "zip_failed",
  SkipAudit: "skip_audit",
  TablesRestored: "tables_restored",
} as const;

export type ResponseKeyType = (typeof ResponseKeyType)[keyof typeof ResponseKeyType];

// ---------------------------------------------------------------------------
// HTTP Status Type (mirrors Go/PHP HttpStatusType enum)
// ---------------------------------------------------------------------------

export const HttpStatusType = {
  Ok: 200,
  Created: 201,
  NoContent: 204,
  BadRequest: 400,
  Unauthorized: 401,
  Forbidden: 403,
  NotFound: 404,
  RequestTimeout: 408,
  Conflict: 409,
  TooManyRequests: 429,
  ServerError: 500,
  NotImplemented: 501,
  BadGateway: 502,
  ServiceUnavailable: 503,
  GatewayTimeout: 504,
} as const;

export type HttpStatusType = (typeof HttpStatusType)[keyof typeof HttpStatusType];
