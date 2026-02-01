# pkg/errors Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Priority:** P0 (Foundational)  

---

## Overview

The `pkg/errors` package provides a centralized, typed error handling system for all SpecBuilder Pro microservices. It enforces consistent error structures, enables error categorization, and provides HTTP response helpers.

**Cross-References:**
- [Error Management Overview](../06-error-management/00-overview.md)
- [Backend Error Codes](../06-error-management/backend/01-error-codes.md)

---

## File Structure

```
pkg/errors/
├── codes.go       # Error code constants
├── types.go       # AppError struct & interfaces
├── factory.go     # Error constructor functions
├── registry.go    # Error code registry & lookup
├── http.go        # HTTP response helpers
├── wrap.go        # Error wrapping utilities
└── errors_test.go # Comprehensive tests
```

---

## Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 1xxx | Validation | Input validation failures |
| 2xxx | Authentication | Auth and permission errors |
| 3xxx | Database | SQLite operations |
| 4xxx | External | LLaMA, APIs, network |
| 5xxx | Business | Domain-specific errors |
| 6xxx | FileSystem | File operations |
| 7xxx | Configuration | Config parsing |
| 8xxx | Security | SSRF, security violations |
| 9xxx | System | OS-level, resources |

---

## codes.go

```go
package errors

// ErrorCode represents a typed error code
type ErrorCode int

// Validation Errors (1xxx)
const (
    ErrValidationRequired     ErrorCode = 1001
    ErrValidationFormat       ErrorCode = 1002
    ErrValidationRange        ErrorCode = 1003
    ErrValidationLength       ErrorCode = 1004
    ErrValidationEnum         ErrorCode = 1005
    ErrValidationUnique       ErrorCode = 1006
    ErrValidationReference    ErrorCode = 1007
    ErrValidationConflict     ErrorCode = 1008
    ErrValidationImmutable    ErrorCode = 1009
    ErrValidationDependency   ErrorCode = 1010
)

// Authentication Errors (2xxx)
const (
    ErrAuthRequired           ErrorCode = 2001
    ErrAuthInvalidToken       ErrorCode = 2002
    ErrAuthExpiredToken       ErrorCode = 2003
    ErrAuthInsufficientPerms  ErrorCode = 2004
    ErrAuthInvalidCredentials ErrorCode = 2005
    ErrAuthAccountLocked      ErrorCode = 2006
    ErrAuthSessionExpired     ErrorCode = 2007
    ErrAuthMFARequired        ErrorCode = 2008
)

// Database Errors (3xxx)
const (
    ErrDatabaseConnection     ErrorCode = 3001
    ErrDatabaseQuery          ErrorCode = 3002
    ErrDatabaseTransaction    ErrorCode = 3003
    ErrDatabaseMigration      ErrorCode = 3004
    ErrDatabaseConstraint     ErrorCode = 3005
    ErrDatabaseNotFound       ErrorCode = 3006
    ErrDatabaseDuplicate      ErrorCode = 3007
    ErrDatabaseTimeout        ErrorCode = 3008
    ErrDatabaseCorruption     ErrorCode = 3009
    ErrDatabaseLock           ErrorCode = 3010
)

// External Service Errors (4xxx)
const (
    ErrExternalConnection     ErrorCode = 4001
    ErrExternalTimeout        ErrorCode = 4002
    ErrExternalRateLimit      ErrorCode = 4003
    ErrExternalUnavailable    ErrorCode = 4004
    ErrExternalInvalidResp    ErrorCode = 4005
    ErrExternalLLaMALoad      ErrorCode = 4006
    ErrExternalLLaMAInference ErrorCode = 4007
    ErrExternalLLaMAContext   ErrorCode = 4008
)

// Business Logic Errors (5xxx)
const (
    ErrBusinessInvalidState   ErrorCode = 5001
    ErrBusinessRuleViolation  ErrorCode = 5002
    ErrBusinessQuotaExceeded  ErrorCode = 5003
    ErrBusinessNotAllowed     ErrorCode = 5004
    ErrBusinessPrecondition   ErrorCode = 5005
    ErrBusinessConcurrency    ErrorCode = 5006
    ErrBusinessWorkflow       ErrorCode = 5007
)

// File System Errors (6xxx)
const (
    ErrFileNotFound           ErrorCode = 6001
    ErrFilePermission         ErrorCode = 6002
    ErrFileRead               ErrorCode = 6003
    ErrFileWrite              ErrorCode = 6004
    ErrFileCreate             ErrorCode = 6005
    ErrFileDelete             ErrorCode = 6006
    ErrFilePath               ErrorCode = 6007
    ErrFileSize               ErrorCode = 6008
    ErrFileType               ErrorCode = 6009
)

// Configuration Errors (7xxx)
const (
    ErrConfigNotFound         ErrorCode = 7001
    ErrConfigParse            ErrorCode = 7002
    ErrConfigValidation       ErrorCode = 7003
    ErrConfigMissing          ErrorCode = 7004
    ErrConfigType             ErrorCode = 7005
    ErrConfigEnvironment      ErrorCode = 7006
)

// Security Errors (8xxx)
const (
    ErrSecuritySSRF           ErrorCode = 8001
    ErrSecurityInjection      ErrorCode = 8002
    ErrSecurityPathTraversal  ErrorCode = 8003
    ErrSecurityRateLimit      ErrorCode = 8004
    ErrSecurityBlocked        ErrorCode = 8005
    ErrSecurityTampered       ErrorCode = 8006
)

// System Errors (9xxx)
const (
    ErrSystemMemory           ErrorCode = 9001
    ErrSystemDisk             ErrorCode = 9002
    ErrSystemCPU              ErrorCode = 9003
    ErrSystemNetwork          ErrorCode = 9004
    ErrSystemProcess          ErrorCode = 9005
    ErrSystemShutdown         ErrorCode = 9006
    ErrSystemPanic            ErrorCode = 9007
)

// String returns the constant name for the error code
func (c ErrorCode) String() string {
    return codeToString[c]
}

// Category returns the error category
func (c ErrorCode) Category() string {
    switch {
    case c >= 1000 && c < 2000:
        return "validation"
    case c >= 2000 && c < 3000:
        return "authentication"
    case c >= 3000 && c < 4000:
        return "database"
    case c >= 4000 && c < 5000:
        return "external"
    case c >= 5000 && c < 6000:
        return "business"
    case c >= 6000 && c < 7000:
        return "filesystem"
    case c >= 7000 && c < 8000:
        return "configuration"
    case c >= 8000 && c < 9000:
        return "security"
    case c >= 9000 && c < 10000:
        return "system"
    default:
        return "unknown"
    }
}

// HTTPStatus returns the appropriate HTTP status code
func (c ErrorCode) HTTPStatus() int {
    switch c.Category() {
    case "validation":
        return 400 // Bad Request
    case "authentication":
        if c == ErrAuthInsufficientPerms {
            return 403 // Forbidden
        }
        return 401 // Unauthorized
    case "database":
        if c == ErrDatabaseNotFound {
            return 404 // Not Found
        }
        if c == ErrDatabaseDuplicate {
            return 409 // Conflict
        }
        return 500 // Internal Server Error
    case "external":
        if c == ErrExternalRateLimit {
            return 429 // Too Many Requests
        }
        return 502 // Bad Gateway
    case "business":
        if c == ErrBusinessNotAllowed {
            return 403 // Forbidden
        }
        return 422 // Unprocessable Entity
    case "filesystem":
        if c == ErrFileNotFound {
            return 404 // Not Found
        }
        return 500 // Internal Server Error
    case "configuration":
        return 500 // Internal Server Error
    case "security":
        return 403 // Forbidden
    case "system":
        return 503 // Service Unavailable
    default:
        return 500 // Internal Server Error
    }
}

// Retryable returns whether the error is retryable
func (c ErrorCode) Retryable() bool {
    switch c {
    case ErrDatabaseTimeout, ErrDatabaseLock,
        ErrExternalTimeout, ErrExternalRateLimit, ErrExternalUnavailable,
        ErrSystemMemory, ErrSystemNetwork:
        return true
    default:
        return false
    }
}

var codeToString = map[ErrorCode]string{
    ErrValidationRequired:     "ERR_VALIDATION_REQUIRED",
    ErrValidationFormat:       "ERR_VALIDATION_FORMAT",
    ErrValidationRange:        "ERR_VALIDATION_RANGE",
    // ... all other mappings
}
```

