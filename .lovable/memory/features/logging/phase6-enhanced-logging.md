# Memory: features/logging/phase6-enhanced-logging
Updated: 2026-02-06

## Overview

Phase 6 implements a comprehensive logging and diagnostics system with configurable startup behavior, per-request session logging, React execution chain tracking, and enhanced UI for error diagnostics.

---

## Phase 6.1: Configurable Log Clearing on Startup

### Configuration Options

Located in `backend/config.json` under `logging`:

```json
{
  "logging": {
    "clearLogsOnStartup": false,
    "clearSessionsOnStartup": false,
    "sessionLoggingEnabled": true
  }
}
```

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `clearLogsOnStartup` | bool | false | Clears `log.txt` and `error.log.txt` on server start |
| `clearSessionsOnStartup` | bool | false | Clears all session directories on server start |
| `sessionLoggingEnabled` | bool | true | Enables per-request session logging |

### Implementation

**File:** `backend/cmd/server/main.go`

```go
// Clear logs on startup if configured
if cfg.Logging.ClearLogsOnStartup {
    os.Remove(allLogPath)   // data/errors/log.txt
    os.Remove(errLogPath)   // data/errors/error.log.txt
}

// Clear sessions on startup if configured
if cfg.Logging.ClearSessionsOnStartup {
    os.RemoveAll(sessionsDir)  // data/sessions/
    os.MkdirAll(sessionsDir, 0755)
}
```

---

## Phase 6.2: Session-Based API Logging

### Architecture

Every API request (except health checks) is wrapped in a session that captures full request/response details.

### Storage Structure

```
data/request-sessions/
├── 2026-02-06/
│   ├── 10/           # Hour (24h format)
│   │   ├── a1b2c3d4-e5f6-7890-abcd-ef1234567890.json
│   │   └── b2c3d4e5-f6a7-8901-bcde-f12345678901.json
│   └── 11/
│       └── c3d4e5f6-a7b8-9012-cdef-123456789012.json
```

### Session Data Model

**File:** `backend/internal/services/requestsession/store.go`

```go
type RequestSession struct {
    ID              string
    Method          string
    Path            string
    QueryString     string            `json:",omitempty"`
    RequestHeaders  map[string]string
    RequestBody     string            `json:",omitempty"`
    ResponseStatus  int
    ResponseBody    string            `json:",omitempty"`
    StartTime       time.Time
    EndTime         time.Time
    DurationMs      int64
    Error           string            `json:",omitempty"`
}
```

### Middleware Implementation

**File:** `backend/internal/api/middleware/session_logging.go`

Key features:
- Generates UUID for each request
- Captures request headers (sensitive values redacted)
- Buffers request body (max 50KB)
- Wraps ResponseWriter to capture response body
- Stores session ID in request context
- Skips health check endpoints

```go
func SessionLogging(store *requestsession.Store) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Skip health checks
            if strings.HasSuffix(r.URL.Path, "/health") {
                next.ServeHTTP(w, r)
                return
            }
            
            sessionID := uuid.New().String()
            ctx := context.WithValue(r.Context(), sessionIDKey, sessionID)
            
            // ... capture request/response ...
            
            store.Save(session)
        })
    }
}
```

### API Endpoints

**File:** `backend/internal/api/handlers/request_session_handlers.go`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/request-sessions` | List sessions with pagination |
| GET | `/api/v1/request-sessions/{id}` | Get single session |
| DELETE | `/api/v1/request-sessions/{id}` | Delete session |
| DELETE | `/api/v1/request-sessions` | Clear all sessions |
| GET | `/api/v1/request-sessions/errors` | List error sessions only |
| GET | `/api/v1/request-sessions/{id}/export` | Download as JSON file |

### Query Parameters for List

| Param | Type | Description |
|-------|------|-------------|
| `limit` | int | Max results (default 50, max 500) |
| `offset` | int | Pagination offset |
| `method` | string | Filter by HTTP method |
| `path` | string | Filter by path substring |
| `status` | int | Filter by response status |
| `errorsOnly` | bool | Only show sessions with errors |

### Header Redaction

Sensitive headers are redacted in stored sessions:

```go
var sensitiveHeaders = map[string]bool{
    "authorization": true,
    "cookie": true,
    "x-api-key": true,
    "x-auth-token": true,
}
```

### Retention Policy

Sessions older than 1 day are automatically cleaned up (configurable via `RetentionDays`).

---

## Phase 6.3: React Execution Logger

### Purpose

Tracks function calls, component renders, effects, and handlers to build a complete execution chain for debugging frontend errors.

### Store Implementation

**File:** `src/hooks/useExecutionLogger.ts`

```typescript
interface ExecutionLogEntry {
  id: string;
  timestamp: number;
  type: 'function' | 'component' | 'effect' | 'handler' | 'api';
  name: string;
  context?: string;
  args?: unknown[];
  result?: unknown;
  error?: string;
  parentId?: string;
  duration?: number;
}

