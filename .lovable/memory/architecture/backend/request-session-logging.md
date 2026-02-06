# Memory: architecture/backend/request-session-logging
Updated: 2026-02-06

## Overview

All API requests (except health checks) are now logged with full request/response details when `logging.sessionLoggingEnabled` is true in config.

## Configuration

```json
"logging": {
  "sessionLoggingEnabled": true,
  "clearLogsOnStartup": false,
  "clearSessionsOnStartup": false
}
```

## Storage Structure

Request sessions are stored in `data/request-sessions/`:
```
data/request-sessions/
├── 2026-02-06/
│   ├── 10/
│   │   ├── {uuid}.json
│   │   └── {uuid}.json
│   └── 11/
│       └── {uuid}.json
```

## Session Data Captured

Each request session captures:
- Request method, path, query string
- Request headers (sensitive ones redacted)
- Request body (truncated at 50KB)
- Response body (truncated at 50KB)
- Response status code
- Start/end timestamps, duration
- Extracted error message (if status >= 400)

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/request-sessions` | List with pagination |
| GET | `/api/v1/request-sessions/{id}` | Get single session |
| DELETE | `/api/v1/request-sessions/{id}` | Delete session |
| DELETE | `/api/v1/request-sessions` | Clear all sessions |
| GET | `/api/v1/request-sessions/errors` | List error sessions only |
| GET | `/api/v1/request-sessions/{id}/export` | Download as JSON |

## Retention

Request sessions are automatically cleaned up after 1 day (high volume).

## Context Key

The session ID is available in request context:
```go
sessionID := middleware.GetSessionID(r.Context())
```

## Related Files

- `backend/internal/api/middleware/session_logging.go` - Middleware
- `backend/internal/services/requestsession/store.go` - Storage
- `backend/internal/api/handlers/request_session_handlers.go` - API handlers
