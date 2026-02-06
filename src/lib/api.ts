// API client for WP Plugin Publish backend

import { resolveApiBase, resolveApiOrigin, resolveApiUrl, toAbsoluteUrl } from "@/lib/endpoints";
import { logger } from "@/lib/logger";
import { withCircuitBreaker } from "@/lib/circuitBreaker";

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ApiError;
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

export class ApiClientError extends Error {
  readonly apiError: ApiError;
  readonly meta: Required<Pick<ApiCallMeta, "endpoint">> & {
    method?: ApiMethod;
    requestBody?: unknown;
    apiOrigin?: string;
    apiBase: string;
    requestUrl: string;
  };

  constructor(apiError: ApiError, meta: ApiCallMeta) {
    super(apiError.message);
    this.name = "ApiClientError";
    this.apiError = apiError;
    const apiBase = resolveApiBase();
    const requestUrl = toAbsoluteUrl(resolveApiUrl(meta.endpoint));
    this.meta = {
      endpoint: meta.endpoint,
      method: meta.method,
      requestBody: meta.requestBody,
      apiOrigin: resolveApiOrigin(),
      apiBase,
      requestUrl,
    };
  }
}

export function isApiClientError(err: unknown): err is ApiClientError {
  return err instanceof ApiClientError;
}

export function requireSuccess<T>(response: ApiResponse<T>, meta: ApiCallMeta): T {
  if (response.success) return response.data as T;
  const apiError: ApiError =
    response.error ||
    ({
      code: "E9999",
      message: "Unknown API error",
      timestamp: new Date().toISOString(),
    } as ApiError);
  throw new ApiClientError(apiError, meta);
}

function looksLikeJson(text: string): boolean {
  const trimmed = text.trim();
  return trimmed.startsWith("{") || trimmed.startsWith("[");
}

/**
 * Core fetch operation (without circuit breaker - used internally)
 */
