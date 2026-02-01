# Error Handling

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Comprehensive error handling with stack traces, structured logging, and integration with the shared error package.

**Cross-References:**
- [Error Code Registry](../error-code-registry/01-registry.md)
- [BRun Error Handling](../brun-cli/06-error-handling.md)
- [Core Architecture](./01-core-architecture.md)

---

## Error Code Range

WP Plugin Builder uses error codes **10000-10999**.

| Range | Category | Description |
|-------|----------|-------------|
| 10000-10099 | General/Startup | Initialization, config loading |
| 10100-10199 | Configuration | Config parsing, validation, seeding |
| 10200-10299 | Database | Connection, migration, query errors |
| 10300-10399 | Project Management | Create, clone, import, export |
| 10400-10499 | RAG/Vector | Embedding, indexing, search |
| 10500-10599 | Code Generation | AI calls, parsing, validation |
| 10600-10699 | Spec Processing | Parsing, import, validation |
| 10700-10799 | Server/API | HTTP server, endpoints |
| 10800-10899 | Import/Export | File operations, zip handling |

---

## Error Code Table

| Code | Constant | Exit | HTTP | Description | Retryable |
|------|----------|------|------|-------------|-----------|
| **General (10000-10099)** |
| 10001 | `ERR_WPB_INIT_FAILED` | 1 | 500 | Application initialization failed | No |
| 10002 | `ERR_WPB_AIBRIDGE_UNAVAILABLE` | 1 | 503 | AI Bridge connection failed | Yes |
| 10003 | `ERR_WPB_BINARY_NOT_FOUND` | 127 | 500 | Required binary not in PATH | No |
| 10004 | `ERR_WPB_VERSION_MISMATCH` | 1 | 400 | Version incompatibility | No |
| **Configuration (10100-10199)** |
| 10101 | `ERR_WPB_CONFIG_NOT_FOUND` | 2 | 404 | Config file not found | No |
| 10102 | `ERR_WPB_CONFIG_PARSE_ERROR` | 2 | 400 | Invalid JSON in config | No |
| 10103 | `ERR_WPB_CONFIG_SCHEMA_INVALID` | 2 | 400 | Config schema validation failed | No |
| 10104 | `ERR_WPB_CONFIG_SEED_FAILED` | 2 | 500 | Configuration seeding failed | No |
| 10105 | `ERR_WPB_CONFIG_WRITE_FAILED` | 2 | 500 | Config file write failed | No |
| **Database (10200-10299)** |
| 10201 | `ERR_WPB_DB_CONNECTION` | 3 | 500 | Database connection failed | Yes |
| 10202 | `ERR_WPB_DB_MIGRATION` | 3 | 500 | Database migration failed | No |
| 10203 | `ERR_WPB_DB_QUERY` | 3 | 500 | Database query failed | Yes |
| 10204 | `ERR_WPB_DB_TRANSACTION` | 3 | 500 | Transaction failed | Yes |
| 10205 | `ERR_WPB_DB_NOT_FOUND` | 3 | 404 | Database file not found | No |
| **Project Management (10300-10399)** |
| 10301 | `ERR_WPB_PROJECT_NAME_REQUIRED` | 4 | 400 | Project name not provided | No |
| 10302 | `ERR_WPB_PROJECT_EXISTS` | 4 | 409 | Project already exists | No |
| 10303 | `ERR_WPB_PROJECT_CREATE_FAILED` | 4 | 500 | Project creation failed | No |
| 10304 | `ERR_WPB_PROJECT_LIST_FAILED` | 4 | 500 | Project listing failed | No |
| 10305 | `ERR_WPB_PROJECT_NOT_FOUND` | 4 | 404 | Project not found | No |
| 10306 | `ERR_WPB_PROJECT_DB_OPEN` | 4 | 500 | Project database open failed | No |
| 10307 | `ERR_WPB_PROJECT_DELETE_CANCELLED` | 4 | 400 | Deletion cancelled by user | No |
| 10308 | `ERR_WPB_PROJECT_DELETE_FAILED` | 4 | 500 | Database deletion failed | No |
| 10309 | `ERR_WPB_PROJECT_CLONE_FAILED` | 4 | 500 | Project cloning failed | No |
| 10310 | `ERR_WPB_PROJECT_EXPORT_FAILED` | 4 | 500 | Database export failed | No |
| 10311 | `ERR_WPB_PROJECT_ZIP_FAILED` | 4 | 500 | Zip creation failed | No |
| 10312 | `ERR_WPB_PROJECT_IMPORT_FAILED` | 4 | 500 | Database import failed | No |
| 10313 | `ERR_WPB_PROJECT_EXTRACT_FAILED` | 4 | 500 | Zip extraction failed | No |
| **RAG/Vector (10400-10499)** |
| 10401 | `ERR_WPB_RAG_EMBED_FAILED` | 5 | 500 | Embedding generation failed | Yes |
| 10402 | `ERR_WPB_RAG_BATCH_EMBED` | 5 | 500 | Batch embedding failed | Yes |
| 10403 | `ERR_WPB_RAG_SEARCH_FAILED` | 5 | 500 | Vector search failed | Yes |
| 10404 | `ERR_WPB_RAG_INSERT_FAILED` | 5 | 500 | Vector insertion failed | No |
| 10405 | `ERR_WPB_PRESET_READ_FAILED` | 5 | 404 | Preset file read failed | No |
| 10406 | `ERR_WPB_PRESET_EXISTS` | 5 | 409 | Preset already exists | No |
| 10407 | `ERR_WPB_PRESET_INDEX_FAILED` | 5 | 500 | Preset indexing failed | No |
| 10408 | `ERR_WPB_PRESET_CREATE_FAILED` | 5 | 500 | Preset creation failed | No |
| 10409 | `ERR_WPB_PRESET_NOT_FOUND` | 5 | 404 | Preset not found | No |
| 10410 | `ERR_WPB_PRESET_VECTORS_FAILED` | 5 | 500 | Preset vectors fetch failed | No |
| 10411 | `ERR_WPB_PRESET_COPY_FAILED` | 5 | 500 | Vector copy to project failed | No |
| **Code Generation (10500-10599)** |
| 10501 | `ERR_WPB_GEN_SPEC_PARSE` | 6 | 400 | Specification parsing failed | No |
| 10502 | `ERR_WPB_GEN_AI_FAILED` | 6 | 500 | AI generation request failed | Yes |
| 10503 | `ERR_WPB_GEN_SYNTAX_ERROR` | 6 | 422 | Generated code has syntax errors | No |
| 10504 | `ERR_WPB_GEN_NO_FILES` | 6 | 422 | No files extracted from response | No |
| 10505 | `ERR_WPB_GEN_BACKUP_FAILED` | 6 | 500 | File backup failed | No |
| 10506 | `ERR_WPB_GEN_DIR_FAILED` | 6 | 500 | Directory creation failed | No |
| 10507 | `ERR_WPB_GEN_WRITE_FAILED` | 6 | 500 | File write failed | No |
| 10508 | `ERR_WPB_GEN_PHP_SYNTAX` | 6 | 422 | PHP syntax validation failed | No |
| **Spec Processing (10600-10699)** |
| 10601 | `ERR_WPB_SPEC_FORMAT` | 7 | 400 | Unsupported spec format | No |
| 10602 | `ERR_WPB_SPEC_STORE_FAILED` | 7 | 500 | Spec storage failed | No |
| 10603 | `ERR_WPB_SPEC_TEMP_DIR` | 7 | 500 | Temp directory creation failed | No |
| 10604 | `ERR_WPB_SPEC_ZIP_EXTRACT` | 7 | 400 | Zip extraction failed | No |
| 10605 | `ERR_WPB_SPEC_READ_FAILED` | 7 | 404 | Folder reading failed | No |
| **Server/API (10700-10799)** |
| 10701 | `ERR_WPB_SERVER_START` | 8 | 500 | Server start failed | No |
| 10702 | `ERR_WPB_SERVER_PORT_IN_USE` | 8 | 409 | Port already in use | No |
| 10703 | `ERR_WPB_API_RATE_LIMIT` | 8 | 429 | Rate limit exceeded | Yes |
| 10704 | `ERR_WPB_API_INVALID_REQUEST` | 8 | 400 | Invalid API request | No |

