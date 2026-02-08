# 14 — Logging System

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Active

---

## Overview

Every log entry must include:
- **Timestamp** — Configurable format (single source of truth: `config.json` → `logging.timeFormat`)
- **Level** — debug, info, warn, error
- **Message** — Human-readable description
- **File** — Source file name
- **Line** — Line number
- **Context** — Relevant data as key-value pairs

---

## Timestamp Configuration (SINGLE SOURCE OF TRUTH)

The timestamp format is configured in **one place only**: `config.json` → `logging.timeFormat`.

### Default Format

```
2006-01-02 03:04:05 PM
```

This produces output like:

```
[2026-02-04 03:16:26 PM] INFO  main.go:52 - Starting application name=WP Plugin Publish version=1.0.0
```

### Configuration

```json
// config.json
{
  "logging": {
    "level": "info",
    "retentionDays": 7,
    "debugMode": false,
    "timeFormat": "2006-01-02 03:04:05 PM"
  }
}
```

### Go Time Format Reference

| Format String | Output Example |
|---------------|----------------|
| `2006-01-02 03:04:05 PM` | `2026-02-04 03:16:26 PM` (12-hour, default) |
| `2006-01-02 15:04:05` | `2026-02-04 15:16:26` (24-hour) |
| `time.RFC3339` | `2026-02-04T15:16:26+08:00` (ISO8601) |
| `Jan 02 15:04:05` | `Feb 04 15:16:26` |

### Implementation

```go
// backend/cmd/server/main.go
cfg, err := config.Load("config.json")
// ...
log := logger.New(logger.Config{
    Level:      parseLogLevel(cfg.Logging.Level),
    TimeFormat: cfg.Logging.TimeFormat,  // <-- from config
})
```

---

## Logger Implementation

### Core Logger

```go
// internal/logger/logger.go
package logger

type Config struct {
    Level      Level
    Output     io.Writer
    TimeFormat string   // Go time layout string
    NoColor    bool
}

func New(cfg Config) *Logger {
    if cfg.Output == nil {
        cfg.Output = os.Stdout
    }
    if cfg.TimeFormat == "" {
        cfg.TimeFormat = "2006-01-02 03:04:05 PM"  // 12-hour default
    }
    return &Logger{config: cfg}
}
```

### Log Output Format

```
[TIMESTAMP] LEVEL file:line - message key=value...
```

Example:
```
[2026-02-04 03:16:26 PM] INFO  service.go:45 - Starting plugin publish plugin_id=1 site_id=2
```

---

## Log Levels Usage

| Level | When to Use | Example |
|-------|-------------|---------|
| Debug | Development details, variable values | `log.Debug("Hash calculated", "hash", hash)` |
| Info | Normal operations, milestones | `log.Info("Plugin published", "id", id)` |
| Warn | Recoverable issues, deprecations | `log.Warn("Retrying connection", "attempt", 2)` |
| Error | Failures, exceptions | `log.Error("Publish failed", "error", err)` |

---

## Request Logging Middleware

```go
// internal/api/middleware/middleware.go
func Logging(log *logger.Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            start := time.Now()
            rw := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}
            next.ServeHTTP(rw, r)

            duration := time.Since(start)
            log.Info("HTTP request",
                "method", r.Method,
                "path", r.URL.Path,
                "status", rw.statusCode,
                "duration", duration.String(),
            )

            // Persist error responses (>= 400) to error.log.txt
            if rw.statusCode >= 400 && ErrorLogDir != "" {
                appendToErrorLog(r, rw, duration)
            }
        })
    }
}
```

---

## Middleware Error Log Persistence

> **Added:** v1.19.5

All HTTP error responses (status ≥ 400) are automatically appended to `data/errors/error.log.txt` by the `Logging` middleware. This ensures the Global Error Modal's **Log** tab always has diagnostic content — not just for remote plugin action failures.

### Initialization

The `ErrorLogDir` package-level variable must be set in `main.go` after the errors directory is created:

