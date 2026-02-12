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