---

## types.go

```go
package errors

import (
    "encoding/json"
    "fmt"
    "runtime"
    "strings"
)

// StackFrame represents a single frame in the stack trace
type StackFrame struct {
    Function string `json:"function"`
    File     string `json:"file"`
    Line     int    `json:"line"`
}

// AppError represents a structured application error
// CRITICAL: All errors MUST capture stack traces at creation time
type AppError struct {
    Code       ErrorCode         `json:"code"`
    Constant   string            `json:"constant"`
    Message    string            `json:"message"`
    Details    map[string]any    `json:"details,omitempty"`
    StackTrace []StackFrame      `json:"stackTrace,omitempty"`
    cause      error             // Internal, not serialized
    retryable  bool              // Override default retryability
}

// captureStackTrace captures the current call stack
// Skips the specified number of frames (0 = captureStackTrace itself)
func captureStackTrace(skip int) []StackFrame {
    const maxDepth = 32
    var pcs [maxDepth]uintptr
    n := runtime.Callers(skip+2, pcs[:]) // +2 to skip Callers and captureStackTrace
    
    if n == 0 {
        return nil
    }
    
    frames := runtime.CallersFrames(pcs[:n])
    stack := make([]StackFrame, 0, n)
    
    for {
        frame, more := frames.Next()
        
        // Skip runtime and internal frames
        if strings.Contains(frame.File, "runtime/") {
            if !more {
                break
            }
            continue
        }
        
        // Extract short function name
        funcName := frame.Function
        if idx := strings.LastIndex(funcName, "/"); idx != -1 {
            funcName = funcName[idx+1:]
        }
        
        stack = append(stack, StackFrame{
            Function: funcName,
            File:     frame.File,
            Line:     frame.Line,
        })
        
        if !more {
            break
        }
    }
    
    return stack
}

// Error implements the error interface
func (e *AppError) Error() string {
    if e.cause != nil {
        return fmt.Sprintf("[%s] %s: %v", e.Constant, e.Message, e.cause)
    }
    return fmt.Sprintf("[%s] %s", e.Constant, e.Message)
}

// ErrorWithStack returns error message with stack trace
func (e *AppError) ErrorWithStack() string {
    var sb strings.Builder
    sb.WriteString(e.Error())
    sb.WriteString("\nStack trace:\n")
    for _, frame := range e.StackTrace {
        sb.WriteString(fmt.Sprintf("  at %s (%s:%d)\n", frame.Function, frame.File, frame.Line))
    }
    return sb.String()
}

// Unwrap returns the underlying cause for errors.Is/As
func (e *AppError) Unwrap() error {
    return e.cause
}

// WithCause sets the underlying error cause
func (e *AppError) WithCause(err error) *AppError {
    e.cause = err
    return e
}

// WithDetails adds context details
func (e *AppError) WithDetails(details map[string]any) *AppError {
    if e.Details == nil {
        e.Details = make(map[string]any)
    }
    for k, v := range details {
        e.Details[k] = v
    }
    return e
}

// WithDetail adds a single detail
func (e *AppError) WithDetail(key string, value any) *AppError {
    if e.Details == nil {
        e.Details = make(map[string]any)
    }
    e.Details[key] = value
    return e
}

// IsRetryable returns whether the error can be retried
func (e *AppError) IsRetryable() bool {
    if e.retryable {
        return true
    }
    return e.Code.Retryable()
}

// SetRetryable overrides the default retryability
func (e *AppError) SetRetryable(retryable bool) *AppError {
    e.retryable = retryable
    return e
}

// HTTPStatus returns the appropriate HTTP status code
func (e *AppError) HTTPStatus() int {
    return e.Code.HTTPStatus()
}

// Category returns the error category
func (e *AppError) Category() string {
    return e.Code.Category()
}

// MarshalJSON customizes JSON serialization
func (e *AppError) MarshalJSON() ([]byte, error) {
    type alias AppError
    return json.Marshal(&struct {
        *alias
        Retryable bool `json:"retryable"`
    }{
        alias:     (*alias)(e),
        Retryable: e.IsRetryable(),
    })
}

// Is checks if the error matches a specific code
func (e *AppError) Is(code ErrorCode) bool {
    return e.Code == code
}

// IsCategory checks if the error belongs to a category
func (e *AppError) IsCategory(category string) bool {
    return e.Category() == category
}
```