interface ExecutionLoggerState {
  entries: ExecutionLogEntry[];
  enabled: boolean;
  callStack: string[];
  // ... methods
}
```

### Logger Methods

| Method | Purpose | Example |
|--------|---------|---------|
| `logFunction` | Track function execution | `logFunction('fetchData', [userId])` |
| `logComponent` | Track component render | `logComponent('UserCard', { props })` |
| `logEffect` | Track useEffect | `logEffect('loadUserData', deps)` |
| `logHandler` | Track event handlers | `logHandler('onClick', 'DeleteButton')` |
| `logApiCall` | Track API requests | `logApiCall('GET', '/api/users')` |

### Call Chain Building

The logger maintains a `callStack` to track parent-child relationships:

```typescript
logFunction: (name, args) => {
  const parentId = state.callStack[state.callStack.length - 1];
  const entry = { id, parentId, type: 'function', name, args };
  // Push to call stack during execution
}
```

### Integration with Error Store

**File:** `src/stores/errorStore.ts`

When an error is captured, the execution chain is included:

```typescript
captureError: (input) => {
  const executionChain = useExecutionLogger.getState().getFormattedChain();
  const error: CapturedError = {
    ...input,
    executionChain,
    // ...
  };
}
```

### Configuration

Controlled by `debugMode` setting (default: false). When disabled, logging is no-op for performance.

---

## Phase 6.4: Stack Tab Frontend/Backend Sub-tabs

### UI Structure

**File:** `src/components/errors/GlobalErrorModal.tsx`

The Stack tab now contains two sub-tabs:

```
Stack Tab
├── Frontend Sub-tab
│   ├── React Execution Chain (if debug mode enabled)
│   ├── Raw Stack Trace
│   └── Parsed Stack Frames
└── Backend Sub-tab
    ├── Backend Error Log (auto-fetched)
    ├── Go Stack Trace
    └── PHP Stack Trace Frames
```

### Frontend Sub-tab Content

1. **React Execution Chain** (when `debugMode` enabled):
   - Formatted call chain from `useExecutionLogger`
   - Copy button for quick sharing
   - Tip shown when debug mode is off

2. **Raw Stack Trace**:
   - Unprocessed JavaScript error stack

3. **Parsed Stack Frames**:
   - Table view with File, Function, Line, Column

### Backend Sub-tab Content

1. **Backend Error Log**:
   - Auto-fetched from `/api/v1/errors/log`
   - Refresh, Copy, Download buttons
   - Cached for modal session

2. **Go Stack Trace**:
   - From `error.backendStackTrace`

3. **PHP Stack Trace Frames**:
   - From `error.phpStackFrames`
   - Orange-themed table distinguishing from JS

---

## Related Files

### Backend
- `backend/internal/config/config.go` - Config struct with logging settings
- `backend/cmd/server/main.go` - Startup clearing and middleware wiring
- `backend/internal/api/middleware/session_logging.go` - Request session middleware
- `backend/internal/services/requestsession/store.go` - Session storage service
- `backend/internal/api/handlers/request_session_handlers.go` - Session API handlers
- `backend/internal/api/router.go` - Route registration

### Frontend
- `src/hooks/useExecutionLogger.ts` - React execution logger
- `src/stores/errorStore.ts` - Error store with execution chain capture
- `src/components/errors/GlobalErrorModal.tsx` - Stack tab UI

---

## Configuration Reference

```json
{
  "logging": {
    "level": "info",
    "retentionDays": 7,
    "debugMode": false,
    "timeFormat": "2006-01-02 03:04:05 PM",
    "clearLogsOnStartup": false,
    "clearSessionsOnStartup": false,
    "sessionLoggingEnabled": true,
    "frontendDebugMode": false,
    "retryMaxAttempts": 3,
    "retryInitialDelayMs": 1000,
    "circuitBreakerThreshold": 5,
    "circuitBreakerCooldownMs": 60000
  }
}
```
