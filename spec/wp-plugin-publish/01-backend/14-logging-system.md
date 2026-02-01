# 14 — Logging System

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

Every log entry must include:
- **Timestamp** — ISO8601 format
- **Level** — debug, info, warn, error
- **Message** — Human-readable description
- **File** — Source file name
- **Line** — Line number
- **Function** — Function name
- **Context** — Relevant data as key-value pairs

---

## Logger Implementation

### Core Logger

```go
// internal/logger/logger.go
package logger

import (
    "io"
    "os"
    "runtime"
    "strings"
    "time"

    "github.com/rs/zerolog"
)

type Logger struct {
    log      zerolog.Logger
    dbWriter *DBWriter
}

type Config struct {
    Level     string    // "debug", "info", "warn", "error"
    Output    io.Writer // Where to write logs (default: stdout)
    DBWriter  *DBWriter // Optional database writer for errors
}

func New(cfg Config) *Logger {
    level := parseLevel(cfg.Level)
    output := cfg.Output
    if output == nil {
        output = os.Stdout
    }
    
    log := zerolog.New(output).
        Level(level).
        With().
        Timestamp().
        Logger()
    
    return &Logger{
        log:      log,
        dbWriter: cfg.DBWriter,
    }
}

func parseLevel(level string) zerolog.Level {
    switch strings.ToLower(level) {
    case "debug":
        return zerolog.DebugLevel
    case "info":
        return zerolog.InfoLevel
    case "warn":
        return zerolog.WarnLevel
    case "error":
        return zerolog.ErrorLevel
    default:
        return zerolog.InfoLevel
    }
}
```

### Logging Methods

```go
// internal/logger/logger.go (continued)

func (l *Logger) Debug(msg string, args ...any) {
    l.logWithCaller(zerolog.DebugLevel, msg, args...)
}

func (l *Logger) Info(msg string, args ...any) {
    l.logWithCaller(zerolog.InfoLevel, msg, args...)
}

func (l *Logger) Warn(msg string, args ...any) {
    l.logWithCaller(zerolog.WarnLevel, msg, args...)
}

func (l *Logger) Error(msg string, args ...any) {
    l.logWithCaller(zerolog.ErrorLevel, msg, args...)
    
    // Also write to database if configured
    if l.dbWriter != nil {
        l.writeErrorToDB(msg, args...)
    }
}

func (l *Logger) Fatal(msg string, args ...any) {
    l.logWithCaller(zerolog.FatalLevel, msg, args...)
    os.Exit(1)
}

func (l *Logger) logWithCaller(level zerolog.Level, msg string, args ...any) {
    file, line, fn := getCaller(3)
    
    event := l.log.WithLevel(level).
        Str("file", file).
        Int("line", line).
        Str("function", fn)
    
    // Add key-value pairs
    for i := 0; i < len(args)-1; i += 2 {
        key, ok := args[i].(string)
        if !ok {
            continue
        }
        event = addField(event, key, args[i+1])
    }
    
    event.Msg(msg)
}

func addField(event *zerolog.Event, key string, value any) *zerolog.Event {
    switch v := value.(type) {
    case string:
        return event.Str(key, v)
    case int:
        return event.Int(key, v)
    case int64:
        return event.Int64(key, v)
    case float64:
        return event.Float64(key, v)
    case bool:
        return event.Bool(key, v)
    case error:
        return event.Err(v)
    case time.Duration:
        return event.Dur(key, v)
    default:
        return event.Interface(key, v)
    }
}
```

### Caller Extraction

```go
// internal/logger/context.go
package logger

import (
    "runtime"
    "strings"
)

func getCaller(skip int) (file string, line int, fn string) {
    pc, filePath, lineNum, ok := runtime.Caller(skip)
    if !ok {
        return "unknown", 0, "unknown"
    }
    
    // Extract just the filename
    if idx := strings.LastIndex(filePath, "/"); idx >= 0 {
        file = filePath[idx+1:]
    } else {
        file = filePath
    }
    
    // Extract function name
    fn = "unknown"
    if f := runtime.FuncForPC(pc); f != nil {
        name := f.Name()
        if idx := strings.LastIndex(name, "."); idx >= 0 {
            fn = name[idx+1:]
        } else {
            fn = name
        }
    }
    
    return file, lineNum, fn
}
```

---

## Log Output Format

### Console Output (Development)

```json
{
  "level": "info",
  "time": "2026-02-01T10:30:00Z",
  "file": "service.go",
  "line": 45,
  "function": "PublishPlugin",
  "plugin_id": 1,
  "site_id": 2,
  "message": "Starting plugin publish"
}
```

### Pretty Console (Optional)