```go
// backend/cmd/server/main.go
import "wp-plugin-publish/internal/api/middleware"

errorsDir := filepath.Join(filepath.Dir(cfg.DatabasePath), "errors")
os.MkdirAll(errorsDir, 0755)

// Enable middleware-level error logging to error.log.txt
middleware.ErrorLogDir = errorsDir
```

When `ErrorLogDir` is empty (zero value), error-log persistence is disabled.

### Response Body Capture

The `responseWriter` wrapper intercepts `Write()` calls to capture the response body **only** for error responses (status ≥ 400). Successful responses are not buffered, avoiding unnecessary memory overhead.

```go
func (rw *responseWriter) Write(b []byte) (int, error) {
    if rw.statusCode >= 400 {
        rw.body.Write(b) // capture for error.log.txt
    }
    return rw.ResponseWriter.Write(b)
}
```

### Log Entry Format

Each error entry is appended to `error.log.txt` with the following structure:

```
[2026-02-08 17:20:26] HTTP 500 POST /api/v1/sites/1/plugins/disable
  Query: slug=my-plugin
  Duration: 1.234s
  Response: {"Status":{"IsSuccess":false,"IsFailed":true,"Code":500,...},...}
───────────────────────────────────────────────────────────────────────────────
```

| Field | Description |
|-------|-------------|
| Timestamp | `YYYY-MM-DD HH:MM:SS` local time |
| HTTP status | The numeric status code returned to the client |
| Method + Path | The HTTP method and URL path |
| Query | URL query string (omitted if empty) |
| Duration | Total request processing time |
| Response | The JSON response body, truncated to **2 KB** for large payloads |
| Separator | Visual `───` divider between entries |

### Relationship to Site Service Error Logging

The middleware error log is **complementary** to the existing `logToErrorFile()` in `site/service.go`:

| Source | Scope | Deduplication | Format |
|--------|-------|---------------|--------|
| `middleware.Logging` | **All** HTTP errors (≥ 400) | None (every error logged) | Generic HTTP request/response |
| `site.logToErrorFile` | Remote plugin action failures only | MD5-based hash suppression | Redefined Log Format with delegated request/response blocks |

Both write to the same file (`data/errors/error.log.txt`) via append. The middleware provides baseline coverage so that **no error goes unrecorded**, while the site service provides enriched diagnostics for remote operations.

### Truncation

Response bodies exceeding **2,048 bytes** are truncated with a `... (truncated)` suffix to prevent the log file from growing excessively due to large error payloads (e.g., full HTML error pages from WordPress).

---

## Key Requirements

1. **Single source of truth**: Timestamp format MUST be configurable from `config.json` → `logging.timeFormat` only
2. **Default**: 12-hour clock format (`2006-01-02 03:04:05 PM`)
3. **Customizable**: Users can change to 24-hour or any Go time layout
4. **Consistent**: All backend logs use the same format

---

## Session-Based Logging

For operation-specific logs (publish, sync, backup), use the Session Service instead of the global logger. Session logs are:

- Isolated to individual operation files
- Retrievable via REST API
- Correlated with WebSocket events via `sessionId`

### Detailed Stage Context Logging

The publish pipeline requires granular context for upload and activate stages:

```go
type StageContext struct {
    What      string                 // What is being processed
    Why       string                 // Why this operation is happening
    Where     string                 // Target URL/path
    Result    string                 // Outcome summary
    InnerData map[string]interface{} // HTTP status, response bodies, etc.
}
```

Example log entry:

```
[2026-02-05 01:24:27] [INFO] [upload] Starting upload
    {
      "what": "Plugin ZIP (category-generator.zip, 45.2 KB)",
      "why": "User initiated publish",
      "where": "https://example.com/wp-json/riseup-asia-uploader/v1/upload",
      "result": "Pending",
      "innerData": {
        "zipPath": "/path/to/plugin.zip",
        "fileCount": 23
      }
    }
```

See [17-session-management.md](./17-session-management.md) for full session service documentation.

---

## Next Document

See [02-frontend/20-frontend-overview.md](../02-frontend/20-frontend-overview.md) for React architecture.
