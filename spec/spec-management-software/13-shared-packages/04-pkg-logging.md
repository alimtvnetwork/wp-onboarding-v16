# pkg/logging Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Priority:** P0 (Foundational)  

---

## Overview

The `pkg/logging` package provides structured logging using Go's `slog` package. It enforces consistent log formats, supports context propagation for request tracing, and provides HTTP middleware for automatic request logging.

**Cross-References:**
- [Backend Logging Patterns](../06-error-management/backend/03-logging-patterns.md)
- [pkg/types Specification](./03-pkg-types.md)

---

## File Structure

```
pkg/logging/
├── logger.go      # Logger factory & configuration
├── context.go     # Context-aware logging
├── middleware.go  # HTTP middleware
├── fields.go      # Standard field definitions
├── handler.go     # Custom slog handlers
└── logging_test.go
```

---

## Design Goals

1. **Zero allocation** for disabled log levels
2. **Structured output** (JSON in production, text in development)
3. **Context propagation** for distributed tracing
4. **Request scoping** with automatic field injection
5. **Performance** - minimal overhead on hot paths

---

## logger.go

```go
package logging

import (
    "context"
    "io"
    "log/slog"
    "os"
)

// Level is the logging level
type Level = slog.Level

// Standard levels
const (
    LevelDebug = slog.LevelDebug
    LevelInfo  = slog.LevelInfo
    LevelWarn  = slog.LevelWarn
    LevelError = slog.LevelError
)

// Logger is the main logging interface
type Logger interface {
    Debug(msg string, args ...any)
    Info(msg string, args ...any)
    Warn(msg string, args ...any)
    Error(msg string, args ...any)
    
    // Context-aware logging
    DebugContext(ctx context.Context, msg string, args ...any)
    InfoContext(ctx context.Context, msg string, args ...any)
    WarnContext(ctx context.Context, msg string, args ...any)
    ErrorContext(ctx context.Context, msg string, args ...any)
    
    // Create child logger with additional fields
    With(args ...any) Logger
    
    // WithGroup creates a named group for fields
    WithGroup(name string) Logger
    
    // Enabled checks if level would be logged
    Enabled(ctx context.Context, level Level) bool
}

// Config holds logger configuration
type Config struct {
    Level       Level
    Format      Format
    Output      io.Writer
    AddSource   bool      // CRITICAL: Must be true - logs function name and file:line
    ServiceName string
    Version     string
}

// Format specifies output format
type Format string

const (
    FormatJSON Format = "json"
    FormatText Format = "text"
)

// CRITICAL REQUIREMENT: AddSource MUST be enabled
// All logs MUST contain:
// - Function name (e.g., "SpecService.CreateSpec")
// - File path (e.g., "/app/internal/specmgr/service.go")
// - Line number (e.g., 142)
//
// Example output:
// {"time":"2026-01-30T10:00:00Z","level":"INFO","source":{"function":"specmgr.(*SpecService).CreateSpec","file":"/app/internal/specmgr/service.go","line":142},"msg":"spec created"}

// Option configures the logger
type Option func(*Config)

// WithLevel sets the minimum log level
func WithLevel(level Level) Option {
    return func(c *Config) {
        c.Level = level
    }
}

// WithFormat sets the output format
func WithFormat(format Format) Option {
    return func(c *Config) {
        c.Format = format
    }
}

// WithOutput sets the output destination
func WithOutput(w io.Writer) Option {
    return func(c *Config) {
        c.Output = w
    }
}

// WithSource enables source file/line logging
func WithSource(enabled bool) Option {
    return func(c *Config) {
        c.AddSource = enabled
    }
}

// WithService sets service metadata
func WithService(name, version string) Option {
    return func(c *Config) {
        c.ServiceName = name
        c.Version = version
    }
}

// DefaultConfig returns sensible defaults
// CRITICAL: AddSource is ALWAYS true - this is mandatory
func DefaultConfig() Config {
    return Config{
        Level:     LevelInfo,
        Format:    FormatJSON,
        Output:    os.Stdout,
        AddSource: true, // MANDATORY: Always log function name, file, line number
    }
}

// slogLogger wraps slog.Logger to implement Logger
type slogLogger struct {
    *slog.Logger
}

// New creates a new Logger with the given options
func New(opts ...Option) Logger {
    cfg := DefaultConfig()
    for _, opt := range opts {
        opt(&cfg)
    }
    
    var handler slog.Handler
    
    handlerOpts := &slog.HandlerOptions{
        Level:     cfg.Level,
        AddSource: cfg.AddSource,
        ReplaceAttr: func(groups []string, a slog.Attr) slog.Attr {
            // Customize time format
            if a.Key == slog.TimeKey {
                return slog.Attr{
                    Key:   "timestamp",
                    Value: a.Value,
                }
            }
            return a
        },
    }
    
    switch cfg.Format {
    case FormatJSON:
        handler = slog.NewJSONHandler(cfg.Output, handlerOpts)
    case FormatText:
        handler = slog.NewTextHandler(cfg.Output, handlerOpts)
    default:
        handler = slog.NewJSONHandler(cfg.Output, handlerOpts)
    }
    
    // Wrap with context extractor
    handler = NewContextHandler(handler)
    
    logger := slog.New(handler)
    
    // Add service metadata if configured
    if cfg.ServiceName != "" {
        logger = logger.With(
            slog.String("service", cfg.ServiceName),
            slog.String("version", cfg.Version),
        )
    }
    
    return &slogLogger{Logger: logger}
}

// NewNoop creates a logger that discards all output
func NewNoop() Logger {
    return &slogLogger{
        Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
    }
}

// Implementation methods
func (l *slogLogger) Debug(msg string, args ...any) { l.Logger.Debug(msg, args...) }
func (l *slogLogger) Info(msg string, args ...any)  { l.Logger.Info(msg, args...) }
func (l *slogLogger) Warn(msg string, args ...any)  { l.Logger.Warn(msg, args...) }
func (l *slogLogger) Error(msg string, args ...any) { l.Logger.Error(msg, args...) }

func (l *slogLogger) DebugContext(ctx context.Context, msg string, args ...any) {
    l.Logger.DebugContext(ctx, msg, args...)
}
func (l *slogLogger) InfoContext(ctx context.Context, msg string, args ...any) {
    l.Logger.InfoContext(ctx, msg, args...)
}
func (l *slogLogger) WarnContext(ctx context.Context, msg string, args ...any) {
    l.Logger.WarnContext(ctx, msg, args...)
}
func (l *slogLogger) ErrorContext(ctx context.Context, msg string, args ...any) {
    l.Logger.ErrorContext(ctx, msg, args...)
}

func (l *slogLogger) With(args ...any) Logger {
    return &slogLogger{Logger: l.Logger.With(args...)}
}

func (l *slogLogger) WithGroup(name string) Logger {
    return &slogLogger{Logger: l.Logger.WithGroup(name)}
}

func (l *slogLogger) Enabled(ctx context.Context, level Level) bool {
    return l.Logger.Enabled(ctx, level)
}

// Default is the package-level default logger
var Default Logger = New()

// SetDefault replaces the default logger
func SetDefault(l Logger) {
    Default = l
}
```