```go
// internal/logger/formatter.go
package logger

import (
    "fmt"
    "io"
    "time"
    
    "github.com/rs/zerolog"
)

func NewPrettyWriter(w io.Writer) zerolog.ConsoleWriter {
    return zerolog.ConsoleWriter{
        Out:        w,
        TimeFormat: time.RFC3339,
        FormatLevel: func(i interface{}) string {
            return fmt.Sprintf("| %-5s |", i)
        },
        FormatCaller: func(i interface{}) string {
            return ""  // We add our own file:line:function
        },
        FormatMessage: func(i interface{}) string {
            return fmt.Sprintf("%s", i)
        },
        FormatFieldName: func(i interface{}) string {
            return fmt.Sprintf("%s=", i)
        },
    }
}
```

Pretty output example:
```
2026-02-01T10:30:00Z | INFO  | Starting plugin publish file=service.go line=45 function=PublishPlugin plugin_id=1 site_id=2
```

---

## Request Logging Middleware

```go
// internal/api/middleware/logging.go
package middleware

import (
    "net/http"
    "time"
    
    "wp-plugin-publish/internal/logger"
)

type responseWriter struct {
    http.ResponseWriter
    status int
    size   int
}

func (rw *responseWriter) WriteHeader(status int) {
    rw.status = status
    rw.ResponseWriter.WriteHeader(status)
}

func (rw *responseWriter) Write(b []byte) (int, error) {
    size, err := rw.ResponseWriter.Write(b)
    rw.size += size
    return size, err
}

func Logging(log *logger.Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            start := time.Now()
            
            rw := &responseWriter{ResponseWriter: w, status: http.StatusOK}
            
            next.ServeHTTP(rw, r)
            
            duration := time.Since(start)
            
            log.Info("HTTP request",
                "method", r.Method,
                "path", r.URL.Path,
                "status", rw.status,
                "size", rw.size,
                "duration", duration,
                "remote_addr", r.RemoteAddr,
            )
        })
    }
}
```

---

## Service-Level Logging

```go
// internal/services/publish/service.go
func (s *Service) PublishPlugin(ctx context.Context, pluginID int64, mode string) error {
    s.log.Info("Starting plugin publish",
        "plugin_id", pluginID,
        "mode", mode,
    )
    
    // Get plugin
    plugin, err := s.pluginService.GetByID(ctx, pluginID)
    if err != nil {
        s.log.Error("Failed to get plugin",
            "error", err,
            "plugin_id", pluginID,
        )
        return err
    }
    
    s.log.Debug("Plugin retrieved",
        "plugin_id", pluginID,
        "name", plugin.Name,
        "local_path", plugin.LocalPath,
    )
    
    // Create backup
    s.log.Info("Creating backup before publish",
        "plugin_id", pluginID,
        "site_id", plugin.SiteID,
    )
    
    backupPath, err := s.backupService.Create(ctx, plugin)
    if err != nil {
        s.log.Error("Failed to create backup",
            "error", err,
            "plugin_id", pluginID,
        )
        return err
    }
    
    s.log.Info("Backup created",
        "plugin_id", pluginID,
        "backup_path", backupPath,
    )
    
    // Continue with publish...
    
    s.log.Info("Plugin publish completed",
        "plugin_id", pluginID,
        "mode", mode,
        "duration", time.Since(start),
    )
    
    return nil
}
```

---

## Database Error Writer

```go
// internal/logger/db_writer.go
func (l *Logger) writeErrorToDB(msg string, args ...any) {
    if l.dbWriter == nil {
        return
    }
    
    file, line, fn := getCaller(4)
    
    // Extract error from args if present
    var errObj error
    context := make(map[string]any)
    
    for i := 0; i < len(args)-1; i += 2 {
        key, ok := args[i].(string)
        if !ok {
            continue
        }
        if key == "error" {
            if e, ok := args[i+1].(error); ok {
                errObj = e
            }
        } else {
            context[key] = args[i+1]
        }
    }
    
    appErr := &apperror.AppError{
        Code:     "E9001",
        Message:  msg,
        Cause:    errObj,
        Context:  context,
        File:     file,
        Line:     line,
        Function: fn,
        Level:    "error",
    }
    
    // If it's already an AppError, use its details
    if ae, ok := errObj.(*apperror.AppError); ok {
        appErr = ae
    }
    
    l.dbWriter.Write(appErr)
}
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

## Configuration

```json
// config.json
{
  "settings": {
    "logLevel": "info"
  }
}
```

Log levels can be changed at runtime via the settings API.

---

## Next Document

See [02-frontend/20-frontend-overview.md](../02-frontend/20-frontend-overview.md) for React architecture.
