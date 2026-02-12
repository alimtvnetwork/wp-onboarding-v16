// API types — all interfaces and type aliases for the WP Plugin Publish API.
//
// Envelope Schema: spec/response-envelope/envelope.schema.json v1.0.0
// See spec/response-envelope/README.md for full specification.

// ---------------------------------------------------------------------------
// Universal Response Envelope types (PascalCase, matches Go backend)
// ---------------------------------------------------------------------------

export interface EnvelopeStatus {
  IsSuccess: boolean;
  IsFailed: boolean;
  Code: number;
  Message: string;
  Timestamp: string;
}

export interface EnvelopeAttributes {
  RequestedAt?: string;
  RequestDelegatedAt?: string;
  SessionId?: string;
  HasAnyErrors: boolean;
  IsSingle: boolean;
  IsMultiple: boolean;
  IsEmpty?: boolean;
  TotalRecords?: number;
  PerPage?: number;
  TotalPages?: number;
  CurrentPage?: number;
}

export interface EnvelopeNavigation {
  NextPage: string | null;
  PrevPage: string | null;
  CloserLinks: string[];
}

export interface DelegatedRequestServer {
  DelegatedEndpoint: string;
  Method: string;
  StatusCode: number;
  RequestBody?: unknown;
  Response?: unknown;
  StackTrace?: string[];
  AdditionalMessages?: string;
}

export interface EnvelopeErrors {
  BackendMessage: string;
  DelegatedServiceErrorStack?: string[];
  Backend?: string[];
  Frontend?: string[];
  DelegatedRequestServer?: DelegatedRequestServer;
}

export interface EnvelopeMethodFrame {
  Method: string;
  File: string;
  LineNumber: number;
}

export interface EnvelopeMethodsStack {
  Backend: EnvelopeMethodFrame[];
  Frontend: EnvelopeMethodFrame[];
}

/** Metadata preserved from the envelope for downstream use (pagination, diagnostics) */
export interface EnvelopeMeta {
  attributes: EnvelopeAttributes;
  navigation?: EnvelopeNavigation;
  errors?: EnvelopeErrors;
  methodsStack?: EnvelopeMethodsStack;
}

// ---------------------------------------------------------------------------
// Core API types
// ---------------------------------------------------------------------------

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ApiError;
  /** Full envelope metadata when response came from the PascalCase envelope format */
  envelope?: EnvelopeMeta;
}

export interface ApiError {
  code: string;
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  file?: string;
  line?: number;
  function?: string;
  stackTrace?: string;
  timestamp: string;
}

export type ApiMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

export type ApiCallMeta = {
  endpoint: string; // e.g. "/plugins"
  method?: ApiMethod;
  requestBody?: unknown;
};

// ---------------------------------------------------------------------------
// Domain types
// ---------------------------------------------------------------------------

