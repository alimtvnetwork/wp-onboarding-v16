# Shared Pkg Modules

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-30  

---

## Overview

The `pkg/` directory contains shared Go packages that provide the architectural foundation for all microservices. These modules ensure consistent error handling, database operations, logging, configuration management, and type definitions across the entire system.

**Cross-References:**
- [Gateway Service](./01-gateway-service.md)
- [AI Bridge CLI](./12-ai-bridge-cli.md)
- [Voice CLI](./10-voice-cli.md)
- [Nexus-Flow](./09-nexus-flow-standalone-architecture.md)
- [Error Management](../../06-error-management/00-overview.md)

---

## Module Overview

```
pkg/
├── errors/          # Structured error handling with stack traces
├── database/        # SQLite connection pooling and dynamic routing
├── logging/         # Structured logging with source attribution
├── config/          # Hierarchical configuration management
├── types/           # Shared DTOs and identifiers
├── middleware/      # Reusable HTTP middleware
├── validation/      # Input validation utilities
├── crypto/          # Encryption and hashing utilities
└── testutil/        # Testing helpers and mocks
```

---

## 1. pkg/errors

Structured error handling with mandatory stack trace capture.

### Core Types

```go
package errors

import (
    "fmt"
    "runtime"
    "strings"
    "time"
)

// StackFrame represents a single frame in the stack trace
type StackFrame struct {
    Function string `json:"function"`
    File     string `json:"file"`
    Line     int    `json:"line"`
}

// AppError is the standard error type for all services
type AppError struct {
    Code       int                    `json:"code"`
    Constant   string                 `json:"constant"`
    Message    string                 `json:"message"`
    Details    map[string]interface{} `json:"details,omitempty"`
    Retryable  bool                   `json:"retryable"`
    Stack      []StackFrame           `json:"stack,omitempty"`
    Cause      error                  `json:"-"`
    CauseMsg   string                 `json:"cause,omitempty"`
    Timestamp  time.Time              `json:"timestamp"`
    RequestID  string                 `json:"requestId,omitempty"`
    ServiceName string               `json:"service,omitempty"`
}

const (
    DefaultStackDepth = 40
    MaxStackDepth     = 100
)

// NewAppError creates a new AppError with stack trace
func NewAppError(code int, constant, message string) *AppError {
    err := &AppError{
        Code:      code,
        Constant:  constant,
        Message:   message,
        Details:   make(map[string]interface{}),
        Timestamp: time.Now().UTC(),
        Stack:     captureStack(2, DefaultStackDepth),
    }
    return err
}

// NewAppErrorf creates a new AppError with formatted message
func NewAppErrorf(code int, constant, format string, args ...interface{}) *AppError {
    return NewAppError(code, constant, fmt.Sprintf(format, args...))
}

// captureStack captures the current stack trace
func captureStack(skip, depth int) []StackFrame {
    if depth > MaxStackDepth {
        depth = MaxStackDepth
    }
    
    frames := make([]StackFrame, 0, depth)
    pcs := make([]uintptr, depth)
    n := runtime.Callers(skip+1, pcs)
    
    callersFrames := runtime.CallersFrames(pcs[:n])
    for {
        frame, more := callersFrames.Next()
        
        // Skip runtime and reflect packages
        if strings.Contains(frame.Function, "runtime.") ||
           strings.Contains(frame.Function, "reflect.") {
            if !more {
                break
            }
            continue
        }
        
        frames = append(frames, StackFrame{
            Function: frame.Function,
            File:     frame.File,
            Line:     frame.Line,
        })
        
        if !more || len(frames) >= depth {
            break
        }
    }
    
    return frames
}

// Error implements the error interface
func (e *AppError) Error() string {
    if e.Cause != nil {
        return fmt.Sprintf("[%s] %s: %v", e.Constant, e.Message, e.Cause)
    }
    return fmt.Sprintf("[%s] %s", e.Constant, e.Message)
}

// Unwrap returns the underlying cause
func (e *AppError) Unwrap() error {
    return e.Cause
}

// WithDetails adds details to the error
func (e *AppError) WithDetails(details map[string]interface{}) *AppError {
    for k, v := range details {
        e.Details[k] = v
    }
    return e
}

// WithDetail adds a single detail
func (e *AppError) WithDetail(key string, value interface{}) *AppError {
    e.Details[key] = value
    return e
}

// WithCause wraps another error
func (e *AppError) WithCause(cause error) *AppError {
    e.Cause = cause
    if cause != nil {
        e.CauseMsg = cause.Error()
    }
    return e
}

// SetRetryable sets the retryable flag
func (e *AppError) SetRetryable(retryable bool) *AppError {
    e.Retryable = retryable
    return e
}

// WithRequestID sets the request ID
func (e *AppError) WithRequestID(requestID string) *AppError {
    e.RequestID = requestID
    return e
}

// WithService sets the service name
func (e *AppError) WithService(service string) *AppError {
    e.ServiceName = service
    return e
}

// Is checks if the error matches a code
func (e *AppError) Is(code int) bool {
    return e.Code == code
}

// IsRetryable returns whether the error is retryable
func (e *AppError) IsRetryable() bool {
    return e.Retryable
}

// ToJSON returns a JSON-serializable map
func (e *AppError) ToJSON(includeStack bool) map[string]interface{} {
    result := map[string]interface{}{
        "code":      e.Code,
        "constant":  e.Constant,
        "message":   e.Message,
        "retryable": e.Retryable,
        "timestamp": e.Timestamp.Format(time.RFC3339),
    }
    
    if len(e.Details) > 0 {
        result["details"] = e.Details
    }
    
    if e.CauseMsg != "" {
        result["cause"] = e.CauseMsg
    }
    
    if e.RequestID != "" {
        result["requestId"] = e.RequestID
    }
    
    if includeStack && len(e.Stack) > 0 {
        stackStrings := make([]string, len(e.Stack))
        for i, frame := range e.Stack {
            stackStrings[i] = fmt.Sprintf("%s (%s:%d)", frame.Function, frame.File, frame.Line)
        }
        result["stack"] = stackStrings
    }
    
    return result
}
```