async function fetchRequest<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  const method = (options?.method || 'GET') as ApiMethod;
  const functionName = `api.${method.toLowerCase()}.${endpoint}`;
  
  logger.trace(functionName, 'enter', { endpoint, method });
  const startTime = Date.now();
  try {
    const apiBase = resolveApiBase();
    const apiOrigin = resolveApiOrigin();
    const url = resolveApiUrl(endpoint);
    const requestUrl = toAbsoluteUrl(url);

    const headers = new Headers(options?.headers);
    headers.set("Accept", "application/json");
    // Only set Content-Type when we actually send a body.
    if (options?.body != null && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    // Read as text first so we can gracefully handle HTML even when the server lies about Content-Type.
    const raw = await response.text();
    const contentType = response.headers.get("content-type") || "";
    const preview = raw.slice(0, 800);

    // JSON happy-path
    if (looksLikeJson(raw)) {
      return JSON.parse(raw) as ApiResponse<T>;
    }

    // HTML / SPA fallback detection
    const rawTrim = raw.trim();
    const looksLikeHtml = rawTrim.startsWith("<!") || rawTrim.startsWith("<html") || /<html[\s>]/i.test(raw);

    // Raw environment variable values for diagnostics
    const envViteApiUrl = (import.meta.env.VITE_API_URL as string | undefined) || "(not set)";
    const envViteWsUrl = (import.meta.env.VITE_WS_URL as string | undefined) || "(not set)";
    const uiOrigin = typeof window !== "undefined" ? window.location.origin : "N/A";

    if (looksLikeHtml || !contentType.includes("application/json")) {
      return {
        success: false,
        error: {
          code: "E9005",
          message: "API returned HTML instead of JSON",
          details:
            "This usually means the UI is not talking to the Go backend (wrong base URL/port, or preview environment).\n" +
            `Requested URL: ${requestUrl}\n` +
            `Configured API base: ${apiBase}\n` +
            `API Base (absolute): ${toAbsoluteUrl(apiBase)}\n` +
            `VITE_API_URL (raw): ${envViteApiUrl}\n` +
            "Fix: set VITE_API_URL to your backend origin (e.g. http://localhost:8080) and reload.\n" +
            `HTTP ${response.status} (${contentType || "no content-type"})`,
          context: {
            requestUrl,
            apiBase,
            apiBaseAbsolute: toAbsoluteUrl(apiBase),
            "VITE_API_URL (raw)": envViteApiUrl,
            "VITE_WS_URL (raw)": envViteWsUrl,
            resolvedApiOrigin: apiOrigin || null,
            uiOrigin,
            responseStatus: response.status,
            contentType: contentType || null,
            responsePreview: preview,
          },
          timestamp: new Date().toISOString(),
        },
      };
    }

    // Unexpected non-JSON (but not HTML)
    return {
      success: false,
      error: {
        code: "E9006",
        message: "Unexpected API response format",
        details:
          `Expected JSON but got: ${contentType || "unknown"}\n` +
          `Requested URL: ${requestUrl}\n` +
          `Preview: ${preview}`,
        context: {
          requestUrl,
          apiBase,
          apiBaseAbsolute: toAbsoluteUrl(apiBase),
          "VITE_API_URL (raw)": envViteApiUrl,
          "VITE_WS_URL (raw)": envViteWsUrl,
          resolvedApiOrigin: apiOrigin || null,
          uiOrigin,
          responseStatus: response.status,
          contentType: contentType || null,
          responsePreview: preview,
        },
        timestamp: new Date().toISOString(),
      },
    };
  } catch (error) {
    const duration = Date.now() - startTime;
    logger.error(`API request failed: ${endpoint}`, error, { endpoint, method, duration });
    
    // Raw environment variable values for diagnostics
    const envViteApiUrl = (import.meta.env.VITE_API_URL as string | undefined) || "(not set)";
    const envViteWsUrl = (import.meta.env.VITE_WS_URL as string | undefined) || "(not set)";
    const uiOrigin = typeof window !== "undefined" ? window.location.origin : "N/A";
    return {
      success: false,
      error: {
        code: "E9003",
        message: "Network error",
        details: error instanceof Error ? error.message : "Unknown error",
        context: {
          apiBase: resolveApiBase(),
          apiBaseAbsolute: toAbsoluteUrl(resolveApiBase()),
          "VITE_API_URL (raw)": envViteApiUrl,
          "VITE_WS_URL (raw)": envViteWsUrl,
          resolvedApiOrigin: resolveApiOrigin() || null,
          uiOrigin,
        },
        timestamp: new Date().toISOString(),
      },
    };
  } finally {
    const duration = Date.now() - startTime;
    logger.trace(functionName, 'exit', { endpoint, method, duration });
  }
}

/**
 * Main request function with circuit breaker protection
 */
async function request<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  const circuitKey = `api:${endpoint}`;
  
  try {
    return await withCircuitBreaker(circuitKey, () => fetchRequest<T>(endpoint, options));
  } catch (error) {
    // If circuit breaker blocked the call, return a user-friendly error
    if (error instanceof Error && (error as unknown as { code?: string }).code === 'E_CIRCUIT_OPEN') {
      logger.warn(`Circuit breaker open for ${endpoint}, request blocked`);
      return {
        success: false,
        error: {
          code: "E_CIRCUIT_OPEN",
          message: "Too many recent failures for this operation",
          details: `The circuit breaker has blocked requests to ${endpoint} due to repeated failures. Please wait a moment and try again.`,
          timestamp: new Date().toISOString(),
        },
      };
    }
    throw error;
  }
}

export function getApiDiagnostics() {
  const apiBase = resolveApiBase();
  const apiOrigin = resolveApiOrigin();
  return {
    apiOrigin: apiOrigin || null,
    apiBase,
    apiBaseAbsolute: toAbsoluteUrl(apiBase),
  };
}