---

## factory.go

```go
package errors

import "time"

// New creates a new AppError with the given code and message
// CRITICAL: Stack trace is ALWAYS captured at error creation
func New(code ErrorCode, message string) *AppError {
    return &AppError{
        Code:       code,
        Constant:   code.String(),
        Message:    message,
        StackTrace: captureStackTrace(1), // Skip New() itself
    }
}

// NewWithDetails creates an AppError with details
// CRITICAL: Stack trace is ALWAYS captured at error creation
func NewWithDetails(code ErrorCode, message string, details map[string]any) *AppError {
    return &AppError{
        Code:       code,
        Constant:   code.String(),
        Message:    message,
        Details:    details,
        StackTrace: captureStackTrace(1), // Skip NewWithDetails() itself
    }
}

// ============ Validation Error Factories ============

// NewValidation creates a validation error
func NewValidation(code ErrorCode, message string, details map[string]any) *AppError {
    if code < 1000 || code >= 2000 {
        code = ErrValidationRequired // Default to required
    }
    return NewWithDetails(code, message, details)
}

// NewValidationRequired creates a required field error
func NewValidationRequired(field string) *AppError {
    return NewWithDetails(
        ErrValidationRequired,
        fmt.Sprintf("%s is required", field),
        map[string]any{"field": field},
    )
}

// NewValidationFormat creates a format error
func NewValidationFormat(field, expected string) *AppError {
    return NewWithDetails(
        ErrValidationFormat,
        fmt.Sprintf("%s has invalid format, expected %s", field, expected),
        map[string]any{"field": field, "expected": expected},
    )
}

// NewValidationRange creates a range error
func NewValidationRange(field string, min, max any) *AppError {
    return NewWithDetails(
        ErrValidationRange,
        fmt.Sprintf("%s must be between %v and %v", field, min, max),
        map[string]any{"field": field, "min": min, "max": max},
    )
}

// ============ Database Error Factories ============

// NewDatabase creates a database error
func NewDatabase(code ErrorCode, message string, details map[string]any) *AppError {
    if code < 3000 || code >= 4000 {
        code = ErrDatabaseQuery // Default
    }
    return NewWithDetails(code, message, details)
}

// NewDatabaseNotFound creates a not found error
func NewDatabaseNotFound(resource, identifier string) *AppError {
    return NewWithDetails(
        ErrDatabaseNotFound,
        fmt.Sprintf("%s not found: %s", resource, identifier),
        map[string]any{"resource": resource, "identifier": identifier},
    )
}

// NewDatabaseDuplicate creates a duplicate error
func NewDatabaseDuplicate(resource, field, value string) *AppError {
    return NewWithDetails(
        ErrDatabaseDuplicate,
        fmt.Sprintf("%s with %s '%s' already exists", resource, field, value),
        map[string]any{"resource": resource, "field": field, "value": value},
    )
}

// ============ External Service Error Factories ============

// NewExternal creates an external service error
func NewExternal(code ErrorCode, service, message string) *AppError {
    if code < 4000 || code >= 5000 {
        code = ErrExternalConnection
    }
    return NewWithDetails(code, message, map[string]any{"service": service})
}

// NewExternalTimeout creates a timeout error
func NewExternalTimeout(service string, timeout time.Duration) *AppError {
    return NewWithDetails(
        ErrExternalTimeout,
        fmt.Sprintf("%s request timed out after %v", service, timeout),
        map[string]any{"service": service, "timeout": timeout.String()},
    )
}

// ============ File System Error Factories ============

// NewFileSystem creates a file system error
func NewFileSystem(code ErrorCode, path, message string) *AppError {
    if code < 6000 || code >= 7000 {
        code = ErrFileRead
    }
    return NewWithDetails(code, message, map[string]any{"path": path})
}

// NewFileNotFound creates a file not found error
func NewFileNotFound(path string) *AppError {
    return NewWithDetails(
        ErrFileNotFound,
        fmt.Sprintf("file not found: %s", path),
        map[string]any{"path": path},
    )
}

// ============ Security Error Factories ============

// NewSecurity creates a security error
func NewSecurity(code ErrorCode, message string, details map[string]any) *AppError {
    if code < 8000 || code >= 9000 {
        code = ErrSecurityBlocked
    }
    return NewWithDetails(code, message, details)
}

// NewSecuritySSRF creates an SSRF attempt error
func NewSecuritySSRF(url string) *AppError {
    return NewWithDetails(
        ErrSecuritySSRF,
        "blocked potential SSRF attempt",
        map[string]any{"blocked_url": url},
    )
}

// ============ System Error Factories ============

// NewSystem creates a system error
func NewSystem(code ErrorCode, message string) *AppError {
    if code < 9000 || code >= 10000 {
        code = ErrSystemPanic
    }
    return New(code, message)
}
```

