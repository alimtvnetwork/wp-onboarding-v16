# Log Retrieval — Enhanced Log Viewer UI Spec

> **Location:** Remote Logs Panel (RemoteLogsPanel.tsx)
> **Status:** Implemented
> **Updated:** 2026-03-22

---

## 1. Overview

The Remote Logs Panel provides full log content viewing for **both** WordPress plugins
(Riseup Asia and QUpload) with tabbed navigation, copy/download, and clear capabilities.

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

### Panel Structure

1. **Status section** — File metadata (existing: name, lines, size per file)
2. **Action bar** — Refresh, View Logs, Max Lines selector, Clear, Clear All, Email
3. **Content viewer** (expanded on "View Logs" click):
   - **Plugin tabs** — One tab per available plugin (Riseup Asia / QUpload)
   - **Log type tabs** (nested) — Info | Error | Stacktrace
   - **Per-tab content** — Monospace scrollable viewer with metadata badges
4. **Download All** button — Downloads all available log content as a single `.txt` file
5. **Copy** button — Per log file, copies content to clipboard

### Tab Hierarchy

```
[Plugin Tabs: QUpload | Riseup Asia]
  └─ [Log Type Tabs: Info | Error | Stacktrace]
       └─ Metadata badges (lines, size, truncated)
       └─ Truncation warning (if applicable)
       └─ Monospace content viewer (400px scroll)
       └─ Copy button
```

### States

- **Not available**: Plugin tab shows "Plugin not available on this site"
- **File not found**: Log tab shows "No {type} file found."
- **Truncated**: Yellow warning banner with line counts
- **Empty content**: Shows "(empty)"

---

## 4. Endpoints

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

## 5. Backend Files

- `backend/internal/wordpress/LogsRetrieveTypes.go` — Go types for retrieve response
- `backend/internal/services/site/RemoteLogsRetrieve.go` — Service method with parallel probing
- `backend/internal/api/handlers/HandlerRemoteLogsRetrieve.go` — HTTP handler
- Endpoint enum: `LogsRetrieve` in endpointtype + operationtype

## 6. Frontend Files

- `src/components/plugins/RemoteLogsPanel.tsx` — Full panel with content viewer
- `src/lib/api/types.ts` — `LogsRetrieveResult`, `PluginLogsData`, `LogRetrieveFileData`
- `src/lib/api/methods.ts` — `api.retrieveRemoteLogs()`
