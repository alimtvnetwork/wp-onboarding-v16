// src/lib/constants.ts — Named constants and const enums for all status/action strings.
// Spec: spec/02-typescript-standards/README.md v2.0.0
// Rule: No magic strings or magic numbers — all identifiers come from here.

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

export const ConnectionStatus = {
  Connected: "connected",
  Disconnected: "disconnected",
  Unknown: "unknown",
} as const;

export type ConnectionStatus = (typeof ConnectionStatus)[keyof typeof ConnectionStatus];

// ---------------------------------------------------------------------------
// Publish
// ---------------------------------------------------------------------------

export const PublishStatus = {
  Success: "success",
  Failed: "failed",
  Partial: "partial",
} as const;

export type PublishStatus = (typeof PublishStatus)[keyof typeof PublishStatus];

// ---------------------------------------------------------------------------
// Publish Operation (store-level status for live operations)
// ---------------------------------------------------------------------------

export const PublishOperationStatus = {
  Pending: "pending",
  Running: "running",
  Success: "success",
  Error: "error",
} as const;

export type PublishOperationStatus = (typeof PublishOperationStatus)[keyof typeof PublishOperationStatus];

// ---------------------------------------------------------------------------
// Publish Stage
// ---------------------------------------------------------------------------

export const PublishStageName = {
  Backup: "backup",
  Package: "package",
  Upload: "upload",
  Activate: "activate",
  Cleanup: "cleanup",
} as const;

export type PublishStageName = (typeof PublishStageName)[keyof typeof PublishStageName];

export const PublishStageStatus = {
  Pending: "pending",
  Running: "running",
  Success: "success",
  Error: "error",
  Skipped: "skipped",
} as const;

export type PublishStageStatus = (typeof PublishStageStatus)[keyof typeof PublishStageStatus];

// ---------------------------------------------------------------------------
// Snapshot
// ---------------------------------------------------------------------------

export const SnapshotRunStatus = {
  Pending: "pending",
  Running: "running",
  InProgress: "in_progress",
  Completed: "completed",
  Failed: "failed",
  Error: "error",
} as const;

export type SnapshotRunStatus = (typeof SnapshotRunStatus)[keyof typeof SnapshotRunStatus];

export const SnapshotExportStatus = {
  Valid: "valid",
  Expired: "expired",
  Building: "building",
} as const;

export type SnapshotExportStatus = (typeof SnapshotExportStatus)[keyof typeof SnapshotExportStatus];

// ---------------------------------------------------------------------------
// Sync Status (plugin mapping sync)
// ---------------------------------------------------------------------------

export const SyncStatus = {
  Synced: "synced",
  Ok: "ok",
  Modified: "modified",
  Pending: "pending",
  Error: "error",
  Failed: "failed",
} as const;

export type SyncStatus = (typeof SyncStatus)[keyof typeof SyncStatus];

// ---------------------------------------------------------------------------
// Deploy Status
// ---------------------------------------------------------------------------

export const DeployStatus = {
  Idle: "idle",
  Deploying: "deploying",
  Completed: "completed",
  Error: "error",
} as const;

export type DeployStatus = (typeof DeployStatus)[keyof typeof DeployStatus];

// ---------------------------------------------------------------------------
// Connection Test Steps
// ---------------------------------------------------------------------------

export const ConnectionTestStep = {
  Start: "start",
  Complete: "complete",
} as const;

export type ConnectionTestStepName = (typeof ConnectionTestStep)[keyof typeof ConnectionTestStep];

export const ConnectionTestStatus = {
  Running: "running",
  Success: "success",
  Error: "error",
} as const;

export type ConnectionTestStatus = (typeof ConnectionTestStatus)[keyof typeof ConnectionTestStatus];

// ---------------------------------------------------------------------------
// Cron Job
// ---------------------------------------------------------------------------

export const CronJobStatus = {
  Active: "active",
  Paused: "paused",
  Error: "error",
} as const;

export type CronJobStatus = (typeof CronJobStatus)[keyof typeof CronJobStatus];

export const CronLastStatus = {
  Completed: "completed",
  Failed: "failed",
  Running: "running",
} as const;

export type CronLastStatus = (typeof CronLastStatus)[keyof typeof CronLastStatus];

// ---------------------------------------------------------------------------
// Remote Plugin
// ---------------------------------------------------------------------------

export const RemotePluginStatus = {
  Active: "active",
  Inactive: "inactive",
} as const;

