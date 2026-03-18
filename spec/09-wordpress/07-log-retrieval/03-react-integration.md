# Log Retrieval — React Frontend Integration

> **Location:** Site detail page — new "Logs" tab

---

## 1. UI Design

### Tab Placement

Add a **"Logs"** tab to the site detail view, positioned after the existing tabs (e.g., Overview, Plugins, Settings).

### Tab Layout

The Logs tab contains:

1. **Control bar** — refresh button, max lines selector, include/exclude toggles
2. **Three collapsible sections** — Info Log, Error Log, Stack Trace
3. **Each section shows:**
   - File metadata (exists, size, line count, truncated indicator)
   - Monospace log content viewer with scroll
   - Copy to clipboard button
   - Truncation warning banner (if applicable)

### Empty State

If a log file doesn't exist (`Exists: false`), show a muted message: "No log file found."

### Loading State

Show skeleton loaders for each section while the API call is in progress.

### Error State

If the API call fails, show an error banner with the error message and a retry button.

---

## 2. API Integration

### React Query Hook

```typescript
// src/hooks/useLogRetrieval.ts

interface LogRetrievalParams {
  siteId: string;
  includeInfoLog?: boolean;
  includeErrorLog?: boolean;
  includeStacktrace?: boolean;
  maxLines?: number;
}

interface LogFileData {
  Exists: boolean;
  File: string;
  Path: string;
  Content: string;
  Lines: number;
  TotalLines: number;
  TotalSize: number;
  Truncated: boolean;
}

interface LogRetrievalResponse {
  Success: boolean;
  Version: string;
  RequestedAt: string;
  Settings: {
    include_info_log: boolean;
    include_error_log: boolean;
    include_stacktrace: boolean;
    max_lines: number;
  };
  InfoLog?: LogFileData;
  ErrorLog?: LogFileData;
  StacktraceLog?: LogFileData;
}
```

### Query Key Pattern

```typescript
['site-logs', siteId, { includeInfoLog, includeErrorLog, includeStacktrace, maxLines }]
```

### Caching

- **staleTime:** 30 seconds (logs change frequently)
- **refetchOnWindowFocus:** false (avoid unnecessary calls)
- Manual refresh via refetch button

---

## 3. Component Structure

```
src/components/sites/logs/
├── SiteLogsTab.tsx          — Tab container with controls
├── LogFileSection.tsx       — Collapsible log file viewer
├── LogControlBar.tsx        — Refresh, max lines, toggles
└── LogContentViewer.tsx     — Monospace content display with copy
```

### SiteLogsTab

- Manages `useLogRetrieval` hook state
- Passes data to child components
- Handles loading/error/empty states

### LogFileSection

- Accepts a `LogFileData` object and a title
- Collapsible via Radix Collapsible
- Shows metadata badges (size, lines, truncated)
- Renders `LogContentViewer` for content

### LogControlBar

- Refresh button (calls `refetch()`)
- Max lines dropdown: 50, 100, 200, 500, 1000
- Toggle switches for info/error/stacktrace inclusion

### LogContentViewer

- Pre-formatted monospace text display
- Max height with scroll (400px default)
- Copy to clipboard button (top-right)
- Line numbers (optional, togglable)
- Syntax highlighting for timestamps and log levels (INFO/ERROR/WARN)

---

## 4. Go Backend Proxy

The React app calls the Go backend, which proxies to the WordPress site:

```
React → GET /api/sites/{siteId}/logs/retrieve?params...
  → Go backend → GET /wp-json/{namespace}/v1/logs/retrieve?params...
    → WordPress plugin → reads files → returns response
```

### Go Route

```
GET /api/sites/{siteId}/logs/retrieve
```

Query parameters are forwarded as-is to the WordPress endpoint.

### Error Handling

- **Site not found:** 404
- **WordPress unreachable:** 502 with timeout info
- **Auth failure:** 401/403 forwarded
- **Plugin missing endpoint:** 404 (v2.18.0+ required)

---

## 5. UX Considerations

1. **Auto-refresh:** No auto-refresh by default; users click refresh manually to avoid hammering the server.
2. **Truncation warning:** If `Truncated: true`, show a yellow banner: "Showing last {Lines} of {TotalLines} lines. Increase max lines to see more."
3. **File size display:** Format bytes as human-readable (KB/MB).
4. **Responsive:** On mobile, sections stack vertically; control bar wraps.
5. **Dark mode:** Log viewer uses dark background with light monospace text (terminal aesthetic) in both light and dark themes.