---

## context.go

```go
package logging

import (
    "context"
    "log/slog"
)

// Context keys
type contextKey string

const (
    requestIDKey   contextKey = "request_id"
    userIDKey      contextKey = "user_id"
    correlationKey contextKey = "correlation_id"
    spanIDKey      contextKey = "span_id"
    traceIDKey     contextKey = "trace_id"
    logFieldsKey   contextKey = "log_fields"
)

// WithRequestID adds request ID to context
func WithRequestID(ctx context.Context, requestID string) context.Context {
    return context.WithValue(ctx, requestIDKey, requestID)
}

// GetRequestID retrieves request ID from context
func GetRequestID(ctx context.Context) string {
    if v := ctx.Value(requestIDKey); v != nil {
        return v.(string)
    }
    return ""
}

// WithUserID adds user ID to context
func WithUserID(ctx context.Context, userID string) context.Context {
    return context.WithValue(ctx, userIDKey, userID)
}

// GetUserID retrieves user ID from context
func GetUserID(ctx context.Context) string {
    if v := ctx.Value(userIDKey); v != nil {
        return v.(string)
    }
    return ""
}

// WithCorrelationID adds correlation ID to context
func WithCorrelationID(ctx context.Context, correlationID string) context.Context {
    return context.WithValue(ctx, correlationKey, correlationID)
}

// GetCorrelationID retrieves correlation ID from context
func GetCorrelationID(ctx context.Context) string {
    if v := ctx.Value(correlationKey); v != nil {
        return v.(string)
    }
    return ""
}

// WithTraceContext adds trace and span IDs
func WithTraceContext(ctx context.Context, traceID, spanID string) context.Context {
    ctx = context.WithValue(ctx, traceIDKey, traceID)
    ctx = context.WithValue(ctx, spanIDKey, spanID)
    return ctx
}

// WithFields adds arbitrary log fields to context
func WithFields(ctx context.Context, fields ...any) context.Context {
    existing := getFields(ctx)
    combined := make([]any, 0, len(existing)+len(fields))
    combined = append(combined, existing...)
    combined = append(combined, fields...)
    return context.WithValue(ctx, logFieldsKey, combined)
}

func getFields(ctx context.Context) []any {
    if v := ctx.Value(logFieldsKey); v != nil {
        return v.([]any)
    }
    return nil
}

// ContextHandler extracts context values and adds them to log records
type ContextHandler struct {
    handler slog.Handler
}

// NewContextHandler wraps a handler with context extraction
func NewContextHandler(h slog.Handler) *ContextHandler {
    return &ContextHandler{handler: h}
}

// Enabled delegates to the wrapped handler
func (h *ContextHandler) Enabled(ctx context.Context, level slog.Level) bool {
    return h.handler.Enabled(ctx, level)
}

// Handle extracts context values and adds them to the record
func (h *ContextHandler) Handle(ctx context.Context, r slog.Record) error {
    // Add request ID
    if requestID := GetRequestID(ctx); requestID != "" {
        r.AddAttrs(slog.String("request_id", requestID))
    }
    
    // Add user ID
    if userID := GetUserID(ctx); userID != "" {
        r.AddAttrs(slog.String("user_id", userID))
    }
    
    // Add correlation ID
    if correlationID := GetCorrelationID(ctx); correlationID != "" {
        r.AddAttrs(slog.String("correlation_id", correlationID))
    }
    
    // Add trace context
    if traceID := ctx.Value(traceIDKey); traceID != nil {
        r.AddAttrs(slog.String("trace_id", traceID.(string)))
    }
    if spanID := ctx.Value(spanIDKey); spanID != nil {
        r.AddAttrs(slog.String("span_id", spanID.(string)))
    }
    
    // Add custom fields
    if fields := getFields(ctx); len(fields) > 0 {
        attrs := argsToAttrs(fields)
        r.AddAttrs(attrs...)
    }
    
    return h.handler.Handle(ctx, r)
}

// WithAttrs returns a new handler with attrs
func (h *ContextHandler) WithAttrs(attrs []slog.Attr) slog.Handler {
    return &ContextHandler{handler: h.handler.WithAttrs(attrs)}
}

// WithGroup returns a new handler with group
func (h *ContextHandler) WithGroup(name string) slog.Handler {
    return &ContextHandler{handler: h.handler.WithGroup(name)}
}

func argsToAttrs(args []any) []slog.Attr {
    var attrs []slog.Attr
    for i := 0; i < len(args); i += 2 {
        if i+1 < len(args) {
            if key, ok := args[i].(string); ok {
                attrs = append(attrs, slog.Any(key, args[i+1]))
            }
        }
    }
    return attrs
}

// FromContext returns a logger with context fields
func FromContext(ctx context.Context, logger Logger) Logger {
    // Build args from context
    var args []any
    
    if requestID := GetRequestID(ctx); requestID != "" {
        args = append(args, "request_id", requestID)
    }
    if userID := GetUserID(ctx); userID != "" {
        args = append(args, "user_id", userID)
    }
    if fields := getFields(ctx); len(fields) > 0 {
        args = append(args, fields...)
    }
    
    if len(args) > 0 {
        return logger.With(args...)
    }
    return logger
}
```

