# Log Retrieval — Enhanced Log Viewer UI Spec

> **Location:** Remote Logs Panel (RemoteLogsPanel.tsx)
> **Status:** Implemented
> **Updated:** 2026-03-26

> **Diagram:** [remote-log-retrieval-flow.mmd](./remote-log-retrieval-flow.mmd)

---

## 1. Overview

The Remote Logs Panel provides full log content viewing for **both** WordPress plugins
(Riseup Asia and QUpload) with a three-tab layout, copy/download, and clear capabilities.

---

## 2. Architecture

### Dual-Plugin Parallel Retrieval

The Go backend probes BOTH plugin namespaces (`qupload-api/v1` and `riseup-asia-api/v1`)
in parallel goroutines, returning results from ALL available plugins — not first-wins.
This allows the UI to show separate tabs per plugin.

```
React → GET /api/sites/{siteId}/remote-logs/retrieve?max_lines=200
  → Go backend → 2 parallel goroutines:
    → GET /wp-json/qupload-api/v1/logs/retrieve?max_lines=200
    → GET /wp-json/riseup-asia-api/v1/logs/retrieve?max_lines=200
  → Combined response with both plugins' data
```

### Response Shape

```json
{
  "plugins": [
    {
      "namespace": "qupload-api/v1",
      "label": "QUpload",
      "available": true,
      "infoLog": { "Exists": true, "Content": "...", "Lines": 200, "TotalLines": 3033, "Truncated": true, ... },
      "errorLog": { "Exists": false, ... },
      "stacktrace": { "Exists": false, ... }
    },
    {
      "namespace": "riseup-asia-api/v1",
      "label": "Riseup Asia",
      "available": true,
      "infoLog": { ... },
      "errorLog": { ... },
      "stacktrace": { ... }
    }
  ]
}
```

---

## 3. UI Layout

### Top-Level Tabs

The panel uses a **three-tab layout** at the top level to separate concerns:

| Tab | Icon | Purpose |
|-----|------|---------|
| **Overview** | `ScrollText` | File metadata list, quick actions (Refresh, View Logs, Max Lines selector) |
| **Viewer** | `Eye` | Log content viewer with plugin/log-type sub-tabs. Shows `✓` badge when data is loaded |
| **Actions** | `Zap` | Destructive and utility operations (Clear, Clear All, Email) |

### Overview Tab

- **File list** — Each log file as a row: name (monospace), line count, size badge
- **Archive indicator** — Shows count of archived rotations if any
- **Quick actions bar** — Refresh, View Logs (primary), Max Lines selector (50–2000)

### Viewer Tab

Three states:

1. **Empty** — No logs loaded yet. Centered icon + "Load Logs" button
2. **Loading skeleton** — Animated placeholders for toolbar, plugin tabs, log-type tabs, metadata badges, and 12 content lines with varying widths
3. **Loaded** — Toolbar (Reload, Max Lines, Download All) + plugin/log-type sub-tabs

#### Viewer Sub-Tab Hierarchy

```
[Toolbar: Reload | Max Lines | Download All]
[Plugin Tabs: QUpload | Riseup Asia]       ← only if 2+ plugins available
  └─ [Log Type Tabs: Info | Error | Trace]
       └─ Metadata badges (lines, size, truncated)
       └─ Truncation warning (if applicable)
       └─ Monospace content viewer (400px scroll)
       └─ Copy button
```

When only one plugin is available, the plugin tab strip is omitted and the plugin label is shown as a text header instead.

### Actions Tab

Three card sections:

1. **Clear Logs** — Two-step confirmation (token-based) for the active plugin
2. **Clear All Plugins** — Destructive-themed card; clears both Riseup Asia + QUpload simultaneously
3. **Email Logs** — Opens dialog for recipient + archive toggle

### States

- **Not available**: Plugin sub-tab shows "Plugin not available on this site" with warning icon
- **File not found**: Log type tab shows "No {type} file found."
- **Truncated**: Yellow warning banner with line counts
- **Empty content**: Shows "(empty)"
- **Plugin outdated**: Full-width destructive banner with upgrade prompt (replaces all tabs)

---

## 4. Loading Skeleton (Viewer Tab)

When `isRetrieving` is true and no data exists yet, the Viewer tab renders an animated
pulse skeleton matching the final layout structure:

- Toolbar row: 2 left-aligned bars + 1 right-aligned bar
- Plugin tabs bar: full-width
- Log-type tabs bar: full-width (slightly lighter)
- Metadata badges: 2 pill shapes
- Content area: 400px bordered container with 12 lines of varying width (60–100%)

---

## 5. Endpoints

### Go Backend

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/sites/{id}/remote-logs/retrieve` | Retrieve log content from both plugins |

Query params: `include_info_log`, `include_error_log`, `include_stacktrace` (bool, default true), `max_lines` (int, default 200)

### PHP (both plugins)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/wp-json/{namespace}/v1/logs/retrieve` | Return log file contents |

---

## 6. Backend Files

- `backend/internal/wordpress/LogsRetrieveTypes.go` — Go types for retrieve response
- `backend/internal/services/site/RemoteLogsRetrieve.go` — Service method with parallel probing
- `backend/internal/api/handlers/HandlerRemoteLogsRetrieve.go` — HTTP handler
- Endpoint enum: `LogsRetrieve` in endpointtype + operationtype

## 7. Frontend Files

- `src/components/plugins/RemoteLogsPanel.tsx` — Full panel with 3-tab layout and content viewer
- `src/lib/api/types.ts` — `LogsRetrieveResult`, `PluginLogsData`, `LogRetrieveFileData`
- `src/lib/api/methods.ts` — `api.retrieveRemoteLogs()`
