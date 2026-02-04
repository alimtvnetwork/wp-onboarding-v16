// API client for WP Plugin Publish backend

import { resolveApiBase, resolveApiOrigin, resolveApiUrl, toAbsoluteUrl } from "@/lib/endpoints";

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

async function request<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
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

  // Git operations
  gitPull: (pluginId: number) =>
    request<{ success: boolean; filesChanged: number; commitHash: string; branch: string }>(
      `/plugins/${pluginId}/git/pull`, { method: "POST" }
    ),
  gitPullAll: () =>
    request<{ succeeded: number; failed: number; duration: number }>(
      `/plugins/git/pull`, { method: "POST" }
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
    options: { mode: "selected" | "full"; files?: string[]; createBackup: boolean }
  ) =>
    request<{ filesUpdated: number; backupId?: number }>(`/plugins/${pluginId}/sites/${siteId}/publish`, {
      method: "POST",
      body: JSON.stringify(options),
    }),

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

  // Settings
  getSettings: () => request<Settings>("/settings"),
  updateSettings: (settings: Partial<Settings>) =>
    request<Settings>("/settings", { method: "PUT", body: JSON.stringify(settings) }),
  updateSetting: (key: string, value: string) =>
    request<Settings>(`/settings/${encodeURIComponent(key)}`, { 
      method: "PUT", 
      body: JSON.stringify({ value }) 
    }),

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
};