---

## middleware.go

```go
package logging

import (
    "net/http"
    "time"
    
    "github.com/google/uuid"
)

// Middleware returns HTTP middleware for request logging
func Middleware(logger Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            start := time.Now()
            
            // Generate or extract request ID
            requestID := r.Header.Get("X-Request-ID")
            if requestID == "" {
                requestID = uuid.New().String()
            }
            
            // Extract correlation ID
            correlationID := r.Header.Get("X-Correlation-ID")
            
            // Add to context
            ctx := WithRequestID(r.Context(), requestID)
            if correlationID != "" {
                ctx = WithCorrelationID(ctx, correlationID)
            }
            
            // Set response header
            w.Header().Set("X-Request-ID", requestID)
            
            // Wrap response writer to capture status
            wrapped := &responseWriter{ResponseWriter: w, status: 200}
            
            // Log request start
            logger.InfoContext(ctx, "request_started",
                "method", r.Method,
                "path", r.URL.Path,
                "remote_addr", r.RemoteAddr,
                "user_agent", r.UserAgent(),
            )
            
            // Call next handler
            next.ServeHTTP(wrapped, r.WithContext(ctx))
            
            // Log request completion
            duration := time.Since(start)
            logger.InfoContext(ctx, "request_completed",
                "method", r.Method,
                "path", r.URL.Path,
                "status", wrapped.status,
                "bytes", wrapped.bytes,
                "duration_ms", duration.Milliseconds(),
            )
        })
    }
}

// responseWriter wraps http.ResponseWriter to capture status
type responseWriter struct {
    http.ResponseWriter
    status int
    bytes  int
}

func (w *responseWriter) WriteHeader(status int) {
    w.status = status
    w.ResponseWriter.WriteHeader(status)
}

func (w *responseWriter) Write(b []byte) (int, error) {
    n, err := w.ResponseWriter.Write(b)
    w.bytes += n
    return n, err
}

// Flush implements http.Flusher
func (w *responseWriter) Flush() {
    if flusher, ok := w.ResponseWriter.(http.Flusher); ok {
        flusher.Flush()
    }
}

// RecoveryMiddleware logs panics and returns 500
func RecoveryMiddleware(logger Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            defer func() {
                if err := recover(); err != nil {
                    logger.ErrorContext(r.Context(), "panic_recovered",
                        "error", err,
                        "method", r.Method,
                        "path", r.URL.Path,
                    )
                    http.Error(w, "Internal Server Error", http.StatusInternalServerError)
                }
            }()
            next.ServeHTTP(w, r)
        })
    }
}

// DebugMiddleware logs detailed request/response info (dev only)
func DebugMiddleware(logger Logger) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Log headers
            logger.DebugContext(r.Context(), "request_headers",
                "headers", r.Header,
            )
            
            next.ServeHTTP(w, r)
        })
    }
}
```