---

## http.go

```go
package errors

import (
    "encoding/json"
    "net/http"
)

// ErrorResponse is the standard error response format
type ErrorResponse struct {
    Success bool      `json:"success"`
    Error   *AppError `json:"error"`
}

// WriteError writes an AppError as JSON response
func WriteError(w http.ResponseWriter, err *AppError) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(err.HTTPStatus())
    
    response := ErrorResponse{
        Success: false,
        Error:   err,
    }
    
    json.NewEncoder(w).Encode(response)
}

// WriteErrorWithStatus writes an error with custom status
func WriteErrorWithStatus(w http.ResponseWriter, err *AppError, status int) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    
    response := ErrorResponse{
        Success: false,
        Error:   err,
    }
    
    json.NewEncoder(w).Encode(response)
}

// FromHTTPError converts an HTTP error to AppError
func FromHTTPError(statusCode int, body []byte) *AppError {
    // Try to parse as ErrorResponse
    var errResp ErrorResponse
    if err := json.Unmarshal(body, &errResp); err == nil && errResp.Error != nil {
        return errResp.Error
    }
    
    // Create generic error based on status
    switch statusCode {
    case 400:
        return New(ErrValidationRequired, "bad request")
    case 401:
        return New(ErrAuthRequired, "authentication required")
    case 403:
        return New(ErrAuthInsufficientPerms, "forbidden")
    case 404:
        return New(ErrDatabaseNotFound, "resource not found")
    case 429:
        return New(ErrExternalRateLimit, "rate limit exceeded")
    case 500:
        return New(ErrSystemPanic, "internal server error")
    case 502, 503, 504:
        return New(ErrExternalUnavailable, "service unavailable")
    default:
        return New(ErrSystemPanic, "unexpected error")
    }
}

// HandlerFunc is an HTTP handler that returns an error
type HandlerFunc func(http.ResponseWriter, *http.Request) error

// Wrap wraps a HandlerFunc to handle errors automatically
func Wrap(h HandlerFunc) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        if err := h(w, r); err != nil {
            if appErr, ok := err.(*AppError); ok {
                WriteError(w, appErr)
                return
            }
            // Wrap unknown errors
            WriteError(w, New(ErrSystemPanic, err.Error()))
        }
    }
}
```

