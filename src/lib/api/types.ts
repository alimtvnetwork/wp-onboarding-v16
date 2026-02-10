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

export interface EnvelopeErrors {
  BackendMessage: string;
  DelegatedServiceErrorStack?: string[];
  Backend?: string[];
  Frontend?: string[];
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
}

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

// Request Session Types
export interface RequestSessionRecord {
  id: string;
  method: string;
  path: string;
  query?: string;
  requestBody?: string;
  responseBody?: string;
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
