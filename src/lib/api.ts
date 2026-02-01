// API client for WP Plugin Publish backend

const API_BASE = "http://localhost:8080/api/v1";

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

async function request<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  try {
    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        ...options?.headers,
      },
    });

    const data = await response.json();
    return data;
  } catch (error) {
    return {
      success: false,
      error: {
        code: "E9003",
        message: "Network error",
        details: error instanceof Error ? error.message : "Unknown error",
        timestamp: new Date().toISOString(),
      },
    };
  }
}

// Types
export interface Site {
  id: number;
  name: string;
  url: string;
  username: string;
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
  watchEnabled: boolean;
  excludePatterns: string[];
  fileCount: number;
  modifiedCount: number;
  gitEnabled?: boolean;
  gitRemoteUrl?: string;
  buildCommand?: string;
  mappings: PluginMapping[];
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
  createSite: (site: { name: string; url: string; username: string; applicationPassword: string }) =>
    request<Site>("/sites", { method: "POST", body: JSON.stringify(site) }),
  updateSite: (id: number, site: Partial<Site> & { applicationPassword?: string }) =>
    request<Site>(`/sites/${id}`, { method: "PUT", body: JSON.stringify(site) }),
  deleteSite: (id: number) =>
    request<void>(`/sites/${id}`, { method: "DELETE" }),
  testConnection: (id: number) =>
    request<{ success: boolean; wpVersion?: string; message?: string }>(`/sites/${id}/test`, { method: "POST" }),

  // Plugins
  getPlugins: () => request<Plugin[]>("/plugins"),
  getPlugin: (id: number) => request<Plugin>(`/plugins/${id}`),
  createPlugin: (plugin: { 
    name: string; 
    path: string; 
    watchEnabled?: boolean; 
    excludePatterns?: string[];
    gitEnabled?: boolean;
    gitRemoteUrl?: string;
    buildCommand?: string;
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
  deletePluginMapping: (mappingId: number) =>
    request<void>(`/mappings/${mappingId}`, { method: "DELETE" }),

  // Git operations
  gitPull: (pluginId: number) =>
    request<{ success: boolean; filesChanged: number; commitHash: string; branch: string }>(
      `/git/pull/${pluginId}`, { method: "POST" }
    ),
  gitPullAll: () =>
    request<{ succeeded: number; failed: number; duration: number }>(
      `/git/pull-all`, { method: "POST" }
    ),

  // File scanning (hybrid watcher)
  scanPlugin: (pluginId: number) =>
    request<{ pluginId: number; filesScanned: number; changes: FileChange[] }>(
      `/watcher/scan/${pluginId}`, { method: "POST" }
    ),
  scanAllPlugins: () =>
    request<{ results: Array<{ pluginId: number; changes: number }> }>(
      `/watcher/scan-all`, { method: "POST" }
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
};