// Types
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
  stats?: {
    additions: number;
    deletions: number;
  };
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

// Phase 10: Remote Plugin File Browser
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
    // Frontend resilience settings
    frontendDebugMode?: boolean;
    retryMaxAttempts?: number;
    retryInitialDelayMs?: number;
    circuitBreakerThreshold?: number;
    circuitBreakerCooldownMs?: number;
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
}

// API methods
export const api = {
  // Health
  health: () => request<{ status: string }>("/health"),

  // Sites
  getSites: () => request<Site[]>("/sites"),
  getSite: (id: number) => request<Site>(`/sites/${id}`),
  createSite: (site: { name: string; url: string; username: string; applicationPassword: string; category?: string }) =>
    request<Site>("/sites", { method: "POST", body: JSON.stringify(site) }),
  updateSite: (id: number, site: Partial<Site> & { applicationPassword?: string; category?: string }) =>
    request<Site>(`/sites/${id}`, { method: "PUT", body: JSON.stringify(site) }),
  deleteSite: (id: number) =>
    request<void>(`/sites/${id}`, { method: "DELETE" }),
  testConnection: (id: number) =>
    request<{ success: boolean; wpVersion?: string; message?: string; siteName?: string; canManagePlugins?: boolean }>(`/sites/${id}/test`, { method: "POST" }),
  testCredentials: (credentials: { url: string; username: string; password: string }) =>
    request<{ success: boolean; wpVersion?: string; message?: string; siteName?: string; canManagePlugins?: boolean }>("/sites/test", { method: "POST", body: JSON.stringify(credentials) }),
  bootstrapUploader: (siteId: number, uploaderPath?: string) =>
    request<{ success: boolean; siteId: number; siteName: string; message: string; activated: boolean }>(
      `/sites/${siteId}/bootstrap-uploader`,
      { method: "POST", body: JSON.stringify({ uploaderPath }) }
    ),
  bulkBootstrapUploader: (siteIds: number[], uploaderPath?: string) =>
    request<{ results: Array<{ siteId: number; siteName: string; success: boolean; message: string; activated?: boolean; error?: string }> }>(
      `/sites/bulk-bootstrap-uploader`,
      { method: "POST", body: JSON.stringify({ siteIds, uploaderPath }) }
    ),
  getSiteCredentials: (siteId: number) =>
    request<{ url: string; username: string; appPassword: string }>(`/sites/${siteId}/credentials`),

  // Plugins
  getPlugins: () => request<Plugin[]>("/plugins"),
  getPlugin: (id: number) => request<Plugin>(`/plugins/${id}`),
  createPlugin: (plugin: { 
    name: string; 
    path: string; 
    category?: string;
    watchEnabled?: boolean; 
    excludePatterns?: string[];
    gitEnabled?: boolean;
    gitRemoteUrl?: string;
    buildCommand?: string;
    forceCreate?: boolean;
  }) =>
    request<Plugin>("/plugins", { method: "POST", body: JSON.stringify(plugin) }),
  updatePlugin: (id: number, plugin: Partial<Plugin>) =>
    request<Plugin>(`/plugins/${id}`, { method: "PUT", body: JSON.stringify(plugin) }),
  deletePlugin: (id: number) =>
    request<void>(`/plugins/${id}`, { method: "DELETE" }),
  getPluginMappings: (pluginId: number) =>
    request<PluginMapping[]>(`/plugins/${pluginId}/mappings`),
  createPluginMapping: (pluginId: number, mapping: { siteId: number; remoteSlug: string }) =>
    request<PluginMapping>(`/plugins/${pluginId}/mappings`, { method: "POST", body: JSON.stringify(mapping) }),
  updatePluginMappings: (pluginId: number, mapping: { siteIds: number[]; remoteSlug: string }) =>
    request<PluginMapping[]>(`/plugins/${pluginId}/mappings`, { method: "PUT", body: JSON.stringify(mapping) }),
  deletePluginMapping: (mappingId: number) =>
    request<void>(`/mappings/${mappingId}`, { method: "DELETE" }),
  
  // Site mappings (plugins linked to a site)
  getSiteMappings: (siteId: number) =>
    request<PluginMapping[]>(`/sites/${siteId}/mappings`),
  updateSiteMappings: (siteId: number, pluginIds: number[]) =>
    request<PluginMapping[]>(`/sites/${siteId}/mappings`, { 
      method: "PUT", 
      body: JSON.stringify({ pluginIds }) 
    }),
  
  // Remote plugin management
  getRemotePlugins: (siteId: number) =>
    request<RemotePlugin[]>(`/sites/${siteId}/remote-plugins`),
  forceSyncRemotePlugins: (siteId: number) =>
    request<RemotePlugin[]>(`/sites/${siteId}/remote-plugins/force-sync`, { method: "POST" }),
  clearRemotePluginsCache: (siteId: number) =>
    request<{ cleared: boolean }>(`/sites/${siteId}/remote-plugins/cache`, { method: "DELETE" }),
  enableRemotePlugin: (siteId: number, pluginSlug: string) =>
    request<{ enabled: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/${encodeURIComponent(pluginSlug)}/enable`,
      { method: "POST" }
    ),
  disableRemotePlugin: (siteId: number, pluginSlug: string) =>
    request<{ disabled: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/${encodeURIComponent(pluginSlug)}/disable`,
      { method: "POST" }
    ),
  deleteRemotePlugin: (siteId: number, pluginSlug: string) =>
    request<{ deleted: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/${encodeURIComponent(pluginSlug)}`,
      { method: "DELETE" }
    ),
  // Remote plugin file browser (Phase 10)
  getRemotePluginFiles: (siteId: number, pluginSlug: string) =>
    request<RemotePluginFilesResult>(
      `/sites/${siteId}/remote-plugins/${encodeURIComponent(pluginSlug)}/files`
    ),
  getRemotePluginFileContent: (siteId: number, pluginSlug: string, filePath: string) =>
    request<{ path: string; content: string }>(
      `/sites/${siteId}/remote-plugins/${encodeURIComponent(pluginSlug)}/file`,
      { method: "POST", body: JSON.stringify({ path: filePath }) }
    ),

  // Git operations
  gitPull: (pluginId: number) =>
    request<{ success: boolean; filesChanged: number; commitHash: string; branch: string }>(
      `/plugins/${pluginId}/git/pull`, { method: "POST" }
    ),
  gitPullAll: () =>
    request<{ succeeded: number; failed: number; duration: number }>(
      `/plugins/git/pull`, { method: "POST" }
    ),
  gitStatus: (pluginId: number) =>
    request<{ 
      branch: string; 
      ahead: number; 
      behind: number; 
      staged: number; 
      modified: number; 
      untracked: number; 
      hasChanges: boolean;
      lastCommit?: string;
    }>(`/plugins/${pluginId}/git/status`),
  gitCommit: (pluginId: number, message: string) =>
    request<{ success: boolean; commitHash: string }>(
      `/plugins/${pluginId}/git/commit`, { method: "POST", body: JSON.stringify({ message }) }
    ),
  gitPush: (pluginId: number) =>
    request<{ success: boolean; pushed: number }>(
      `/plugins/${pluginId}/git/push`, { method: "POST" }
    ),

  // Bulk operations
  bulkUpdatePlugins: (pluginIds: number[], update: { watchEnabled?: boolean }) =>
    request<{ updated: number }>(
      `/plugins/bulk`, { method: "PATCH", body: JSON.stringify({ pluginIds, ...update }) }
    ),
  bulkDeletePlugins: (pluginIds: number[]) =>
    request<{ deleted: number }>(
      `/plugins/bulk`, { method: "DELETE", body: JSON.stringify({ pluginIds }) }
    ),

  // File scanning (hybrid watcher)
  scanPlugin: (pluginId: number) =>
    request<{ pluginId: number; filesScanned: number; changes: FileChange[] }>(
      `/plugins/${pluginId}/scan`, { method: "POST" }
    ),
  scanAllPlugins: () =>
    request<{ results: Array<{ pluginId: number; changes: number }> }>(
      `/plugins/scan`, { method: "POST" }
    ),
 
   // Scan directory for WordPress plugin info
   scanDirectory: (path: string, createDetection?: boolean) =>
     request<{
       path: string;
       isValid: boolean;
       pluginName?: string;
       version?: string;
       mainFile?: string;
       description?: string;
       author?: string;
       textDomain?: string;
       fileCount: number;
       totalSize: number;
       error?: string;
       detectionCreated?: boolean;
     }>("/plugins/scan-directory", {
       method: "POST",
       body: JSON.stringify({ path, createDetection }),
     }),

   // Scan multiple directories for WordPress plugins
   scanDirectories: (paths: string[], createDetection?: boolean) =>
     request<{
       scanned: number;
       detected: number;
       results: Array<{
         path: string;
         isPlugin: boolean;
         metadata?: {
           pluginName?: string;
           version?: string;
           mainFile?: string;
           description?: string;
           author?: string;
           textDomain?: string;
           fileCount: number;
           totalSize: number;
         };
         error?: string;
         detectionCreated?: boolean;
       }>;
     }>("/plugins/scan-directories", {
       method: "POST",
       body: JSON.stringify({ paths, createDetection }),
     }),

  // Sync
  getFileChanges: (pluginId: number, siteId: number) =>
    request<FileChange[]>(`/plugins/${pluginId}/changes?siteId=${siteId}`),
  checkSync: (pluginId: number, siteId: number) =>
    request<{ changedFiles: number }>(`/plugins/${pluginId}/sites/${siteId}/sync`, { method: "POST" }),
  checkAllSites: (pluginId: number) =>
    request<void>(`/plugins/${pluginId}/sync/check-all`, { method: "POST" }),

  // Publish
  publishPlugin: (
    pluginId: number,
    siteId: number,
    options: { mode: "selected" | "full"; files?: string[]; createBackup: boolean; keepZipFiles?: boolean }
  ) =>
    request<{ filesUpdated: number; backupId?: number }>(`/plugins/${pluginId}/sites/${siteId}/publish`, {
      method: "POST",
      body: JSON.stringify(options),
    }),
  previewPublish: (pluginId: number, siteId: number) =>
    request<PublishPreview>(`/plugins/${pluginId}/sites/${siteId}/preview`),

  // Publish History
  getPublishHistory: (params?: { limit?: number; offset?: number; status?: string; pluginId?: number; siteId?: number; search?: string }) => {
    const q = new URLSearchParams();
    if (params?.limit) q.set("limit", String(params.limit));
    if (params?.offset) q.set("offset", String(params.offset));
    if (params?.status) q.set("status", params.status);
    if (params?.pluginId) q.set("pluginId", String(params.pluginId));
    if (params?.siteId) q.set("siteId", String(params.siteId));
    if (params?.search) q.set("search", params.search);
    return request<{ entries: PublishHistoryEntry[]; total: number }>(`/publish-history?${q.toString()}`);
  },
  getPublishHistoryStats: () => request<PublishHistoryStats>("/publish-history/stats"),
  deletePublishHistoryEntry: (id: number) => request<void>(`/publish-history/${id}`, { method: "DELETE" }),
  clearPublishHistory: () => request<void>("/publish-history", { method: "DELETE", body: JSON.stringify({ confirm: true }) }),

  // Backups
  getBackups: (pluginId: number) => request<Backup[]>(`/plugins/${pluginId}/backups`),
  restoreBackup: (backupId: number) =>
    request<{ success: boolean }>(`/backups/${backupId}/restore`, { method: "POST" }),
  deleteBackup: (backupId: number) =>
    request<void>(`/backups/${backupId}`, { method: "DELETE" }),

  // Errors
  getErrors: (limit?: number) =>
    request<ErrorLog[]>(`/errors${limit ? `?limit=${limit}` : ""}`),
  getError: (id: number) => request<ErrorLog>(`/errors/${id}`),
  clearErrors: () => request<void>("/errors", { method: "DELETE" }),
  // Get backend error.log.txt file content
  getBackendErrorLog: () =>
    request<{ content: string; filename: string; size: number; lastModified: string }>("/errors/log"),
  // Get backend full log.txt file content
  getBackendFullLog: () =>
    request<{ content: string; filename: string; size: number; lastModified: string }>("/logs/full"),

  // Plugin Version History
  getPluginVersions: (pluginId: number, siteId?: number, limit?: number) =>
    request<PluginVersion[]>(
      `/plugins/${pluginId}/versions${siteId ? `?siteId=${siteId}` : ""}${limit ? `${siteId ? "&" : "?"}limit=${limit}` : ""}`
    ),
  getPluginVersion: (pluginId: number, versionId: number) =>
    request<PluginVersion>(`/plugins/${pluginId}/versions/${versionId}`),
  rollbackPluginVersion: (pluginId: number, versionId: number) =>
    request<{ success: boolean; version: string; rolledBackAt: string }>(
      `/plugins/${pluginId}/versions/${versionId}/rollback`, { method: "POST" }
    ),
  deletePluginVersion: (pluginId: number, versionId: number) =>
    request<void>(`/plugins/${pluginId}/versions/${versionId}`, { method: "DELETE" }),

  // Settings
  getSettings: () => request<Settings>("/settings"),
  updateSettings: (settings: Partial<Settings>) =>
    request<Settings>("/settings", { method: "PUT", body: JSON.stringify(settings) }),
  updateSetting: (key: string, value: string) =>
    request<Settings>(`/settings/${encodeURIComponent(key)}`, { 
      method: "PUT", 
      body: JSON.stringify({ value }) 
    }),

  // Sessions
  getSessions: (limit?: number) =>
    request<SessionSummary[]>(`/sessions${limit ? `?limit=${limit}` : ""}`),
  getSession: (sessionId: string) =>
    request<SessionInfo>(`/sessions/${sessionId}`),
  getSessionLogs: (sessionId: string) =>
    request<{ sessionId: string; logs: string }>(`/sessions/${sessionId}/logs`),
  deleteSession: (sessionId: string) =>
    request<void>(`/sessions/${sessionId}`, { method: "DELETE" }),

  // File content for diff viewer
  getFileDiff: (pluginId: number, siteId: number, filePath: string) =>
    request<{ localContent: string; remoteContent: string; path: string }>(
      `/plugins/${pluginId}/sites/${siteId}/file-diff`,
      { method: "POST", body: JSON.stringify({ path: filePath }) }
    ),
  getLocalFileContent: (pluginId: number, filePath: string) =>
    request<{ content: string; path: string }>(
      `/plugins/${pluginId}/file`,
      { method: "POST", body: JSON.stringify({ path: filePath }) }
    ),

  // E2E Testing
  getE2ESuites: () => request<unknown[]>("/e2e/suites"),
  getE2ECases: (suiteId: string) => request<unknown[]>(`/e2e/suites/${suiteId}/cases`),
  startE2ERun: (opts: { suites?: string[]; cases?: string[]; parallel: boolean; stopOnFailure: boolean }) =>
    request<{ runId: string; status: string; totalTests: number }>("/e2e/run", { 
      method: "POST", 
      body: JSON.stringify(opts) 
    }),
  abortE2ERun: (runId: string) =>
    request<void>(`/e2e/runs/${runId}/abort`, { method: "POST" }),
  getE2ERuns: (limit?: number) =>
    request<unknown[]>(`/e2e/runs${limit ? `?limit=${limit}` : ""}`),
  getE2ERun: (runId: string) =>
    request<unknown>(`/e2e/runs/${runId}`),
  deleteE2ERun: (runId: string) =>
    request<void>(`/e2e/runs/${runId}`, { method: "DELETE" }),

  // Remote Snapshot Management (Phase 28)
  getRemoteSnapshots: (siteId: number) =>
    request<SnapshotRecord[]>(`/sites/${siteId}/snapshots`),
  getRemoteSnapshot: (siteId: number, snapshotId: number) =>
    request<SnapshotRecord>(`/sites/${siteId}/snapshots/${snapshotId}`),
  createRemoteSnapshot: (siteId: number, opts?: Record<string, unknown>) =>
    request<Record<string, unknown>>(`/sites/${siteId}/snapshots`, {
      method: "POST",
      body: JSON.stringify(opts || {}),
    }),
  deleteRemoteSnapshot: (siteId: number, snapshotId: number) =>
    request<{ deleted: boolean }>(`/sites/${siteId}/snapshots/${snapshotId}`, { method: "DELETE" }),
  restoreRemoteSnapshot: (siteId: number, snapshotId: number, opts?: Record<string, unknown>) =>
    request<Record<string, unknown>>(`/sites/${siteId}/snapshots/${snapshotId}/restore`, {
      method: "POST",
      body: JSON.stringify(opts || {}),
    }),
  getRemoteSnapshotSettings: (siteId: number) =>
    request<SnapshotSettings>(`/sites/${siteId}/snapshots/settings`),
  updateRemoteSnapshotSettings: (siteId: number, settings: Record<string, unknown>) =>
    request<SnapshotSettings>(`/sites/${siteId}/snapshots/settings`, {
      method: "PUT",
      body: JSON.stringify(settings),
    }),
  getRemoteSnapshotProviders: (siteId: number) =>
    request<SnapshotProviderInfo[]>(`/sites/${siteId}/snapshots/providers`),
  getRemoteSnapshotExportUrl: (siteId: number, snapshotId: number): string => {
    const base = resolveApiBase();
    return `${base}/sites/${siteId}/snapshots/${snapshotId}/export`;
  },
  getRemoteAvailableTables: (siteId: number) =>
    request<AvailableTable[]>(`/sites/${siteId}/snapshots/tables`),

  // Error History (persistent error/notification storage)
  saveErrorHistory: (input: ErrorHistoryInput) =>
    request<ErrorHistoryRecord>("/error-history", { 
      method: "POST", 
      body: JSON.stringify(input) 
    }),
  listErrorHistory: (opts?: { limit?: number; offset?: number; code?: string; level?: string; search?: string }) => {
    const params = new URLSearchParams();
    if (opts?.limit) params.set("limit", opts.limit.toString());
    if (opts?.offset) params.set("offset", opts.offset.toString());
    if (opts?.code) params.set("code", opts.code);
    if (opts?.level) params.set("level", opts.level);
    if (opts?.search) params.set("search", opts.search);
    const query = params.toString();
    return request<ErrorHistoryListResponse>(`/error-history${query ? `?${query}` : ""}`);
  },
  getErrorHistoryById: (id: number | string) =>
    request<ErrorHistoryRecord>(`/error-history/${id}`),
  deleteErrorHistory: (id: number) =>
    request<{ deleted: boolean; id: number }>(`/error-history/${id}`, { method: "DELETE" }),
  clearErrorHistory: () =>
    request<{ cleared: boolean; deleted: number }>("/error-history", { method: "DELETE" }),
  bulkExportErrorHistory: (ids: number[]) =>
    request<{ report: string; count: number }>("/error-history/bulk-export", { 
      method: "POST", 
      body: JSON.stringify({ ids }) 
    }),
  getErrorHistoryStats: () =>
    request<ErrorHistoryStats>("/error-history/stats"),
};

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

// Snapshot Types (Phase 28)
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