---

## registry.go

```go
package errors

import "sync"

// Registry maintains a registry of all error codes
type Registry struct {
    mu     sync.RWMutex
    codes  map[ErrorCode]CodeInfo
}

// CodeInfo contains metadata about an error code
type CodeInfo struct {
    Code        ErrorCode
    Constant    string
    Category    string
    Description string
    HTTPStatus  int
    Retryable   bool
}

// GlobalRegistry is the default error code registry
var GlobalRegistry = NewRegistry()

// NewRegistry creates a new error registry
func NewRegistry() *Registry {
    r := &Registry{
        codes: make(map[ErrorCode]CodeInfo),
    }
    r.registerDefaults()
    return r
}

// Register adds a new error code to the registry
func (r *Registry) Register(info CodeInfo) {
    r.mu.Lock()
    defer r.mu.Unlock()
    r.codes[info.Code] = info
}

// Lookup retrieves info about an error code
func (r *Registry) Lookup(code ErrorCode) (CodeInfo, bool) {
    r.mu.RLock()
    defer r.mu.RUnlock()
    info, ok := r.codes[code]
    return info, ok
}

// All returns all registered error codes
func (r *Registry) All() []CodeInfo {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    result := make([]CodeInfo, 0, len(r.codes))
    for _, info := range r.codes {
        result = append(result, info)
    }
    return result
}

// ByCategory returns all codes in a category
func (r *Registry) ByCategory(category string) []CodeInfo {
    r.mu.RLock()
    defer r.mu.RUnlock()
    
    var result []CodeInfo
    for _, info := range r.codes {
        if info.Category == category {
            result = append(result, info)
        }
    }
    return result
}

func (r *Registry) registerDefaults() {
    defaults := []CodeInfo{
        {ErrValidationRequired, "ERR_VALIDATION_REQUIRED", "validation", "Required field missing", 400, false},
        {ErrValidationFormat, "ERR_VALIDATION_FORMAT", "validation", "Invalid format", 400, false},
        {ErrDatabaseNotFound, "ERR_DATABASE_NOT_FOUND", "database", "Resource not found", 404, false},
        {ErrDatabaseTimeout, "ERR_DATABASE_TIMEOUT", "database", "Query timeout", 500, true},
        // ... all other defaults
    }
    
    for _, info := range defaults {
        r.codes[info.Code] = info
    }
}
```