export type RemotePluginStatus = (typeof RemotePluginStatus)[keyof typeof RemotePluginStatus];

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

export const SessionStatus = {
  Running: "running",
  Completed: "completed",
  Error: "error",
} as const;

export type SessionStatus = (typeof SessionStatus)[keyof typeof SessionStatus];

// ---------------------------------------------------------------------------
// E2E Testing
// ---------------------------------------------------------------------------

export const E2ECaseStatusValues = {
  Pending: "pending",
  Running: "running",
  Passed: "passed",
  Failed: "failed",
  Skipped: "skipped",
} as const;

export const E2ERunStatusValues = {
  Pending: "pending",
  Running: "running",
  Completed: "completed",
  Aborted: "aborted",
  Failed: "failed",
} as const;

// ---------------------------------------------------------------------------
// File Change
// ---------------------------------------------------------------------------

export const FileChangeStatus = {
  Added: "added",
  Modified: "modified",
  Deleted: "deleted",
  Renamed: "renamed",
  Synced: "synced",
} as const;

export type FileChangeStatus = (typeof FileChangeStatus)[keyof typeof FileChangeStatus];

export const FileDirection = {
  LocalNewer: "local_newer",
  RemoteNewer: "remote_newer",
  LocalOnly: "local_only",
  RemoteOnly: "remote_only",
} as const;

export type FileDirection = (typeof FileDirection)[keyof typeof FileDirection];

// ---------------------------------------------------------------------------
// Activity Feed
// ---------------------------------------------------------------------------

export const ActivityTypeValues = {
  Publish: "publish",
  Snapshot: "snapshot",
  Plugin: "plugin",
  Config: "config",
  Connection: "connection",
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
  Healthy: "healthy",
  Degraded: "degraded",
  Down: "down",
  Unknown: "unknown",
} as const;

// ---------------------------------------------------------------------------
// Snapshot Scope & Type
// ---------------------------------------------------------------------------

export const SnapshotScopeValues = {
  All: "all",
  Wordpress: "wordpress",
  Content: "content",
  Custom: "custom",
} as const;

export const SnapshotTypeValues = {
  Full: "full",
  Incremental: "incremental",
} as const;

// ---------------------------------------------------------------------------
// Storage Mode
// ---------------------------------------------------------------------------

export const StorageModeValues = {
  Single: "single",
  PerTable: "per-table",
} as const;

// ---------------------------------------------------------------------------
// Publish Action Types (for formatActionLabel / getActionBadgeClasses)
// ---------------------------------------------------------------------------

export const PublishActionType = {
  PluginDisable: "PLUGIN_DISABLE",
  PluginEnable: "PLUGIN_ENABLE",
  PluginDelete: "PLUGIN_DELETE",
  UploadScript: "UPLOAD_SCRIPT",
  Publish: "PUBLISH",
  Sync: "SYNC",
  Backup: "BACKUP",
  Restore: "RESTORE",
  SnapshotCreate: "SNAPSHOT_CREATE",
  SnapshotRestore: "SNAPSHOT_RESTORE",
  SnapshotDelete: "SNAPSHOT_DELETE",
  SnapshotExport: "SNAPSHOT_EXPORT",
  SnapshotImport: "SNAPSHOT_IMPORT",
  SnapshotCleanup: "SNAPSHOT_CLEANUP",
  SnapshotFullBackup: "SNAPSHOT_FULL_BACKUP",
  SnapshotIncremental: "SNAPSHOT_INCREMENTAL",
  SnapshotRestorePerTable: "SNAPSHOT_RESTORE_PERTABLE",
  SnapshotImportPerTable: "SNAPSHOT_IMPORT_PERTABLE",
} as const;

export type PublishActionType = (typeof PublishActionType)[keyof typeof PublishActionType];

// ---------------------------------------------------------------------------
// Log / Diagnostic Levels
// ---------------------------------------------------------------------------

export const LogLevel = {
  Debug: "debug",
  Info: "info",
  Warn: "warn",
  Error: "error",
} as const;

export type LogLevel = (typeof LogLevel)[keyof typeof LogLevel];

// ---------------------------------------------------------------------------
// Session Type Labels
// ---------------------------------------------------------------------------

export const SessionType = {
  Publish: "publish",
  Sync: "sync",
  Connect: "connect",
  Backup: "backup",
  BulkPublish: "bulk_publish",
  RemotePluginAction: "remote_plugin_action",
} as const;

export type SessionType = (typeof SessionType)[keyof typeof SessionType];