### Error Wrapping Utilities

```go
package errors

import (
    "errors"
    "fmt"
)

// Wrap wraps an error with an AppError
func Wrap(err error, code int, constant, message string) *AppError {
    if err == nil {
        return nil
    }
    
    appErr := NewAppError(code, constant, message)
    appErr.Cause = err
    appErr.CauseMsg = err.Error()
    
    // If wrapping another AppError, inherit details
    var existingAppErr *AppError
    if errors.As(err, &existingAppErr) {
        for k, v := range existingAppErr.Details {
            if _, exists := appErr.Details[k]; !exists {
                appErr.Details[k] = v
            }
        }
    }
    
    return appErr
}

// Wrapf wraps an error with formatted message
func Wrapf(err error, code int, constant, format string, args ...interface{}) *AppError {
    return Wrap(err, code, constant, fmt.Sprintf(format, args...))
}

// AsAppError extracts an AppError from an error chain
func AsAppError(err error) (*AppError, bool) {
    var appErr *AppError
    if errors.As(err, &appErr) {
        return appErr, true
    }
    return nil, false
}

// IsCode checks if error chain contains a specific code
func IsCode(err error, code int) bool {
    appErr, ok := AsAppError(err)
    return ok && appErr.Code == code
}

// GetCode returns the error code or 0
func GetCode(err error) int {
    if appErr, ok := AsAppError(err); ok {
        return appErr.Code
    }
    return 0
}
```

### Service-Specific Error Codes

```go
package errors

// Shared Error Codes (1xxx)
const (
    ERR_VALIDATION_REQUIRED = 1001
    ERR_VALIDATION_FORMAT   = 1002
    ERR_VALIDATION_RANGE    = 1003
    ERR_VALIDATION_LENGTH   = 1004
    ERR_VALIDATION_TYPE     = 1005
    ERR_VALIDATION_UNIQUE   = 1006
    ERR_VALIDATION_BATCH    = 1010
)

// Gateway Error Codes (2xxx)
const (
    ERR_GATEWAY_GENERAL           = 2000
    ERR_GATEWAY_PANIC             = 2001
    ERR_GATEWAY_TIMEOUT           = 2002
    ERR_GATEWAY_UNAUTHORIZED      = 2010
    ERR_GATEWAY_FORBIDDEN         = 2011
    ERR_GATEWAY_TOKEN_EXPIRED     = 2012
    ERR_GATEWAY_TOKEN_INVALID     = 2013
    ERR_GATEWAY_API_KEY_INVALID   = 2014
    ERR_GATEWAY_RATE_LIMITED      = 2020
    ERR_GATEWAY_SERVICE_NOT_FOUND = 2030
    ERR_GATEWAY_SERVICE_UNAVAIL   = 2031
    ERR_GATEWAY_CIRCUIT_OPEN      = 2032
    ERR_GATEWAY_PROXY_ERROR       = 2033
    ERR_GATEWAY_UPSTREAM_ERROR    = 2034
    ERR_GATEWAY_VALIDATION        = 2040
    ERR_GATEWAY_BAD_REQUEST       = 2041
)

// SpecManager Error Codes (3xxx)
const (
    ERR_SPEC_GENERAL        = 3000
    ERR_SPEC_NOT_FOUND      = 3001
    ERR_SPEC_ALREADY_EXISTS = 3002
    ERR_SPEC_INVALID        = 3003
    ERR_SPEC_LOCKED         = 3004
    ERR_PROJECT_NOT_FOUND   = 3010
    ERR_PROJECT_EXISTS      = 3011
    ERR_PROJECT_INVALID     = 3012
    ERR_FILE_NOT_FOUND      = 3020
    ERR_FILE_READ_ERROR     = 3021
    ERR_FILE_WRITE_ERROR    = 3022
    ERR_FILE_DELETE_ERROR   = 3023
    ERR_PATH_TRAVERSAL      = 3030
    ERR_PATH_INVALID        = 3031
)

// Chronicle Error Codes (4xxx)
const (
    ERR_CHRONICLE_GENERAL     = 4000
    ERR_COMMIT_NOT_FOUND      = 4001
    ERR_COMMIT_INVALID        = 4002
    ERR_DIFF_GENERATION       = 4010
    ERR_ROLLBACK_FAILED       = 4011
    ERR_GIT_OPERATION         = 4020
    ERR_GIT_NOT_INITIALIZED   = 4021
)

// Business Logic Error Codes (5xxx)
const (
    ERR_LOGIC_STATE    = 5001
    ERR_LOGIC_LIMIT    = 5002
    ERR_LOGIC_CONFLICT = 5003
    ERR_SPEC_CIRCULAR  = 5011
    ERR_SPEC_MISSING   = 5012
)

// AI-Bridge Error Codes (6xxx)
const (
    ERR_AI_GENERAL              = 6000
    ERR_AI_PROVIDER_UNAVAILABLE = 6001
    ERR_AI_MODEL_NOT_FOUND      = 6002
    ERR_AI_INFERENCE_FAILED     = 6003
    ERR_AI_CONTEXT_EXCEEDED     = 6004
    ERR_AI_RATE_LIMITED         = 6005
    ERR_AI_APP_NOT_FOUND        = 6010
    ERR_AI_APP_EXISTS           = 6011
    ERR_AI_PROJECT_NOT_FOUND    = 6012
    ERR_AI_CONV_NOT_FOUND       = 6013
    ERR_AI_MEMORY_ADD_FAILED    = 6020
    ERR_AI_MEMORY_SEARCH_FAILED = 6021
    ERR_AI_EMBEDDING_FAILED     = 6022
)

// Configuration Error Codes (7xxx)
const (
    ERR_CONFIG_GENERAL   = 7000
    ERR_CONFIG_NOT_FOUND = 7001
    ERR_CONFIG_INVALID   = 7002
    ERR_CONFIG_READONLY  = 7003
)

// Security Error Codes (8xxx)
const (
    ERR_SECURITY_GENERAL    = 8000
    ERR_SECURITY_SSRF       = 8001
    ERR_SECURITY_TRAVERSAL  = 8002
    ERR_SECURITY_PERMISSION = 8003
    ERR_SECURITY_ENCRYPTED  = 8004
)

// System Error Codes (9xxx)
const (
    ERR_SYSTEM_GENERAL    = 9000
    ERR_SYSTEM_DB         = 9001
    ERR_SYSTEM_DISK_FULL  = 9002
    ERR_SYSTEM_MEMORY     = 9003
    ERR_SYSTEM_NETWORK    = 9004
)

// Nexus-Flow Error Codes (10xxx)
const (
    ERR_FLOW_GENERAL         = 10000
    ERR_FLOW_NOT_FOUND       = 10001
    ERR_FLOW_INVALID         = 10002
    ERR_FLOW_EXECUTION       = 10020
    ERR_STAGE_NOT_FOUND      = 10010
    ERR_STAGE_EXECUTION      = 10011
    ERR_PIPELINE_NOT_FOUND   = 10030
    ERR_PIPELINE_RUNNING     = 10031
)

// Voice-CLI Error Codes (11xxx)
const (
    ERR_VOICE_GENERAL          = 11000
    ERR_VOICE_SESSION          = 11001
    ERR_VOICE_TRANSCRIPTION    = 11020
    ERR_VOICE_PROVIDER_UNAVAIL = 11021
    ERR_VOICE_AUDIO_INVALID    = 11022
    ERR_VOICE_COMMAND_UNKNOWN  = 11030
    ERR_VOICE_COMMAND_FAILED   = 11031
)
```

