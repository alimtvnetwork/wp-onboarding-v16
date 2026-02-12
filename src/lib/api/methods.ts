// API method definitions — the `api` object with all endpoint methods.

import { resolveApiBase, resolveApiUrl, toAbsoluteUrl } from "@/lib/endpoints";
import { request } from './client';
import { isEnvelope, parseEnvelope, looksLikeJson } from './envelope';
import type {
  ApiResponse,
  Site,
  Plugin,
  PluginMapping,
  PluginVersion,
  FileChange,
  SyncResult,
  Backup,
  RemotePlugin,
  RemotePluginFilesResult,
  ErrorLog,
  SessionSummary,
  SessionInfo,
  SessionDiagnostics,
  PublishPreview,
  Settings,
  PublishHistoryEntry,
  PublishHistoryStats,
  SnapshotRecord,
  SnapshotSettings,
  SnapshotProviderInfo,
  AvailableTable,
  ErrorHistoryInput,
  ErrorHistoryRecord,
  ErrorHistoryListResponse,
  ErrorHistoryStats,
  RequestSessionRecord,
  RequestSessionListResponse,
  SnapshotCronJob,
  SnapshotCronSyncResult,
} from './types';

// ---------------------------------------------------------------------------
// Utility: build query string from optional params
// ---------------------------------------------------------------------------

function buildQuery(params: Record<string, string | number | undefined | null>): string {
  const q = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value != null && value !== '') {
      q.set(key, String(value));
    }
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}