---

## fields.go

```go
package logging

import "log/slog"

// Standard field keys for consistent logging
const (
    // Request fields
    FieldRequestID     = "request_id"
    FieldCorrelationID = "correlation_id"
    FieldUserID        = "user_id"
    FieldMethod        = "method"
    FieldPath          = "path"
    FieldStatus        = "status"
    FieldDuration      = "duration_ms"
    FieldBytes         = "bytes"
    
    // Error fields
    FieldError      = "error"
    FieldErrorCode  = "error_code"
    FieldErrorType  = "error_type"
    FieldStackTrace = "stack_trace"
    
    // Entity fields
    FieldProjectID      = "project_id"
    FieldSpecID         = "spec_id"
    FieldConversationID = "conversation_id"
    FieldBlockID        = "block_id"
    FieldExecutionID    = "execution_id"
    
    // Operation fields
    FieldOperation = "operation"
    FieldComponent = "component"
    FieldAction    = "action"
    
    // Database fields
    FieldQuery    = "query"
    FieldTable    = "table"
    FieldRowCount = "row_count"
    
    // External service fields
    FieldService  = "service"
    FieldEndpoint = "endpoint"
    FieldTimeout  = "timeout"
)

// Err creates an error attribute
func Err(err error) slog.Attr {
    return slog.Any(FieldError, err)
}

// RequestID creates a request ID attribute
func RequestID(id string) slog.Attr {
    return slog.String(FieldRequestID, id)
}

// UserID creates a user ID attribute
func UserID(id string) slog.Attr {
    return slog.String(FieldUserID, id)
}

// ProjectID creates a project ID attribute
func ProjectID(id string) slog.Attr {
    return slog.String(FieldProjectID, id)
}

// Operation creates an operation attribute
func Operation(op string) slog.Attr {
    return slog.String(FieldOperation, op)
}

// Component creates a component attribute
func Component(comp string) slog.Attr {
    return slog.String(FieldComponent, comp)
}

// Duration creates a duration attribute in milliseconds
func Duration(ms int64) slog.Attr {
    return slog.Int64(FieldDuration, ms)
}

// Query creates a database query attribute (sanitized)
func Query(q string) slog.Attr {
    // Truncate long queries
    if len(q) > 500 {
        q = q[:500] + "..."
    }
    return slog.String(FieldQuery, q)
}

// Service creates an external service attribute
func Service(name string) slog.Attr {
    return slog.String(FieldService, name)
}
```