---

## 2. pkg/database

SQLite connection pooling and dynamic project database routing.

### Connection Manager

```go
package database

import (
    "context"
    "database/sql"
    "fmt"
    "path/filepath"
    "sync"
    "time"
    
    _ "github.com/mattn/go-sqlite3"
    
    "github.com/user/pkg/errors"
    "github.com/user/pkg/logging"
)

// ConnectionConfig holds database connection settings
type ConnectionConfig struct {
    MaxOpenConns    int
    MaxIdleConns    int
    ConnMaxLifetime time.Duration
    ConnMaxIdleTime time.Duration
    EnableWAL       bool
    BusyTimeout     int // milliseconds
    CacheSize       int // negative = KB, positive = pages
}

// DefaultConfig returns sensible defaults
func DefaultConfig() ConnectionConfig {
    return ConnectionConfig{
        MaxOpenConns:    10,
        MaxIdleConns:    5,
        ConnMaxLifetime: 30 * time.Minute,
        ConnMaxIdleTime: 5 * time.Minute,
        EnableWAL:       true,
        BusyTimeout:     5000,
        CacheSize:       -20000, // 20MB
    }
}

// ConnectionManager manages database connections
type ConnectionManager struct {
    rootPath    string
    config      ConnectionConfig
    connections sync.Map // map[string]*sql.DB
    logger      *logging.Logger
    mu          sync.RWMutex
}

// NewConnectionManager creates a new connection manager
func NewConnectionManager(rootPath string, config ConnectionConfig, logger *logging.Logger) *ConnectionManager {
    return &ConnectionManager{
        rootPath: rootPath,
        config:   config,
        logger:   logger,
    }
}

// GetConnection returns a connection to a database
func (cm *ConnectionManager) GetConnection(ctx context.Context, dbPath string) (*sql.DB, error) {
    fullPath := cm.resolvePath(dbPath)
    
    // Check cache first
    if conn, ok := cm.connections.Load(fullPath); ok {
        db := conn.(*sql.DB)
        if err := db.PingContext(ctx); err == nil {
            return db, nil
        }
        // Connection is stale, remove it
        cm.connections.Delete(fullPath)
    }
    
    // Create new connection
    cm.mu.Lock()
    defer cm.mu.Unlock()
    
    // Double-check after acquiring lock
    if conn, ok := cm.connections.Load(fullPath); ok {
        return conn.(*sql.DB), nil
    }
    
    db, err := cm.openDatabase(ctx, fullPath)
    if err != nil {
        return nil, err
    }
    
    cm.connections.Store(fullPath, db)
    cm.logger.Debug(ctx, "Database connection opened",
        "path", fullPath,
    )
    
    return db, nil
}

// openDatabase opens a new database connection
func (cm *ConnectionManager) openDatabase(ctx context.Context, fullPath string) (*sql.DB, error) {
    dsn := cm.buildDSN(fullPath)
    
    db, err := sql.Open("sqlite3", dsn)
    if err != nil {
        return nil, errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to open database")
    }
    
    // Configure connection pool
    db.SetMaxOpenConns(cm.config.MaxOpenConns)
    db.SetMaxIdleConns(cm.config.MaxIdleConns)
    db.SetConnMaxLifetime(cm.config.ConnMaxLifetime)
    db.SetConnMaxIdleTime(cm.config.ConnMaxIdleTime)
    
    // Enable WAL mode
    if cm.config.EnableWAL {
        if _, err := db.ExecContext(ctx, "PRAGMA journal_mode=WAL"); err != nil {
            db.Close()
            return nil, errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
                "Failed to enable WAL mode")
        }
    }
    
    // Set cache size
    if _, err := db.ExecContext(ctx, fmt.Sprintf("PRAGMA cache_size=%d", cm.config.CacheSize)); err != nil {
        cm.logger.Warn(ctx, "Failed to set cache size", "error", err)
    }
    
    // Verify connection
    if err := db.PingContext(ctx); err != nil {
        db.Close()
        return nil, errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to ping database")
    }
    
    return db, nil
}

// buildDSN constructs the SQLite DSN
func (cm *ConnectionManager) buildDSN(fullPath string) string {
    return fmt.Sprintf("file:%s?_busy_timeout=%d&_foreign_keys=on&_journal_mode=WAL",
        fullPath, cm.config.BusyTimeout)
}

// resolvePath resolves a database path relative to root
func (cm *ConnectionManager) resolvePath(dbPath string) string {
    if filepath.IsAbs(dbPath) {
        return dbPath
    }
    return filepath.Join(cm.rootPath, dbPath)
}

// Close closes all connections
func (cm *ConnectionManager) Close() error {
    var lastErr error
    cm.connections.Range(func(key, value interface{}) bool {
        if db, ok := value.(*sql.DB); ok {
            if err := db.Close(); err != nil {
                lastErr = err
            }
        }
        cm.connections.Delete(key)
        return true
    })
    return lastErr
}

// HealthCheck checks database health
func (cm *ConnectionManager) HealthCheck(ctx context.Context, dbPath string) error {
    db, err := cm.GetConnection(ctx, dbPath)
    if err != nil {
        return err
    }
    
    var result int
    return db.QueryRowContext(ctx, "SELECT 1").Scan(&result)
}
```

