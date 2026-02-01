# 20 — Frontend Overview

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

The React frontend provides a local UI for managing WordPress sites, plugins, sync status, and publishing operations.

---

## Technology Stack

| Technology | Purpose |
|------------|---------|
| React 18 | UI framework |
| TypeScript | Type safety |
| Tailwind CSS | Styling |
| React Router | Navigation |
| TanStack Query | Server state management |
| WebSocket | Real-time updates |
| Zustand | Client state (optional) |

---

## Directory Structure

```
web/
├── src/
│   ├── components/
│   │   ├── ui/                    # Base UI components (shadcn/ui)
│   │   ├── layout/
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Header.tsx
│   │   │   └── Layout.tsx
│   │   ├── sites/
│   │   │   ├── SiteCard.tsx
│   │   │   ├── SiteForm.tsx
│   │   │   └── SiteList.tsx
│   │   ├── plugins/
│   │   │   ├── PluginCard.tsx
│   │   │   ├── PluginForm.tsx
│   │   │   └── PluginList.tsx
│   │   ├── sync/
│   │   │   ├── SyncStatus.tsx
│   │   │   ├── FileChangeList.tsx
│   │   │   └── SyncActions.tsx
│   │   └── errors/
│   │       ├── ErrorConsole.tsx
│   │       ├── ErrorCard.tsx
│   │       └── ErrorCopyButton.tsx
│   ├── pages/
│   │   ├── Dashboard.tsx
│   │   ├── Sites.tsx
│   │   ├── Plugins.tsx
│   │   ├── Sync.tsx
│   │   ├── Settings.tsx
│   │   └── Errors.tsx
│   ├── hooks/
│   │   ├── useSites.ts
│   │   ├── usePlugins.ts
│   │   ├── useSync.ts
│   │   ├── useWebSocket.ts
│   │   └── useErrors.ts
│   ├── lib/
│   │   ├── api.ts                 # API client
│   │   ├── ws.ts                  # WebSocket client
│   │   └── utils.ts
│   ├── types/
│   │   ├── site.ts
│   │   ├── plugin.ts
│   │   ├── sync.ts
│   │   └── error.ts
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css
├── package.json
└── vite.config.ts
```

---

## Page Structure

### Dashboard

Main overview page showing:
- Connected sites count
- Registered plugins count
- Pending file changes
- Recent sync activity
- Quick actions

### Sites Page

CRUD interface for WordPress sites:
- List of connected sites
- Add new site form
- Test connection button
- Edit/delete actions

### Plugins Page

Plugin directory management:
- List of registered plugins
- Add plugin form (browse for directory)
- Map plugin to site
- Enable/disable file watching

### Sync Page

Change detection and publishing:
- Pending changes per plugin
- Diff viewer (local vs remote)
- Publish single file / full plugin buttons
- Backup before publish toggle

### Settings Page

Application configuration:
- File watcher settings
- Backup retention
- Log level
- Theme (light/dark)

### Errors Page

Error console for debugging:
- Scrollable error list
- Filter by level/code
- Expand for full details
- Copy to clipboard button

---

## API Client