---

## Error Package

```go
package errors

import (
    "fmt"
    "runtime"
    "strings"
)

type WPBError struct {
    Code       int
    Message    string
    StackTrace []StackFrame
    Fields     map[string]any
    Wrapped    error
}

type StackFrame struct {
    Function string
    File     string
    Line     int
}

func New(code int, message string) *WPBError {
    return &WPBError{
        Code:    code,
        Message: message,
        Fields:  make(map[string]any),
    }
}

func Wrap(err error, code int, message string) *WPBError {
    return &WPBError{
        Code:    code,
        Message: message,
        Fields:  make(map[string]any),
        Wrapped: err,
    }
}

func (e *WPBError) WithStack() *WPBError {
    const depth = 32
    var pcs [depth]uintptr
    n := runtime.Callers(2, pcs[:])
    
    frames := runtime.CallersFrames(pcs[:n])
    for {
        frame, more := frames.Next()
        e.StackTrace = append(e.StackTrace, StackFrame{
            Function: frame.Function,
            File:     frame.File,
            Line:     frame.Line,
        })
        if !more || len(e.StackTrace) >= 10 {
            break
        }
    }
    
    return e
}

func (e *WPBError) WithField(key string, value any) *WPBError {
    e.Fields[key] = value
    return e
}

func (e *WPBError) Error() string {
    var b strings.Builder
    b.WriteString(fmt.Sprintf("[WPB-%d] %s", e.Code, e.Message))
    
    if len(e.Fields) > 0 {
        b.WriteString(" (")
        first := true
        for k, v := range e.Fields {
            if !first {
                b.WriteString(", ")
            }
            b.WriteString(fmt.Sprintf("%s=%v", k, v))
            first = false
        }
        b.WriteString(")")
    }
    
    if e.Wrapped != nil {
        b.WriteString(": ")
        b.WriteString(e.Wrapped.Error())
    }
    
    return b.String()
}

func Code(err error) int {
    if wpbErr, ok := err.(*WPBError); ok {
        return wpbErr.Code
    }
    return 0
}
```