### Transaction Helper

```go
package database

import (
    "context"
    "database/sql"
    
    "github.com/user/pkg/errors"
)

// TxFunc is a function that runs within a transaction
type TxFunc func(tx *sql.Tx) error

// WithTransaction executes a function within a transaction
func WithTransaction(ctx context.Context, db *sql.DB, fn TxFunc) error {
    tx, err := db.BeginTx(ctx, nil)
    if err != nil {
        return errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to begin transaction")
    }
    
    defer func() {
        if p := recover(); p != nil {
            tx.Rollback()
            panic(p)
        }
    }()
    
    if err := fn(tx); err != nil {
        if rbErr := tx.Rollback(); rbErr != nil {
            return errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
                "Transaction failed and rollback error").WithDetail("rollbackError", rbErr.Error())
        }
        return err
    }
    
    if err := tx.Commit(); err != nil {
        return errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to commit transaction")
    }
    
    return nil
}

// WithTransactionResult executes a function within a transaction and returns a result
func WithTransactionResult[T any](ctx context.Context, db *sql.DB, fn func(tx *sql.Tx) (T, error)) (T, error) {
    var result T
    
    tx, err := db.BeginTx(ctx, nil)
    if err != nil {
        return result, errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to begin transaction")
    }
    
    defer func() {
        if p := recover(); p != nil {
            tx.Rollback()
            panic(p)
        }
    }()
    
    result, err = fn(tx)
    if err != nil {
        tx.Rollback()
        return result, err
    }
    
    if err := tx.Commit(); err != nil {
        return result, errors.Wrap(err, errors.ERR_SYSTEM_DB, "ERR_SYSTEM_DB",
            "Failed to commit transaction")
    }
    
    return result, nil
}
```

### Dynamic Project Database Router

```go
package database

import (
    "context"
    "database/sql"
    "path/filepath"
    "sync"
)

// ProjectDBRouter routes to project-specific databases
type ProjectDBRouter struct {
    connManager *ConnectionManager
    appPath     string
    projectDBs  sync.Map // map[projectID]*sql.DB
    logger      *logging.Logger
}

// NewProjectDBRouter creates a project database router
func NewProjectDBRouter(connManager *ConnectionManager, appPath string, logger *logging.Logger) *ProjectDBRouter {
    return &ProjectDBRouter{
        connManager: connManager,
        appPath:     appPath,
        logger:      logger,
    }
}

// GetProjectDB returns the database for a specific project
func (r *ProjectDBRouter) GetProjectDB(ctx context.Context, projectID string) (*sql.DB, error) {
    // Check cache
    if db, ok := r.projectDBs.Load(projectID); ok {
        return db.(*sql.DB), nil
    }
    
    // Build path: {appPath}/projects/{projectID}.db
    dbPath := filepath.Join(r.appPath, "projects", projectID+".db")
    
    db, err := r.connManager.GetConnection(ctx, dbPath)
    if err != nil {
        return nil, err
    }
    
    r.projectDBs.Store(projectID, db)
    return db, nil
}

// GetAppDB returns the application-level database
func (r *ProjectDBRouter) GetAppDB(ctx context.Context) (*sql.DB, error) {
    dbPath := filepath.Join(r.appPath, "app.db")
    return r.connManager.GetConnection(ctx, dbPath)
}

// ListProjectDBs returns all project database paths
func (r *ProjectDBRouter) ListProjectDBs() []string {
    var paths []string
    r.projectDBs.Range(func(key, _ interface{}) bool {
        paths = append(paths, key.(string))
        return true
    })
    return paths
}
```

---

## 3. pkg/logging

Structured logging with mandatory source attribution.

### Logger

