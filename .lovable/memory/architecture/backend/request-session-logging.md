# Memory: architecture/backend/request-session-logging
Updated: 2026-02-06

## Overview

All API requests (except health checks) are logged with full request/response details when `logging.sessionLoggingEnabled` is true in config. This provides a complete audit trail for debugging and diagnostics.

---

## Configuration

```json
{
  "logging": {
    "sessionLoggingEnabled": true,
    "clearLogsOnStartup": false,
    "clearSessionsOnStartup": false
  }
}
```

---

## Storage Structure

Request sessions are stored in date/hour organized folders:

```
data/request-sessions/
├── 2026-02-06/
│   ├── 10/
│   │   ├── {uuid}.json
│   │   └── {uuid}.json
│   └── 11/
│       └── {uuid}.json
└── 2026-02-07/
    └── 09/
        └── {uuid}.json
```

---

## Session Data Model

```go
type RequestSession struct {
    ID              string                        // UUID
    Method          string                        // GET, POST, etc.
    Path            string                        // /api/v1/plugins
    QueryString     string                        // ?limit=10&offset=0
    RequestHeaders  map[string]string             // Redacted sensitive
    RequestBody     string                        // Truncated at 50KB
    ResponseStatus  int                           // 200, 404, 500, etc.
    ResponseBody    string                        // Truncated at 50KB
    StartTime       time.Time                     // Request start
    EndTime         time.Time                     // Response complete
    DurationMs      int64                         // Total duration
    Error           string                        // Extracted error message
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/request-sessions` | List with pagination/filters |
| GET | `/api/v1/request-sessions/{id}` | Get single session by ID |
| DELETE | `/api/v1/request-sessions/{id}` | Delete single session |
| DELETE | `/api/v1/request-sessions` | Clear all sessions |
| GET | `/api/v1/request-sessions/errors` | List error sessions only |
| GET | `/api/v1/request-sessions/{id}/export` | Download session as JSON |

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `limit` | int | 50 | Max results per page (max 500) |
| `offset` | int | 0 | Pagination offset |
| `method` | string | - | Filter by HTTP method |
| `path` | string | - | Filter by path substring |
| `status` | int | - | Filter by response status code |
| `errorsOnly` | bool | false | Only sessions with errors |

---

## Middleware Implementation

**File:** `backend/internal/api/middleware/session_logging.go`

### Request Flow

1. Generate UUID for session
2. Store session ID in request context
3. Capture request headers (redact sensitive)
4. Buffer request body (max 50KB)
5. Wrap ResponseWriter to capture response
6. Execute handler
7. Extract error if status >= 400
8. Save session to file store

### Header Redaction

```go
var sensitiveHeaders = map[string]bool{
    "authorization":  true,
    "cookie":         true,
    "x-api-key":      true,
    "x-auth-token":   true,
}
```

### Body Truncation

```go
const maxBodySize = 50 * 1024 // 50KB
```

### Excluded Paths

```go
// Skip logging for health checks
if strings.HasSuffix(r.URL.Path, "/health") {
    next.ServeHTTP(w, r)
    return
}
```

---

## Context Access

Session ID is available in request context:

```go
// In any handler
sessionID := middleware.GetSessionID(r.Context())
```

---

## Retention Policy

Sessions are automatically cleaned up after 1 day (configurable):

```go
reqSessionStore, err = requestsession.New(requestsession.Config{
    DataDir:       filepath.Dir(cfg.DatabasePath),
    Logger:        log,
    RetentionDays: 1, // High volume, short retention
})
```

---

## Error Extraction

When response status >= 400, the middleware attempts to extract error details:

```go
if status >= 400 && len(responseBody) > 0 {
    var errResp struct {
        Error struct {
            Message string `json:"message"` // external key
        } `json:"error"` // external key
    }
    if json.Unmarshal(responseBody, &errResp) == nil {
        session.Error = errResp.Error.Message
    }
}
```

---

## Integration with Error Modal

The frontend can fetch request session details to show in the error modal:

```typescript
// Fetch session by ID
const session = await api.getRequestSession(sessionId);

// List error sessions
const errors = await api.listRequestSessions({ errorsOnly: true });
```

---

## Related Files

- `backend/internal/api/middleware/session_logging.go` - Middleware
- `backend/internal/services/requestsession/store.go` - Storage service
- `backend/internal/api/handlers/request_session_handlers.go` - API handlers
- `backend/internal/api/router.go` - Route registration
- `backend/cmd/server/main.go` - Initialization and wiring
