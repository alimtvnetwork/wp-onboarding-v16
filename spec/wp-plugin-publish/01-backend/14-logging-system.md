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
// internal/api/middleware/logging.go
func Logging(log *logger.Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            start := time.Now()
            rw := &responseWriter{ResponseWriter: w, status: http.StatusOK}
            next.ServeHTTP(rw, r)
            
            log.Info("HTTP request",
                "method", r.Method,
                "path", r.URL.Path,
                "status", rw.status,
                "duration", time.Since(start),
            )
        })
    }
}
```

---

## Key Requirements

1. **Single source of truth**: Timestamp format MUST be configurable from `config.json` → `logging.timeFormat` only
2. **Default**: 12-hour clock format (`2006-01-02 03:04:05 PM`)
3. **Customizable**: Users can change to 24-hour or any Go time layout
4. **Consistent**: All backend logs use the same format

---

## Next Document

See [02-frontend/20-frontend-overview.md](../02-frontend/20-frontend-overview.md) for React architecture.