```go
package logging

import (
    "context"
    "io"
    "log/slog"
    "os"
    "runtime"
    "strings"
    "time"
)

// Level represents log levels
type Level = slog.Level

const (
    LevelDebug = slog.LevelDebug
    LevelInfo  = slog.LevelInfo
    LevelWarn  = slog.LevelWarn
    LevelError = slog.LevelError
)

// LoggerConfig holds logger configuration
type LoggerConfig struct {
    Level       Level
    Format      string // "json" or "text"
    Output      io.Writer
    ServiceName string
    Version     string
    AddSource   bool
}

// DefaultConfig returns default logger configuration
func DefaultConfig(serviceName string) LoggerConfig {
    return LoggerConfig{
        Level:       LevelInfo,
        Format:      "json",
        Output:      os.Stdout,
        ServiceName: serviceName,
        AddSource:   true, // CRITICAL: Always include source
    }
}

// Logger wraps slog.Logger with additional functionality
type Logger struct {
    *slog.Logger
    config LoggerConfig
}

// NewLogger creates a new logger
func NewLogger(config LoggerConfig) *Logger {
    var handler slog.Handler
    
    opts := &slog.HandlerOptions{
        Level:     config.Level,
        AddSource: config.AddSource, // CRITICAL: Must be true
        ReplaceAttr: func(groups []string, a slog.Attr) slog.Attr {
            // Customize source format
            if a.Key == slog.SourceKey {
                if src, ok := a.Value.Any().(*slog.Source); ok {
                    // Shorten file path
                    parts := strings.Split(src.File, "/")
                    if len(parts) > 3 {
                        parts = parts[len(parts)-3:]
                    }
                    src.File = strings.Join(parts, "/")
                }
            }
            return a
        },
    }
    
    output := config.Output
    if output == nil {
        output = os.Stdout
    }
    
    if config.Format == "json" {
        handler = slog.NewJSONHandler(output, opts)
    } else {
        handler = slog.NewTextHandler(output, opts)
    }
    
    // Add default attributes
    handler = handler.WithAttrs([]slog.Attr{
        slog.String("service", config.ServiceName),
        slog.String("version", config.Version),
    })
    
    return &Logger{
        Logger: slog.New(handler),
        config: config,
    }
}

// contextKey for request ID
type contextKey string

const (
    RequestIDKey contextKey = "requestId"
    TraceIDKey   contextKey = "traceId"
    UserIDKey    contextKey = "userId"
)

// WithContext extracts context values and adds them to log attributes
func (l *Logger) WithContext(ctx context.Context) *slog.Logger {
    attrs := []any{}
    
    if requestID := ctx.Value(RequestIDKey); requestID != nil {
        attrs = append(attrs, "requestId", requestID)
    }
    
    if traceID := ctx.Value(TraceIDKey); traceID != nil {
        attrs = append(attrs, "traceId", traceID)
    }
    
    if userID := ctx.Value(UserIDKey); userID != nil {
        attrs = append(attrs, "userId", userID)
    }
    
    return l.Logger.With(attrs...)
}

// Debug logs a debug message with context
func (l *Logger) Debug(ctx context.Context, msg string, args ...any) {
    l.WithContext(ctx).Debug(msg, args...)
}

// Info logs an info message with context
func (l *Logger) Info(ctx context.Context, msg string, args ...any) {
    l.WithContext(ctx).Info(msg, args...)
}

// Warn logs a warning message with context
func (l *Logger) Warn(ctx context.Context, msg string, args ...any) {
    l.WithContext(ctx).Warn(msg, args...)
}

// Error logs an error message with context and error details
func (l *Logger) Error(ctx context.Context, msg string, err error, args ...any) {
    logger := l.WithContext(ctx)
    
    // If it's an AppError, extract details
    if appErr, ok := err.(*errors.AppError); ok {
        args = append(args,
            "error.code", appErr.Code,
            "error.constant", appErr.Constant,
            "error.message", appErr.Message,
            "error.retryable", appErr.Retryable,
        )
        
        if len(appErr.Stack) > 0 {
            // Include first few stack frames
            stackPreview := make([]string, 0, 5)
            for i := 0; i < len(appErr.Stack) && i < 5; i++ {
                frame := appErr.Stack[i]
                stackPreview = append(stackPreview, 
                    fmt.Sprintf("%s (%s:%d)", frame.Function, frame.File, frame.Line))
            }
            args = append(args, "error.stack_preview", stackPreview)
        }
    } else if err != nil {
        args = append(args, "error", err.Error())
    }
    
    logger.Error(msg, args...)
}

// WithFields returns a logger with additional fields
func (l *Logger) WithFields(fields map[string]any) *Logger {
    args := make([]any, 0, len(fields)*2)
    for k, v := range fields {
        args = append(args, k, v)
    }
    
    return &Logger{
        Logger: l.Logger.With(args...),
        config: l.config,
    }
}

// GetCaller returns caller information
func GetCaller(skip int) (function, file string, line int) {
    pc, file, line, ok := runtime.Caller(skip + 1)
    if !ok {
        return "unknown", "unknown", 0
    }
    
    fn := runtime.FuncForPC(pc)
    if fn != nil {
        function = fn.Name()
    }
    
    return function, file, line
}
```

### Log Output Example