---

## Logging

```go
type Logger struct {
    level   LogLevel
    format  string
    output  io.Writer
    fields  map[string]any
}

type LogLevel int

const (
    LevelDebug LogLevel = iota
    LevelInfo
    LevelWarn
    LevelError
)

func (l *Logger) Error(msg string, err error, fields ...any) {
    entry := l.buildEntry(LevelError, msg, fields)
    
    if wpbErr, ok := err.(*WPBError); ok {
        entry["error_code"] = wpbErr.Code
        entry["error_message"] = wpbErr.Message
        
        if len(wpbErr.StackTrace) > 0 {
            entry["stack_trace"] = wpbErr.StackTrace
        }
        
        for k, v := range wpbErr.Fields {
            entry["error_"+k] = v
        }
    } else if err != nil {
        entry["error"] = err.Error()
    }
    
    l.write(entry)
}

func (l *Logger) buildEntry(level LogLevel, msg string, fields []any) map[string]any {
    entry := map[string]any{
        "timestamp": time.Now().Format(time.RFC3339),
        "level":     level.String(),
        "message":   msg,
    }
    
    // Add configured fields
    for k, v := range l.fields {
        entry[k] = v
    }
    
    // Add provided fields
    for i := 0; i < len(fields)-1; i += 2 {
        if key, ok := fields[i].(string); ok {
            entry[key] = fields[i+1]
        }
    }
    
    return entry
}
```

---

## Error Response (API)

```go
type ErrorResponse struct {
    Code    int            `json:"code"`
    Message string         `json:"message"`
    Details map[string]any `json:"details,omitempty"`
}

func HandleError(w http.ResponseWriter, err error) {
    var resp ErrorResponse
    var statusCode int
    
    if wpbErr, ok := err.(*WPBError); ok {
        resp = ErrorResponse{
            Code:    wpbErr.Code,
            Message: wpbErr.Message,
            Details: wpbErr.Fields,
        }
        statusCode = errorToHTTPStatus(wpbErr.Code)
    } else {
        resp = ErrorResponse{
            Code:    10001,
            Message: err.Error(),
        }
        statusCode = http.StatusInternalServerError
    }
    
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(statusCode)
    json.NewEncoder(w).Encode(resp)
}

func errorToHTTPStatus(code int) int {
    switch {
    case code >= 10600 && code < 10700:
        return http.StatusBadRequest
    case code >= 10300 && code < 10400:
        if code == 10302 || code == 10305 {
            return http.StatusNotFound
        }
        return http.StatusInternalServerError
    default:
        return http.StatusInternalServerError
    }
}
```

---

## Log File Structure

```
~/.wpb/logs/
├── wpb_20260201.log           # Daily log file
├── wpb_20260201_error.log     # Error-only log
└── wpb.log                    # Current symlink
```

---

## See Also

- [Error Code Registry](../error-code-registry/01-registry.md)
- [Configuration](./03-configuration.md)
- [API Interface](./11-api-interface.md)