---

## Usage Examples

### Basic Usage

```go
// Create logger
logger := logging.New(
    logging.WithLevel(logging.LevelDebug),
    logging.WithFormat(logging.FormatJSON),
    logging.WithService("specmanager", "1.0.0"),
)

// Simple logging
logger.Info("server started", "port", 8080)
logger.Error("database connection failed", logging.Err(err))

// With child logger
dbLogger := logger.With("component", "database")
dbLogger.Debug("query executed", "table", "specs", "rows", 42)
```

### Context-Aware Logging

```go
func HandleRequest(w http.ResponseWriter, r *http.Request) {
    ctx := r.Context()
    
    // Context already has request_id from middleware
    logger.InfoContext(ctx, "processing request")
    
    // Add more context
    ctx = logging.WithUserID(ctx, user.ID)
    ctx = logging.WithFields(ctx, "project_id", projectID)
    
    // All logs will include these fields
    logger.InfoContext(ctx, "loading project")
}
```

### HTTP Server Setup

```go
func main() {
    logger := logging.New(
        logging.WithService("gateway", "1.0.0"),
    )
    
    mux := http.NewServeMux()
    mux.HandleFunc("/api/specs", handleSpecs)
    
    // Apply middleware
    handler := logging.Middleware(logger)(mux)
    handler = logging.RecoveryMiddleware(logger)(handler)
    
    http.ListenAndServe(":8080", handler)
}
```

### Output Format

**JSON Output (Production):**
```json
{
  "timestamp": "2026-01-30T10:30:00Z",
  "level": "INFO",
  "msg": "request_completed",
  "service": "gateway",
  "version": "1.0.0",
  "request_id": "abc-123",
  "method": "GET",
  "path": "/api/specs",
  "status": 200,
  "duration_ms": 45
}
```

**Text Output (Development):**
```
2026-01-30T10:30:00Z INFO request_completed service=gateway request_id=abc-123 method=GET path=/api/specs status=200 duration_ms=45
```