```json
{
    "time": "2026-01-30T12:34:56.789Z",
    "level": "ERROR",
    "source": {
        "function": "github.com/user/ai-bridge/internal/inference.(*Service).Complete",
        "file": "internal/inference/service.go",
        "line": 142
    },
    "msg": "Inference failed",
    "service": "ai-bridge",
    "version": "1.0.0",
    "requestId": "req_abc123",
    "traceId": "trace_xyz789",
    "error.code": 6003,
    "error.constant": "ERR_AI_INFERENCE_FAILED",
    "error.message": "Provider returned error",
    "error.retryable": true,
    "error.stack_preview": [
        "github.com/user/ai-bridge/internal/inference.(*Service).Complete (service.go:142)",
        "github.com/user/ai-bridge/internal/api.(*Handler).handleComplete (handler.go:89)",
        "net/http.HandlerFunc.ServeHTTP (server.go:2166)"
    ]
}
```

---

## 4. pkg/config

Hierarchical configuration management with validation.

### Configuration Manager

```go
package config

import (
    "context"
    "encoding/json"
    "fmt"
    "os"
    "path/filepath"
    "reflect"
    "strings"
    "sync"
    
    "github.com/user/pkg/errors"
)

// Source represents a configuration source
type Source int

const (
    SourceDefault Source = iota
    SourceFile
    SourceEnv
    SourceDatabase
    SourceOverride
)

// ConfigValue holds a configuration value with metadata
type ConfigValue struct {
    Key         string      `json:"key"`
    Value       interface{} `json:"value"`
    Type        string      `json:"type"`
    Source      Source      `json:"source"`
    Description string      `json:"description,omitempty"`
    IsSecret    bool        `json:"isSecret"`
}

// Manager manages hierarchical configuration
type Manager struct {
    values    map[string]*ConfigValue
    mu        sync.RWMutex
    validator Validator
}

// NewManager creates a new configuration manager
func NewManager() *Manager {
    return &Manager{
        values:    make(map[string]*ConfigValue),
        validator: NewValidator(),
    }
}

// LoadDefaults loads default configuration values
func (m *Manager) LoadDefaults(defaults map[string]interface{}) {
    m.mu.Lock()
    defer m.mu.Unlock()
    
    for key, value := range defaults {
        m.values[key] = &ConfigValue{
            Key:    key,
            Value:  value,
            Type:   reflect.TypeOf(value).String(),
            Source: SourceDefault,
        }
    }
}

// LoadFile loads configuration from a JSON file
func (m *Manager) LoadFile(path string) error {
    data, err := os.ReadFile(path)
    if err != nil {
        if os.IsNotExist(err) {
            return nil // File not found is OK
        }
        return errors.Wrap(err, errors.ERR_CONFIG_INVALID, "ERR_CONFIG_INVALID",
            "Failed to read config file")
    }
    
    var fileConfig map[string]interface{}
    if err := json.Unmarshal(data, &fileConfig); err != nil {
        return errors.Wrap(err, errors.ERR_CONFIG_INVALID, "ERR_CONFIG_INVALID",
            "Failed to parse config file")
    }
    
    m.mu.Lock()
    defer m.mu.Unlock()
    
    m.loadNested("", fileConfig, SourceFile)
    return nil
}

// loadNested recursively loads nested configuration
func (m *Manager) loadNested(prefix string, data map[string]interface{}, source Source) {
    for key, value := range data {
        fullKey := key
        if prefix != "" {
            fullKey = prefix + "." + key
        }
        
        if nested, ok := value.(map[string]interface{}); ok {
            m.loadNested(fullKey, nested, source)
        } else {
            m.values[fullKey] = &ConfigValue{
                Key:    fullKey,
                Value:  value,
                Type:   reflect.TypeOf(value).String(),
                Source: source,
            }
        }
    }
}

// LoadEnv loads configuration from environment variables
func (m *Manager) LoadEnv(prefix string) {
    m.mu.Lock()
    defer m.mu.Unlock()
    
    prefix = strings.ToUpper(prefix) + "_"
    
    for _, env := range os.Environ() {
        parts := strings.SplitN(env, "=", 2)
        if len(parts) != 2 {
            continue
        }
        
        key, value := parts[0], parts[1]
        if !strings.HasPrefix(key, prefix) {
            continue
        }
        
        // Convert ENV_VAR_NAME to env.var.name
        configKey := strings.ToLower(strings.ReplaceAll(
            strings.TrimPrefix(key, prefix), "_", "."))
        
        m.values[configKey] = &ConfigValue{
            Key:    configKey,
            Value:  value,
            Type:   "string",
            Source: SourceEnv,
        }
    }
}

// Get retrieves a configuration value
func (m *Manager) Get(key string) (interface{}, bool) {
    m.mu.RLock()
    defer m.mu.RUnlock()
    
    if cv, ok := m.values[key]; ok {
        return cv.Value, true
    }
    return nil, false
}

// GetString retrieves a string configuration value
func (m *Manager) GetString(key string, defaultValue string) string {
    if value, ok := m.Get(key); ok {
        if str, ok := value.(string); ok {
            return str
        }
        return fmt.Sprintf("%v", value)
    }
    return defaultValue
}

// GetInt retrieves an integer configuration value
func (m *Manager) GetInt(key string, defaultValue int) int {
    if value, ok := m.Get(key); ok {
        switch v := value.(type) {
        case int:
            return v
        case int64:
            return int(v)
        case float64:
            return int(v)
        case string:
            if i, err := strconv.Atoi(v); err == nil {
                return i
            }
        }
    }
    return defaultValue
}

// GetBool retrieves a boolean configuration value
func (m *Manager) GetBool(key string, defaultValue bool) bool {
    if value, ok := m.Get(key); ok {
        switch v := value.(type) {
        case bool:
            return v
        case string:
            return strings.ToLower(v) == "true" || v == "1"
        case int:
            return v != 0
        }
    }
    return defaultValue
}

// GetDuration retrieves a duration configuration value
func (m *Manager) GetDuration(key string, defaultValue time.Duration) time.Duration {
    if value, ok := m.Get(key); ok {
        switch v := value.(type) {
        case time.Duration:
            return v
        case string:
            if d, err := time.ParseDuration(v); err == nil {
                return d
            }
        case int:
            return time.Duration(v) * time.Second
        case float64:
            return time.Duration(v * float64(time.Second))
        }
    }
    return defaultValue
}

// Set sets a configuration value
func (m *Manager) Set(key string, value interface{}) {
    m.mu.Lock()
    defer m.mu.Unlock()
    
    m.values[key] = &ConfigValue{
        Key:    key,
        Value:  value,
        Type:   reflect.TypeOf(value).String(),
        Source: SourceOverride,
    }
}

// Validate validates configuration against rules
func (m *Manager) Validate(rules []ValidationRule) error {
    m.mu.RLock()
    defer m.mu.RUnlock()
    
    return m.validator.Validate(m.values, rules)
}

// All returns all configuration values
func (m *Manager) All() map[string]*ConfigValue {
    m.mu.RLock()
    defer m.mu.RUnlock()
    
    result := make(map[string]*ConfigValue)
    for k, v := range m.values {
        if v.IsSecret {
            // Mask secret values
            masked := *v
            masked.Value = "********"
            result[k] = &masked
        } else {
            result[k] = v
        }
    }
    return result
}
```