// ---------------------------------------------------------------------------
// API methods
// ---------------------------------------------------------------------------

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
    request<{ isSuccess: boolean; wpVersion?: string; message?: string; siteName?: string; canManagePlugins?: boolean }>(`/sites/${id}/test`, { method: "POST" }),
  testCredentials: (credentials: { url: string; username: string; password: string }) =>
    request<{ isSuccess: boolean; wpVersion?: string; message?: string; siteName?: string; canManagePlugins?: boolean }>("/sites/test", { method: "POST", body: JSON.stringify(credentials) }),
  bootstrapUploader: (siteId: number, uploaderPath?: string) =>
    request<{ isSuccess: boolean; siteId: number; siteName: string; message: string; isActivated: boolean }>(
      `/sites/${siteId}/bootstrap-uploader`,
      { method: "POST", body: JSON.stringify({ uploaderPath }) }
    ),
  bulkBootstrapUploader: (siteIds: number[], uploaderPath?: string) =>
    request<{ results: Array<{ siteId: number; siteName: string; isSuccess: boolean; message: string; isActivated?: boolean; error?: string }> }>(
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
  checkRemotePluginExists: (siteId: number, pluginSlug: string) =>
    request<{ exists: boolean; status: string; plugin_file: string; plugin: string }>(
      `/sites/${siteId}/remote-plugins/exists`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug }) }
    ),
  forceSyncRemotePlugins: (siteId: number) =>
    request<RemotePlugin[]>(`/sites/${siteId}/remote-plugins/force-sync`, { method: "POST" }),
  clearRemotePluginsCache: (siteId: number) =>
    request<{ cleared: boolean }>(`/sites/${siteId}/remote-plugins/cache`, { method: "DELETE" }),
  enableRemotePlugin: (siteId: number, pluginSlug: string, version?: string) =>
    request<{ enabled: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/enable`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug, ...(version ? { version } : {}) }) }
    ),
  disableRemotePlugin: (siteId: number, pluginSlug: string, version?: string) =>
    request<{ disabled: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/disable`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug, ...(version ? { version } : {}) }) }
    ),
  deleteRemotePlugin: (siteId: number, pluginSlug: string, version?: string) =>
    request<{ deleted: boolean; plugin: string }>(
      `/sites/${siteId}/remote-plugins/delete`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug, ...(version ? { version } : {}) }) }
    ),
  uploadRemotePlugin: (siteId: number, file: File, activate: boolean) => {
    const formData = new FormData();
    formData.append("plugin_zip", file);
    formData.append("activate", String(activate));
    return request<{ installed: boolean; plugin: string; activated: boolean }>(
      `/sites/${siteId}/remote-plugins/upload`,
      {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      }
    );
  },

  /**
   * Upload a remote plugin ZIP with progress callback (uses XMLHttpRequest).
   */
  uploadRemotePluginWithProgress: (
    siteId: number,
    file: File,
    activate: boolean,
    onProgress: (percent: number) => void
  ): Promise<ApiResponse<{ installed: boolean; plugin: string; activated: boolean }>> => {
    return new Promise((resolve) => {
      const formData = new FormData();
      formData.append("plugin_zip", file);
      formData.append("activate", String(activate));

      const url = toAbsoluteUrl(resolveApiUrl(`/sites/${siteId}/remote-plugins/upload`));
      const xhr = new XMLHttpRequest();
      xhr.open("POST", url, true);
      xhr.setRequestHeader("Accept", "application/json");

      xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      });

      xhr.addEventListener("load", () => {
        try {
          const json = JSON.parse(xhr.responseText);
          // Handle envelope format
          if (json.Status) {
            if (json.Status.IsSuccess) {
              const data = json.Attributes?.IsSingle && Array.isArray(json.Results) && json.Results.length > 0
                ? json.Results[0]
                : json.Results;
              resolve({ success: true, data });
            } else {
              resolve({
                success: false,
                error: {
                  code: "E_UPLOAD",
                  message: json.Status.Message || "Upload failed",
                  timestamp: json.Status.Timestamp || new Date().toISOString(),
                },
              });
            }
          } else if (xhr.status >= 200 && xhr.status < 300) {
            resolve({ success: true, data: json });
          } else {
            resolve({
              success: false,
              error: {
                code: "E_UPLOAD",
                message: json.error || json.message || `HTTP ${xhr.status}`,
                timestamp: new Date().toISOString(),
              },
            });
          }
        } catch {
          resolve({
            success: false,
            error: {
              code: "E_UPLOAD",
              message: `Upload failed with status ${xhr.status}`,
              timestamp: new Date().toISOString(),
            },
          });
        }
      });

      xhr.addEventListener("error", () => {
        resolve({
          success: false,
          error: {
            code: "E_NETWORK",
            message: "Network error during upload",
            timestamp: new Date().toISOString(),
          },
        });
      });

      xhr.send(formData);
    });
  },

  // Remote plugin file browser
  getRemotePluginFiles: (siteId: number, pluginSlug: string) =>
    request<RemotePluginFilesResult>(
      `/sites/${siteId}/remote-plugins/files`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug }) }
    ),
  getRemotePluginFileContent: (siteId: number, pluginSlug: string, filePath: string) =>
    request<{ path: string; content: string }>(
      `/sites/${siteId}/remote-plugins/file`,
      { method: "POST", body: JSON.stringify({ plugin: pluginSlug, path: filePath }) }
    ),

  // Git operations
  gitPull: (pluginId: number) =>
    request<{ isSuccess: boolean; filesChanged: number; commitHash: string; branch: string }>(
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
    request<{ isSuccess: boolean; commitHash: string }>(
      `/plugins/${pluginId}/git/commit`, { method: "POST", body: JSON.stringify({ message }) }
    ),
  gitPush: (pluginId: number) =>
    request<{ isSuccess: boolean; pushed: number }>(
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
    request<SyncResult>(`/plugins/${pluginId}/sites/${siteId}/sync`, { method: "POST" }),
  pushSync: (pluginId: number, siteId: number) =>
    request<{
      pluginId: number;
      siteId: number;
      filesUpdated: number;
      filesDeleted: number;
      filesIgnored: number;
      totalChanges: number;
      isSuccess: boolean;
      errorMessage?: string;
    }>(`/plugins/${pluginId}/sites/${siteId}/sync/push`, { method: "POST" }),
  checkAllSites: (pluginId: number) =>
    request<void>(`/plugins/${pluginId}/sync/check-all`, { method: "POST" }),

  // Publish — with global dedup lock to prevent duplicate concurrent requests
  publishPlugin: (() => {
    const inFlight = new Set<string>();
    const cooldowns = new Map<string, number>();
    const COOLDOWN_MS = 30_000; // 30s cooldown after success
    return (
      pluginId: number,
      siteId: number,
      options: { mode: "selected" | "full"; files?: string[]; createBackup: boolean; keepZipFiles?: boolean }
    ) => {
      const key = `${pluginId}:${siteId}`;
      // Block if already in-flight
      if (inFlight.has(key)) {
        console.warn(`[api.publishPlugin] BLOCKED duplicate in-flight request for plugin=${pluginId} site=${siteId}`);
        return Promise.resolve({
          success: false,
          error: {
            code: "E_DEDUP",
            message: "A publish is already in progress for this plugin and site",
            timestamp: new Date().toISOString(),
          },
        } as ApiResponse<{ filesUpdated: number; backupId?: number }>);
      }
      // Block if within cooldown period
      const lastSuccess = cooldowns.get(key);
      if (lastSuccess && Date.now() - lastSuccess < COOLDOWN_MS) {
        const secsLeft = Math.ceil((COOLDOWN_MS - (Date.now() - lastSuccess)) / 1000);
        console.warn(`[api.publishPlugin] BLOCKED by cooldown (${secsLeft}s remaining) for plugin=${pluginId} site=${siteId}`);
        return Promise.resolve({
          success: false,
          error: {
            code: "E_COOLDOWN",
            message: `Publish cooldown active (${secsLeft}s remaining). Please wait before re-publishing.`,
            timestamp: new Date().toISOString(),
          },
        } as ApiResponse<{ filesUpdated: number; backupId?: number }>);
      }
      inFlight.add(key);
      return request<{ filesUpdated: number; backupId?: number }>(`/plugins/${pluginId}/sites/${siteId}/publish`, {
        method: "POST",
        body: JSON.stringify(options),
      }).then(response => {
        if (response.success) {
          cooldowns.set(key, Date.now());
        }
        return response;
      }).finally(() => {
        inFlight.delete(key);
      });
    };
  })(),
  previewPublish: (pluginId: number, siteId: number) =>
    request<PublishPreview>(`/plugins/${pluginId}/sites/${siteId}/preview`),

  // Publish History
  getPublishHistory: (params?: { limit?: number; offset?: number; status?: string; pluginId?: number; siteId?: number; search?: string }) =>
    request<{ entries: PublishHistoryEntry[]; total: number }>(`/publish-history${buildQuery(params || {})}`),
  getPublishHistoryStats: () => request<PublishHistoryStats>("/publish-history/stats"),
  deletePublishHistoryEntry: (id: number) => request<void>(`/publish-history/${id}`, { method: "DELETE" }),
  clearPublishHistory: () => request<void>("/publish-history", { method: "DELETE", body: JSON.stringify({ confirm: true }) }),

  // Site Health
  checkSiteHealth: (siteId: number) => request<unknown>(`/site-health/sites/${siteId}/check`, { method: "POST" }),
  checkAllSitesHealth: () => request<unknown>("/site-health/check-all", { method: "POST" }),
  getSiteHealthSummaries: () => request<unknown>("/site-health/summaries"),
  getSiteHealthStats: () => request<unknown>("/site-health/stats"),
  getSiteHealthHistory: (params?: { siteId?: number; limit?: number }) =>
    request<unknown>(`/site-health/history${buildQuery(params || {})}`),

  // Backups
  getBackups: (pluginId: number) => request<Backup[]>(`/plugins/${pluginId}/backups`),
  restoreBackup: (backupId: number) =>
    request<{ isSuccess: boolean }>(`/backups/${backupId}/restore`, { method: "POST" }),
  deleteBackup: (backupId: number) =>
    request<void>(`/backups/${backupId}`, { method: "DELETE" }),

  // Errors
  getErrors: (limit?: number) =>
    request<ErrorLog[]>(`/errors${limit ? `?limit=${limit}` : ""}`),
  getError: (id: number) => request<ErrorLog>(`/errors/${id}`),
  clearErrors: () => request<void>("/errors", { method: "DELETE" }),
  clearErrorDedup: () => request<{ cleared: number; message: string }>("/settings/clear-error-dedup", { method: "POST" }),
  getBackendErrorLog: () =>
    request<{ content: string; filename: string; size: number; lastModified: string }>("/errors/log"),
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
    request<{ isSuccess: boolean; version: string; rolledBackAt: string }>(
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
  getSessionDiagnostics: (sessionId: string) =>
    request<SessionDiagnostics>(`/sessions/${sessionId}/diagnostics`),

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
  rerunE2ECase: (caseId: string) =>
    request<{ runId: string }>("/e2e/run", {
      method: "POST",
      body: JSON.stringify({ cases: [caseId], parallel: false, stopOnFailure: false }),
    }),

  // Remote Snapshot Management
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
  /**
   * Download a cached snapshot ZIP via Go proxy.
   * Streams the ZIP binary; returns a Blob for client-side download.
   */
  downloadSnapshotZip: async (siteId: number, snapshotId: number): Promise<{ blob: Blob; filename: string; cached: boolean; size: number }> => {
    const url = resolveApiUrl(`/sites/${siteId}/snapshots/download`);
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ snapshot_id: snapshotId }),
    });
    if (!res.ok) {
      const text = await res.text();
      let msg = `Download failed (${res.status})`;
      try {
        const parsed = JSON.parse(text);
        msg = parsed?.Status?.Message || parsed?.message || msg;
      } catch { /* ignore */ }
      throw new Error(msg);
    }
    const blob = await res.blob();
    const disposition = res.headers.get("Content-Disposition") || "";
    let filename = `snapshot-${snapshotId}.zip`;
    const match = disposition.match(/filename="?([^";\n]+)"?/);
    if (match?.[1]) filename = match[1];
    const cached = res.headers.get("X-Snapshot-Cached") === "true";
    const size = parseInt(res.headers.get("X-Snapshot-Size") || "0", 10);
    return { blob, filename, cached, size };
  },
  getRemoteAvailableTables: (siteId: number) =>
    request<AvailableTable[]>(`/sites/${siteId}/snapshots/tables`),

  // Full backup orchestration
  fullBackupRemoteSnapshot: (siteId: number, opts?: Record<string, unknown>) =>
    request<Record<string, unknown>>(`/sites/${siteId}/snapshots/full-backup`, {
      method: "POST",
      body: JSON.stringify(opts || {}),
    }),

  // Incremental backup
  incrementalBackupRemoteSnapshot: (siteId: number, opts?: Record<string, unknown>) =>
    request<Record<string, unknown>>(`/sites/${siteId}/snapshots/incremental`, {
      method: "POST",
      body: JSON.stringify(opts || {}),
    }),

  // Import snapshot from ZIP
  importRemoteSnapshot: async (siteId: number, file: File): Promise<ApiResponse<Record<string, unknown>>> => {
    const formData = new FormData();
    formData.append("file", file);
    const url = resolveApiUrl(`/sites/${siteId}/snapshots/import`);
    const res = await fetch(url, { method: "POST", body: formData });
    const text = await res.text();
    if (!looksLikeJson(text)) {
      return { success: false, error: { code: "E9005", message: "Non-JSON response", timestamp: new Date().toISOString() } };
    }
    const parsed = JSON.parse(text);
    if (isEnvelope(parsed)) {
      return parseEnvelope<Record<string, unknown>>(parsed);
    }
    return parsed as ApiResponse<Record<string, unknown>>;
  },

  // Snapshot cleanup
  cleanupRemoteSnapshots: (siteId: number, opts?: Record<string, unknown>) =>
    request<Record<string, unknown>>(`/sites/${siteId}/snapshots/cleanup`, {
      method: "POST",
      body: JSON.stringify(opts || {}),
    }),

  // Snapshot Cron Jobs
  getSnapshotCronJobs: (siteId: number) =>
    request<SnapshotCronJob[]>(`/sites/${siteId}/snapshots/cron`),
  syncSnapshotCronJobs: (siteId: number) =>
    request<SnapshotCronSyncResult>(`/sites/${siteId}/snapshots/cron/sync`, { method: "POST" }),
  triggerSnapshotCronJob: (siteId: number, cronId: string) =>
    request<{ triggered: boolean; runId?: string }>(`/sites/${siteId}/snapshots/cron/${cronId}/trigger`, { method: "POST" }),
  pauseSnapshotCronJob: (siteId: number, cronId: string) =>
    request<SnapshotCronJob>(`/sites/${siteId}/snapshots/cron/${cronId}/pause`, { method: "POST" }),
  resumeSnapshotCronJob: (siteId: number, cronId: string) =>
    request<SnapshotCronJob>(`/sites/${siteId}/snapshots/cron/${cronId}/resume`, { method: "POST" }),

  // Error History
  saveErrorHistory: (input: ErrorHistoryInput) =>
    request<ErrorHistoryRecord>("/error-history", { 
      method: "POST", 
      body: JSON.stringify(input) 
    }),
  listErrorHistory: (opts?: { limit?: number; offset?: number; code?: string; level?: string; search?: string }) =>
    request<ErrorHistoryListResponse>(`/error-history${buildQuery(opts || {})}`),
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

  // Request Sessions (per-API-call logging)
  getRequestSessions: (opts?: { limit?: number; offset?: number }) =>
    request<RequestSessionListResponse>(`/request-sessions${buildQuery(opts || {})}`),
  getRequestSession: (id: string) =>
    request<RequestSessionRecord>(`/request-sessions/${id}`),
  getRequestSessionErrors: (limit?: number) =>
    request<RequestSessionListResponse>(`/request-sessions/errors${limit ? `?limit=${limit}` : ""}`),
  deleteRequestSession: (id: string) =>
    request<{ deleted: boolean; id: string }>(`/request-sessions/${id}`, { method: "DELETE" }),
  clearRequestSessions: () =>
    request<{ cleared: boolean }>("/request-sessions", { method: "DELETE" }),
  exportRequestSession: (id: string) =>
    request<RequestSessionRecord>(`/request-sessions/${id}/export`),
};