---

## Testing Requirements

```go
// errors_test.go
package errors_test

import (
    "testing"
    "encoding/json"
    
    "github.com/specbuilder/pkg/errors"
)

func TestAppError_Error(t *testing.T) {
    err := errors.New(errors.ErrValidationRequired, "email is required")
    
    if err.Error() != "[ERR_VALIDATION_REQUIRED] email is required" {
        t.Errorf("unexpected error string: %s", err.Error())
    }
}

func TestAppError_WithCause(t *testing.T) {
    cause := fmt.Errorf("underlying error")
    err := errors.New(errors.ErrDatabaseQuery, "query failed").WithCause(cause)
    
    if !errors.Is(err, cause) {
        t.Error("cause should be unwrappable")
    }
}

func TestAppError_JSONMarshal(t *testing.T) {
    err := errors.NewWithDetails(
        errors.ErrValidationRequired,
        "email is required",
        map[string]any{"field": "email"},
    )
    
    data, _ := json.Marshal(err)
    
    var result map[string]any
    json.Unmarshal(data, &result)
    
    if result["code"].(float64) != 1001 {
        t.Error("code should be 1001")
    }
}

func TestErrorCode_HTTPStatus(t *testing.T) {
    tests := []struct {
        code   errors.ErrorCode
        status int
    }{
        {errors.ErrValidationRequired, 400},
        {errors.ErrAuthRequired, 401},
        {errors.ErrAuthInsufficientPerms, 403},
        {errors.ErrDatabaseNotFound, 404},
        {errors.ErrExternalRateLimit, 429},
    }
    
    for _, tt := range tests {
        if tt.code.HTTPStatus() != tt.status {
            t.Errorf("%v: expected %d, got %d", tt.code, tt.status, tt.code.HTTPStatus())
        }
    }
}

func TestErrorCode_Retryable(t *testing.T) {
    retryable := []errors.ErrorCode{
        errors.ErrDatabaseTimeout,
        errors.ErrExternalTimeout,
        errors.ErrExternalRateLimit,
    }
    
    for _, code := range retryable {
        if !code.Retryable() {
            t.Errorf("%v should be retryable", code)
        }
    }
}

func BenchmarkNew(b *testing.B) {
    for i := 0; i < b.N; i++ {
        _ = errors.New(errors.ErrValidationRequired, "test")
    }
}
```

---

## Usage Examples

### Basic Error Creation

```go
// Simple error
err := errors.New(errors.ErrValidationRequired, "email is required")

// Error with details
err := errors.NewWithDetails(
    errors.ErrValidationFormat,
    "invalid email format",
    map[string]any{
        "field":    "email",
        "value":    "not-an-email",
        "expected": "user@domain.com",
    },
)

// Using factory functions
err := errors.NewValidationRequired("email")
err := errors.NewDatabaseNotFound("User", "123")
```

### Error Handling in HTTP Handlers

```go
func GetUser(w http.ResponseWriter, r *http.Request) error {
    id := chi.URLParam(r, "id")
    
    user, err := db.GetUser(r.Context(), id)
    if err != nil {
        if errors.Is(err, sql.ErrNoRows) {
            return errors.NewDatabaseNotFound("User", id)
        }
        return errors.NewDatabase(
            errors.ErrDatabaseQuery,
            "failed to fetch user",
            map[string]any{"id": id},
        ).WithCause(err)
    }
    
    return json.NewEncoder(w).Encode(user)
}

// Register with automatic error handling
http.Handle("/users/{id}", errors.Wrap(GetUser))
```

### Error Checking

```go
err := someOperation()

// Check specific code
if appErr, ok := err.(*errors.AppError); ok {
    if appErr.Is(errors.ErrDatabaseNotFound) {
        // Handle not found
    }
    
    if appErr.IsCategory("validation") {
        // Handle all validation errors
    }
    
    if appErr.IsRetryable() {
        // Retry the operation
    }
}
```