### Validation Rules

```go
package config

import (
    "fmt"
    "regexp"
)

// ValidationRule defines a configuration validation rule
type ValidationRule struct {
    Key       string
    Required  bool
    Type      string
    MinValue  *float64
    MaxValue  *float64
    Pattern   *regexp.Regexp
    Validator func(value interface{}) error
}

// Validator validates configuration values
type Validator struct{}

// NewValidator creates a new validator
func NewValidator() Validator {
    return Validator{}
}

// Validate validates configuration against rules
func (v Validator) Validate(values map[string]*ConfigValue, rules []ValidationRule) error {
    var errs []string
    
    for _, rule := range rules {
        cv, exists := values[rule.Key]
        
        if rule.Required && !exists {
            errs = append(errs, fmt.Sprintf("%s: required but not set", rule.Key))
            continue
        }
        
        if !exists {
            continue
        }
        
        // Type check
        if rule.Type != "" && cv.Type != rule.Type {
            errs = append(errs, fmt.Sprintf("%s: expected type %s, got %s",
                rule.Key, rule.Type, cv.Type))
        }
        
        // Range check for numbers
        if num, ok := toFloat64(cv.Value); ok {
            if rule.MinValue != nil && num < *rule.MinValue {
                errs = append(errs, fmt.Sprintf("%s: value %v is less than minimum %v",
                    rule.Key, num, *rule.MinValue))
            }
            if rule.MaxValue != nil && num > *rule.MaxValue {
                errs = append(errs, fmt.Sprintf("%s: value %v is greater than maximum %v",
                    rule.Key, num, *rule.MaxValue))
            }
        }
        
        // Pattern check for strings
        if str, ok := cv.Value.(string); ok && rule.Pattern != nil {
            if !rule.Pattern.MatchString(str) {
                errs = append(errs, fmt.Sprintf("%s: value does not match pattern %s",
                    rule.Key, rule.Pattern.String()))
            }
        }
        
        // Custom validator
        if rule.Validator != nil {
            if err := rule.Validator(cv.Value); err != nil {
                errs = append(errs, fmt.Sprintf("%s: %v", rule.Key, err))
            }
        }
    }
    
    if len(errs) > 0 {
        return errors.NewAppError(errors.ERR_CONFIG_INVALID, "ERR_CONFIG_INVALID",
            "Configuration validation failed").WithDetails(map[string]interface{}{
            "errors": errs,
        })
    }
    
    return nil
}
```

---

## 5. pkg/types

Shared DTOs and identifiers.

### ID Types

```go
package types

import (
    "crypto/rand"
    "encoding/base32"
    "fmt"
    "strings"
    "time"
)

// ID represents a prefixed unique identifier
type ID string

// IDPrefix defines valid ID prefixes
type IDPrefix string

const (
    PrefixApp          IDPrefix = "app_"
    PrefixProject      IDPrefix = "proj_"
    PrefixConversation IDPrefix = "conv_"
    PrefixMessage      IDPrefix = "msg_"
    PrefixMemory       IDPrefix = "mem_"
    PrefixModel        IDPrefix = "mod_"
    PrefixProvider     IDPrefix = "prov_"
    PrefixFlow         IDPrefix = "flow_"
    PrefixStage        IDPrefix = "stg_"
    PrefixTask         IDPrefix = "task_"
    PrefixSpec         IDPrefix = "spec_"
    PrefixCommit       IDPrefix = "cmt_"
    PrefixUser         IDPrefix = "usr_"
    PrefixSession      IDPrefix = "sess_"
    PrefixRequest      IDPrefix = "req_"
)

// NewID generates a new ID with the given prefix
func NewID(prefix IDPrefix) ID {
    timestamp := time.Now().UnixNano()
    randomBytes := make([]byte, 10)
    rand.Read(randomBytes)
    
    encoded := base32.StdEncoding.EncodeToString(randomBytes)
    encoded = strings.ToLower(strings.TrimRight(encoded, "="))
    
    return ID(fmt.Sprintf("%s%d%s", prefix, timestamp/1000000, encoded))
}

// Validate checks if the ID has a valid format
func (id ID) Validate(expectedPrefix IDPrefix) bool {
    return strings.HasPrefix(string(id), string(expectedPrefix)) && len(id) > len(expectedPrefix)+10
}

// String returns the string representation
func (id ID) String() string {
    return string(id)
}

// IsEmpty checks if the ID is empty
func (id ID) IsEmpty() bool {
    return id == ""
}
```