export interface Site {
  id: number;
  name: string;
  url: string;
  username: string;
  category: string | null;
  connectionStatus: "connected" | "disconnected" | "unknown";
  lastTestedAt: string | null;
  lastSyncAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface Plugin {
  id: number;
  name: string;
  path: string;
  version?: string;
  pinned?: boolean;
  category: string | null;
  watchEnabled: boolean;
  autoPublish: boolean;
  excludePatterns: string[];
  fileCount: number;
  modifiedCount: number;
  gitEnabled?: boolean;
  gitRemoteUrl?: string;
  buildCommand?: string;
  mappings: PluginMapping[];
  lastScannedAt?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface PluginMapping {
  id: number;
  pluginId: number;
  siteId: number;
  siteName: string;
  siteUrl: string;
  remoteSlug: string;
  syncStatus: string;
  lastSyncAt: string | null;
  lastBackupAt: string | null;
}

export interface PluginVersion {
  id: number;
  pluginId: number;
  siteId: number;
  siteName: string;
  version: string;
  backupPath: string;
  filesUpdated: number;
  gitCommitHash: string;
  publishType: string;
  status: string;
  notes: string;
  createdAt: string;
}

export interface FileChange {
  path: string;
  status: "added" | "modified" | "deleted" | "renamed" | "synced";
  localHash?: string;
  remoteHash?: string;
  localModifiedAt?: string;
  remoteModifiedAt?: string;
  localSize?: number;
  remoteSize?: number;
  direction?: "local_newer" | "remote_newer" | "local_only" | "remote_only";
  stats?: {
    additions: number;
    deletions: number;
  };
}

export interface SyncResult {
  pluginId: number;
  siteId: number;
  siteName?: string;
  inSync: boolean;
  localFiles: number;
  remoteFiles: number;
  added: number;
  modified: number;
  deleted: number;
  changes: FileChange[];
  checkedAt: string;
  errorMessage?: string;
}

export interface Backup {
  id: number;
  pluginMappingId: number;
  filePath: string;
  fileSize: number;
  pluginVersion?: string;
  createdAt: string;
}

export interface RemotePlugin {
  plugin: string;
  slug: string;
  name: string;
  version: string;
  status: "active" | "inactive";
  author: string;
  description: string;
  pluginUri: string;
  textDomain: string;
}

export interface RemotePluginFile {
  path: string;
  hash: string;
  size: number;
  modifiedAt?: string;
}

export interface RemotePluginFilesResult {
  pluginSlug: string;
  totalFiles: number;
  files: RemotePluginFile[];
}

export interface ErrorLog {
  id: number;
  code: string;
  level: string;
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  file?: string;
  line?: number;
  function?: string;
  stackTrace?: string;
  createdAt: string;
}

export interface SessionSummary {
  sessionId: string;
  type: string;
  pluginId?: number;
  pluginName?: string;
  siteId?: number;
  siteName?: string;
  status: "running" | "completed" | "error";
  startedAt: string;
  endedAt?: string;
}

export interface SessionInfo extends SessionSummary {
  errorMsg?: string;
  metadata?: Record<string, unknown>;
}

export interface SessionStackFrame {
  function: string;
  file?: string;
  line?: number;
  class?: string;
}

export interface SessionDiagnostics {
  request?: {
    url: string;
    method: string;
    headers?: Record<string, string>;
    body?: Record<string, unknown>;
  };
  response?: {
    requestUrl: string;
    responseUrl: string;
    statusCode: number;
    headers?: Record<string, string>;
    body?: unknown;
  };
  stackTrace?: {
    golang?: SessionStackFrame[];
    php?: SessionStackFrame[];
  };
  phpStackTraceLog?: string;
}

export interface FilePreview {
  path: string;
  changeType: "added" | "modified" | "deleted";
  size: number;
  localHash?: string;
}

export interface PublishPreview {
  pluginId: number;
  pluginName: string;
  localVersion: string;
  remoteVersion: string;
  siteId: number;
  siteName: string;
  siteUrl: string;
  remoteSlug: string;
  totalFiles: number;
  totalSize: number;
  added: number;
  modified: number;
  deleted: number;
  files: FilePreview[];
}

export interface Settings {
  meta?: {
    seedVersion: string;
    currentVersion: string;
    lastSeededAt?: string;
  };
  watcher: {
    pollIntervalMs: number;
    debounceMs: number;
    defaultExcludePatterns: string[];
  };
  backup: {
    autoBackupBeforePublish: boolean;
    retentionDays: number;
    maxBackupsPerPlugin: number;
    location: string;
  };
  logging: {
    level: string;
    retentionDays: number;
    debugMode: boolean;
    frontendDebugMode?: boolean;
    showZipFileTreeInLogs?: boolean;
    retryMaxAttempts?: number;
    retryInitialDelayMs?: number;
    circuitBreakerThreshold?: number;
    circuitBreakerCooldownMs?: number;
    stackTraceDepth?: number;
    phpStackTraceDepth?: number;
  };
  appearance: {
    theme: string;
    accentColor: string;
    fontSize: string;
    borderRadius: string;
    compactMode: boolean;
    animationsEnabled: boolean;
    sidebarTheme?: string;
  };
  publish?: {
    uploaderHelperPath?: string;
  };
  server: {
    port: number;
    wsReconnectDelayMs: number;
  };
  responseDebug?: {
    includeErrors: boolean;
    includeStackTrace: boolean;
    includeMethodsStack: boolean;
    maxStackFrames?: number;
  };
  pagination?: {
    defaultPerPage: number;
  };
  snapshots?: {
    enabled: boolean;
    schedules: SnapshotSchedule[];
    storageMode: "single" | "per-table";
    workerCount: number;
    batchSize: number;
  };
}

export interface SnapshotSchedule {
  id: string;
  interval: SnapshotInterval;
  enabled: boolean;
}

export type SnapshotInterval =
  | "hourly"
  | "3h"
  | "6h"
  | "12h"
  | "daily"
  | "weekly"
  | "monthly"
  | "yearly";

// Error History Types
export interface ErrorHistoryInput {
  errorId?: string;
  code: string;
  level: string;
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  stackTrace?: string;
  endpoint?: string;
  method?: string;
  requestBody?: Record<string, unknown>;
  responseStatus?: number;
  sessionId?: string;
  sessionType?: string;
  phpStackFrames?: Array<{ file?: string; fileBase?: string; line?: number; function?: string; class?: string }>;
  backendLogs?: string[];
  backendStackTrace?: string;
  siteUrl?: string;
  triggerComponent?: string;
  triggerAction?: string;
  invocationChain?: string[];
  uiClickPath?: string;
  markdownReport?: string;
}

export interface ErrorHistoryRecord {
  id: number;
  errorId: string;
  code: string;
  level: string;
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  stackTrace?: string;
  endpoint?: string;
  method?: string;
  requestBody?: Record<string, unknown>;
  responseStatus?: number;
  sessionId?: string;
  sessionType?: string;
  phpStackFrames?: Array<{ file?: string; fileBase?: string; line?: number; function?: string; class?: string }>;
  backendLogs?: string[];
  backendStackTrace?: string;
  siteUrl?: string;
  triggerComponent?: string;
  triggerAction?: string;
  invocationChain?: string[];
  uiClickPath?: string;
  markdownReport?: string;
  createdAt: string;
}

export interface ErrorHistoryListResponse {
  errors: ErrorHistoryRecord[];
  total: number;
  limit: number;
  offset: number;
}

export interface ErrorHistoryStats {
  total: number;
  byLevel: Record<string, number>;
  byCode: Record<string, number>;
}

// Snapshot Types
export type SnapshotScope = "all" | "wordpress" | "content" | "custom";
export type SnapshotType = "full" | "incremental";

export interface CreateSnapshotOptions {
  name?: string;
  scope?: SnapshotScope;
  snapshotType?: SnapshotType;
  parentId?: number;
  tables?: string[];
  workerCount?: number;
}

export interface SnapshotOperationResult {
  id?: number;
  status: string;
  message?: string;
  snapshotId?: number;
  filename?: string;
}

export interface RestoreSnapshotOptions {
  confirm?: boolean;
  mode?: "full" | "selective";
  tables?: string[];
  preBackup?: boolean;
}

export interface CleanupSnapshotOptions {
  dryRun?: boolean;
  maxAgeDays?: number;
  maxCount?: number;
}

export interface CleanupSnapshotResult {
  deleted?: number;
  dryRun?: boolean;
  candidates?: string[];
  retention?: { deleted: number; expired: number };
  orphans?: { removed: number };
  stuck?: { cleaned: number };
}

export interface SnapshotImportResult {
  id: number;
  filename: string;
  tables: number;
  totalRows: number;
}

export interface SnapshotRecord {
  id: number;
  sequence: number;
  filename: string;
  scope: string;
  provider: string;
  status: string;
  file_size: number;
  total_rows: number;
  tables: string;
  created_at: string;
  error?: string;
  /** 'full' | 'incremental' — derived from scope or tables_json metadata */
  snapshot_type?: SnapshotType;
  /** For incrementals: the parent full snapshot's ID */
  parent_id?: number;
  /** For incrementals: the master directory name */
  parent_dir?: string;
  /** For full snapshots: count of child incrementals */
  incremental_count?: number;
}

export interface SnapshotSettings {
  provider: string;
  schedule: string;
  schedule_time?: string;
  schedule_day?: string;
  scope: string;
  retention_type: string;
  retention_days?: number;
  retention_max?: number;
  pre_restore_backup: boolean;
  batch_size?: number;
  // Multi-schedule & parallel execution (synced with global settings)
  schedules?: SnapshotSchedule[];
  storage_mode?: "single" | "per-table";
  worker_count?: number;
}

export interface SnapshotProviderInfo {
  id: string;
  name: string;
  available: boolean;
  priority: number;
}

export interface AvailableTable {
  name: string;
  rows: number;
  size: number;
  is_core: boolean;
}

// Publish History types
export interface PublishHistoryEntry {
  id: number;
  pluginId: number;
  pluginName: string;
  siteId: number;
  siteName: string;
  siteUrl: string;
  sessionId?: string;
  status: "success" | "failed" | "partial";
  mode: string;
  actionType?: string;
  version?: string;
  newVersion?: string;
  isSelfUpdate?: boolean;
  machineName?: string;
  filesUpdated: number;
  activationStatus: string;
  rollbackStatus?: string;
  rollbackMessage?: string;
  errorMessage?: string;
  durationMs: number;
  createdAt: string;
}

export interface PublishHistoryStats {
  totalPublishes: number;
  successCount: number;
  failureCount: number;
  partialCount: number;
  avgDurationMs: number;
  totalFilesUpdated: number;
  lastPublishAt?: string;
}

// Snapshot Cron Job Types
export interface SnapshotCronJob {
  id: string;
  scheduleId: string;
  interval: SnapshotInterval;
  cronExpression: string;
  enabled: boolean;
  status: "active" | "paused" | "error";
  lastRunAt?: string;
  nextRunAt?: string;
  lastStatus?: "completed" | "failed" | "running";
  lastError?: string;
  runCount: number;
}

export interface SnapshotCronSyncResult {
  created: number;
  updated: number;
  removed: number;
  active: SnapshotCronJob[];
}

// Site Health Types
// NOTE: Canonical site health types live in src/types/siteHealth.ts
// The API methods use those types via the hook layer.
// These are lightweight types for the raw API response shape.
export type SiteHealthStatus = "healthy" | "degraded" | "down" | "unknown";

export interface SiteHealthCheckResult {
  siteId: number;
  status: string;
  responseMs?: number;
  checkedAt: string;
  error?: string;
}

// E2E Testing Types
export type E2ECaseStatus = "pending" | "running" | "passed" | "failed" | "skipped";
export type E2ERunStatus = "pending" | "running" | "completed" | "aborted" | "failed";

export interface E2ESuite {
  id: string;
  name: string;
  description?: string;
  caseCount: number;
}

export interface E2ECase {
  id: string;
  suiteId: string;
  name: string;
  status: E2ECaseStatus;
  duration?: number;
  error?: string;
}

export interface E2ETestResult {
  id: string;
  runId: string;
  suiteId: string;
  caseId: string;
  caseName: string;
  suiteName?: string;
  status: E2ECaseStatus | "error";
  durationMs: number;
  duration?: number;
  error?: string;
  errorMessage?: string;
  errorDetails?: string;
  requestData?: string;
  responseData?: string;
  logs?: string;
}

export interface E2ERun {
  id: string;
  runId?: string;
  startedAt: string;
  completedAt?: string;
  status: E2ERunStatus;
  totalTests: number;
  passedTests: number;
  failedTests: number;
  skippedTests: number;
  durationMs: number;
  passed?: number;
  failed?: number;
  skipped?: number;
  endedAt?: string;
  results?: E2ETestResult[];
}

export interface E2ERunSummary {
  run: E2ERun;
  results: E2ETestResult[];
}

// Activity Feed Types
export type ActivityType = "publish" | "snapshot" | "plugin" | "config" | "connection";

// ---------------------------------------------------------------------------
// Per-type metadata interfaces (discriminated by ActivityEntry.type)
// ---------------------------------------------------------------------------

export interface PublishMetadata {
  pluginName?: string;
  version?: string;
  filesUpdated?: number;
  from?: string;
  to?: string;
  isSelfUpdate?: boolean;
}

export interface SnapshotMetadata {
  snapshotType?: SnapshotType;
  snapshotId?: number;
  tables?: number | string[];
  size?: number;
  deltaTables?: number;
  mode?: string;
  cascadeCount?: number;
  cached?: boolean;
}

export interface PluginMetadata {
  pluginSlug?: string;
  pluginName?: string;
}

export interface ConfigMetadata {
  setting?: string;
  value?: string | number | boolean;
  time?: string;
}

export interface ConnectionMetadata {
  wpVersion?: string;
  reason?: string;
}

/** Union of all known metadata shapes */
export type ActivityMetadata =
  | PublishMetadata
  | SnapshotMetadata
  | PluginMetadata
  | ConfigMetadata
  | ConnectionMetadata;

// ---------------------------------------------------------------------------
// ActivityEntry — discriminated union on `type`
// ---------------------------------------------------------------------------

interface ActivityEntryBase {
  id: string;
  timestamp: string;
  siteId: number;
  siteName: string;
  action: string;
  title: string;
  source: "go" | "wordpress";
  machineName?: string;
  version?: string;
}

export interface PublishActivityEntry extends ActivityEntryBase {
  type: "publish";
  metadata: PublishMetadata;
}

export interface SnapshotActivityEntry extends ActivityEntryBase {
  type: "snapshot";
  metadata: SnapshotMetadata;
}

export interface PluginActivityEntry extends ActivityEntryBase {
  type: "plugin";
  metadata: PluginMetadata;
}

export interface ConfigActivityEntry extends ActivityEntryBase {
  type: "config";
  metadata: ConfigMetadata;
}

export interface ConnectionActivityEntry extends ActivityEntryBase {
  type: "connection";
  metadata: ConnectionMetadata;
}

export type ActivityEntry =
  | PublishActivityEntry
  | SnapshotActivityEntry
  | PluginActivityEntry
  | ConfigActivityEntry
  | ConnectionActivityEntry;

export interface ActivityFeedResponse {
  entries: ActivityEntry[];
  total: number;
  limit: number;
  offset: number;
}

export interface ActivityFeedParams {
  limit?: number;
  offset?: number;
  siteId?: number;
  type?: ActivityType;
  from?: string;
  to?: string;
  search?: string;
}

// Request Session Types
export interface RequestSessionRecord {
  id: string;
  title?: string;
  method: string;
  path: string;
  query?: string;
  requestBody?: unknown;
  responseBody?: unknown;
  statusCode: number;
  startedAt: string;
  endedAt: string;
  durationMs: number;
  error?: string;
  logs?: Array<{ timestamp: string; level: string; message: string; details?: Record<string, unknown> }>;
  headers?: Record<string, string>;
}

export interface RequestSessionListResponse {
  sessions: RequestSessionRecord[];
  total: number;
  limit?: number;
  offset?: number;
}