```typescript
// src/lib/api.ts
const API_BASE = 'http://localhost:8080/api/v1';

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
  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options?.headers,
    },
  });

  const data = await response.json();
  return data;
}

export const api = {
  // Sites
  getSites: () => request<Site[]>('/sites'),
  getSite: (id: number) => request<Site>(`/sites/${id}`),
  createSite: (site: CreateSiteInput) => 
    request<Site>('/sites', { method: 'POST', body: JSON.stringify(site) }),
  updateSite: (id: number, site: UpdateSiteInput) =>
    request<Site>(`/sites/${id}`, { method: 'PUT', body: JSON.stringify(site) }),
  deleteSite: (id: number) =>
    request<void>(`/sites/${id}`, { method: 'DELETE' }),
  testConnection: (id: number) =>
    request<ConnectionResult>(`/sites/${id}/test`),

  // Plugins
  getPlugins: () => request<Plugin[]>('/plugins'),
  getPlugin: (id: number) => request<Plugin>(`/plugins/${id}`),
  createPlugin: (plugin: CreatePluginInput) =>
    request<Plugin>('/plugins', { method: 'POST', body: JSON.stringify(plugin) }),
  updatePlugin: (id: number, plugin: UpdatePluginInput) =>
    request<Plugin>(`/plugins/${id}`, { method: 'PUT', body: JSON.stringify(plugin) }),
  deletePlugin: (id: number) =>
    request<void>(`/plugins/${id}`, { method: 'DELETE' }),

  // Sync
  checkSync: (pluginId: number) =>
    request<SyncResult>(`/plugins/${pluginId}/sync/check`),
  publishPlugin: (pluginId: number, mode: 'single' | 'full') =>
    request<PublishResult>(`/plugins/${pluginId}/publish`, {
      method: 'POST',
      body: JSON.stringify({ mode }),
    }),
  getFileChanges: (pluginId: number) =>
    request<FileChange[]>(`/plugins/${pluginId}/changes`),

  // Backups
  getBackups: (pluginId: number) =>
    request<Backup[]>(`/plugins/${pluginId}/backups`),
  restoreBackup: (backupId: number) =>
    request<RestoreResult>(`/backups/${backupId}/restore`, { method: 'POST' }),

  // Errors
  getErrors: (limit?: number) =>
    request<ErrorLog[]>(`/errors${limit ? `?limit=${limit}` : ''}`),
  clearErrors: () =>
    request<void>('/errors', { method: 'DELETE' }),

  // Settings
  getSettings: () => request<Settings>('/settings'),
  updateSettings: (settings: UpdateSettingsInput) =>
    request<Settings>('/settings', { method: 'PUT', body: JSON.stringify(settings) }),
};
```

---

## WebSocket Client

```typescript
// src/lib/ws.ts
type EventHandler = (data: any) => void;

class WebSocketClient {
  private ws: WebSocket | null = null;
  private handlers: Map<string, Set<EventHandler>> = new Map();
  private reconnectTimer: number | null = null;

  connect() {
    this.ws = new WebSocket('ws://localhost:8080/ws');

    this.ws.onopen = () => {
      console.log('WebSocket connected');
      if (this.reconnectTimer) {
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
      }
    };

    this.ws.onmessage = (event) => {
      const message = JSON.parse(event.data);
      const { type, data } = message;
      
      const typeHandlers = this.handlers.get(type);
      if (typeHandlers) {
        typeHandlers.forEach(handler => handler(data));
      }
    };

    this.ws.onclose = () => {
      console.log('WebSocket disconnected, reconnecting...');
      this.reconnectTimer = setTimeout(() => this.connect(), 3000);
    };
  }

  on(event: string, handler: EventHandler) {
    if (!this.handlers.has(event)) {
      this.handlers.set(event, new Set());
    }
    this.handlers.get(event)!.add(handler);

    return () => {
      this.handlers.get(event)?.delete(handler);
    };
  }

  disconnect() {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
    }
    this.ws?.close();
  }
}

export const wsClient = new WebSocketClient();
```

---

## WebSocket Hook

```typescript
// src/hooks/useWebSocket.ts
import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { wsClient } from '@/lib/ws';

export function useWebSocket() {
  const queryClient = useQueryClient();

  useEffect(() => {
    wsClient.connect();

    // File change events
    const unsubFileChange = wsClient.on('file_change', (data) => {
      queryClient.invalidateQueries({ queryKey: ['fileChanges', data.pluginId] });
      queryClient.invalidateQueries({ queryKey: ['plugins'] });
    });

    // Sync complete events
    const unsubSyncComplete = wsClient.on('sync_complete', (data) => {
      queryClient.invalidateQueries({ queryKey: ['plugins', data.pluginId] });
      queryClient.invalidateQueries({ queryKey: ['syncRecords'] });
    });

    // Error events
    const unsubError = wsClient.on('error', (data) => {
      queryClient.invalidateQueries({ queryKey: ['errors'] });
    });

    return () => {
      unsubFileChange();
      unsubSyncComplete();
      unsubError();
      wsClient.disconnect();
    };
  }, [queryClient]);
}
```

---

## Theme System

Uses CSS variables for theming:

```css
/* src/index.css */
:root {
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  --primary: 222.2 47.4% 11.2%;
  --primary-foreground: 210 40% 98%;
  /* ... other tokens */
}

.dark {
  --background: 222.2 84% 4.9%;
  --foreground: 210 40% 98%;
  /* ... dark mode tokens */
}
```

---

## Next Document

See [21-site-manager-ui.md](./21-site-manager-ui.md) for site management UI details.