### Common DTOs

```go
package types

import "time"

// Pagination holds pagination information
type Pagination struct {
    Total   int  `json:"total"`
    Limit   int  `json:"limit"`
    Offset  int  `json:"offset"`
    HasMore bool `json:"hasMore"`
}

// NewPagination creates pagination from query params
func NewPagination(total, limit, offset int) Pagination {
    return Pagination{
        Total:   total,
        Limit:   limit,
        Offset:  offset,
        HasMore: offset+limit < total,
    }
}

// APIResponse is the standard API response envelope
type APIResponse[T any] struct {
    Success    bool        `json:"success"`
    Data       T           `json:"data,omitempty"`
    Error      *APIError   `json:"error,omitempty"`
    Pagination *Pagination `json:"pagination,omitempty"`
}

// APIError is the standard error format
type APIError struct {
    Code      int                    `json:"code"`
    Constant  string                 `json:"constant"`
    Message   string                 `json:"message"`
    Details   map[string]interface{} `json:"details,omitempty"`
    Retryable bool                   `json:"retryable"`
    Stack     []string               `json:"stack,omitempty"`
}

// TimestampedModel contains common timestamp fields
type TimestampedModel struct {
    CreatedAt time.Time  `json:"createdAt"`
    UpdatedAt time.Time  `json:"updatedAt"`
    DeletedAt *time.Time `json:"deletedAt,omitempty"`
}

// SoftDelete sets the deletion timestamp
func (t *TimestampedModel) SoftDelete() {
    now := time.Now()
    t.DeletedAt = &now
}

// Restore clears the deletion timestamp
func (t *TimestampedModel) Restore() {
    t.DeletedAt = nil
}

// IsDeleted checks if the model is soft deleted
func (t *TimestampedModel) IsDeleted() bool {
    return t.DeletedAt != nil
}

// SortDirection represents sort order
type SortDirection string

const (
    SortAsc  SortDirection = "asc"
    SortDesc SortDirection = "desc"
)

// SortOptions holds sorting configuration
type SortOptions struct {
    Field     string        `json:"field"`
    Direction SortDirection `json:"direction"`
}

// FilterOperator represents filter operations
type FilterOperator string

const (
    FilterEq       FilterOperator = "eq"
    FilterNe       FilterOperator = "ne"
    FilterGt       FilterOperator = "gt"
    FilterGte      FilterOperator = "gte"
    FilterLt       FilterOperator = "lt"
    FilterLte      FilterOperator = "lte"
    FilterContains FilterOperator = "contains"
    FilterIn       FilterOperator = "in"
)

// Filter represents a single filter condition
type Filter struct {
    Field    string         `json:"field"`
    Operator FilterOperator `json:"operator"`
    Value    interface{}    `json:"value"`
}

// QueryOptions combines pagination, sorting, and filtering
type QueryOptions struct {
    Pagination PaginationRequest `json:"pagination"`
    Sort       []SortOptions     `json:"sort,omitempty"`
    Filters    []Filter          `json:"filters,omitempty"`
}

// PaginationRequest holds pagination request parameters
type PaginationRequest struct {
    Limit  int `json:"limit"`
    Offset int `json:"offset"`
}

// Validate validates pagination parameters
func (p *PaginationRequest) Validate(maxLimit int) {
    if p.Limit <= 0 {
        p.Limit = 50
    }
    if p.Limit > maxLimit {
        p.Limit = maxLimit
    }
    if p.Offset < 0 {
        p.Offset = 0
    }
}
```

---

## 6. Usage Example

### Service Initialization

```go
package main

import (
    "context"
    "os"
    
    "github.com/user/pkg/config"
    "github.com/user/pkg/database"
    "github.com/user/pkg/errors"
    "github.com/user/pkg/logging"
)

func main() {
    // Initialize logger (MUST have AddSource: true)
    logConfig := logging.DefaultConfig("ai-bridge")
    logConfig.Version = "1.0.0"
    logger := logging.NewLogger(logConfig)
    
    // Initialize configuration
    cfg := config.NewManager()
    cfg.LoadDefaults(map[string]interface{}{
        "server.port":       8090,
        "server.host":       "0.0.0.0",
        "database.rootPath": "./data",
    })
    cfg.LoadFile("config.json")
    cfg.LoadEnv("AI_BRIDGE")
    
    // Validate configuration
    if err := cfg.Validate([]config.ValidationRule{
        {Key: "server.port", Required: true, Type: "float64"},
    }); err != nil {
        logger.Error(context.Background(), "Configuration invalid", err)
        os.Exit(1)
    }
    
    // Initialize database
    dbConfig := database.DefaultConfig()
    connManager := database.NewConnectionManager(
        cfg.GetString("database.rootPath", "./data"),
        dbConfig,
        logger,
    )
    defer connManager.Close()
    
    // Create error with stack trace
    if err := someOperation(); err != nil {
        appErr := errors.Wrap(err, errors.ERR_AI_GENERAL, "ERR_AI_GENERAL",
            "Operation failed").
            WithDetail("operationType", "initialization").
            SetRetryable(false)
        
        logger.Error(context.Background(), "Startup failed", appErr)
        os.Exit(1)
    }
    
    logger.Info(context.Background(), "Service started",
        "port", cfg.GetInt("server.port", 8090),
    )
}
```

---

## Related Specifications

- [Gateway Service](./01-gateway-service.md)
- [AI Bridge CLI](./12-ai-bridge-cli.md)
- [Error Management](../../06-error-management/00-overview.md)
- [Consistency Report](./99-consistency-report.md)
