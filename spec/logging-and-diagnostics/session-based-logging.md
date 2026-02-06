# Specification: Session-Based Logging System

**Version:** 1.0.0  
**Created:** 2026-02-06  
**Status:** Implemented

---

## 1. Executive Summary

The session-based logging system provides complete request/response traceability for all API calls. Each HTTP request is assigned a unique session ID, and full diagnostic data is captured and persisted for later retrieval and analysis.

---

## 2. Requirements

### 2.1 Functional Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| F1 | Every API request must be assigned a unique session ID | MUST |
| F2 | Request headers, body, and metadata must be captured | MUST |
| F3 | Response status, body, and timing must be captured | MUST |
| F4 | Sensitive headers must be redacted before storage | MUST |
| F5 | Sessions must be retrievable via API | MUST |
| F6 | Sessions must be filterable by method, path, status | SHOULD |
| F7 | Error sessions must be easily identifiable | MUST |
| F8 | Sessions must auto-expire after retention period | SHOULD |
| F9 | Session logging must be toggleable via config | MUST |
| F10 | Health check endpoints must be excluded | MUST |

### 2.2 Non-Functional Requirements

| ID | Requirement | Target |
|----|-------------|--------|
| NF1 | Logging overhead | < 5ms per request |
| NF2 | Storage efficiency | < 10KB per session average |
| NF3 | Retention period | 1 day (configurable) |
| NF4 | Max body capture | 50KB per request/response |

---

## 3. Architecture

### 3.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        HTTP Request                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Session Logging Middleware                     │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ Generate ID │──│ Capture Req │──│ Wrap ResponseWriter     │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Request Handler                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Session Logging Middleware                     │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │
│  │ Capture Response│──│ Extract Errors  │──│ Save to Store   │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Request Session Store                         │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ data/request-sessions/{date}/{hour}/{uuid}.json             ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Data Flow

1. **Request Arrives** → Middleware intercepts
2. **Generate Session** → UUID created, stored in context
3. **Capture Request** → Headers (redacted), body (truncated), metadata
4. **Execute Handler** → Normal request processing
5. **Capture Response** → Status, body (truncated), timing
6. **Extract Errors** → Parse error message if status >= 400
7. **Persist Session** → Write JSON to file store

---

## 4. Data Model

### 4.1 RequestSession

```go
type RequestSession struct {
    // Identity
    ID string `json:"id"` // UUID v4
    
    // Request Data
    Method         string            `json:"method"`
    Path           string            `json:"path"`
    QueryString    string            `json:"queryString,omitempty"`
    RequestHeaders map[string]string `json:"requestHeaders"`
    RequestBody    string            `json:"requestBody,omitempty"`
    
    // Response Data
    ResponseStatus int    `json:"responseStatus"`
    ResponseBody   string `json:"responseBody,omitempty"`
    
    // Timing
    StartTime  time.Time `json:"startTime"`
    EndTime    time.Time `json:"endTime"`
    DurationMs int64     `json:"durationMs"`
    
    // Error (extracted from response if status >= 400)
    Error string `json:"error,omitempty"`
}
```

### 4.2 Storage Format

```json
{
  "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "method": "POST",
  "path": "/api/v1/plugins/1/publish",
  "queryString": "force=true",
  "requestHeaders": {
    "content-type": "application/json",
    "authorization": "[REDACTED]"
  },
  "requestBody": "{\"siteId\": 1}",
  "responseStatus": 500,
  "responseBody": "{\"success\":false,\"error\":{\"code\":\"E5001\",\"message\":\"Upload failed\"}}",
  "startTime": "2026-02-06T10:30:00.000Z",
  "endTime": "2026-02-06T10:30:02.500Z",
  "durationMs": 2500,
  "error": "Upload failed"
}
```

---

## 5. API Specification

### 5.1 List Sessions

```
GET /api/v1/request-sessions
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| limit | int | 50 | Results per page (max 500) |
| offset | int | 0 | Pagination offset |
| method | string | - | Filter by HTTP method |
| path | string | - | Filter by path substring |
| status | int | - | Filter by status code |
| errorsOnly | bool | false | Only error sessions |

**Response:**

```json
{
  "success": true,
  "data": {
    "sessions": [...],
    "total": 150,
    "limit": 50,
    "offset": 0
  }
}
```

### 5.2 Get Session

```
GET /api/v1/request-sessions/{id}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": "...",
    "method": "POST",
    ...
  }
}
```

### 5.3 Delete Session

```
DELETE /api/v1/request-sessions/{id}
```

### 5.4 Clear All Sessions

```
DELETE /api/v1/request-sessions
```

### 5.5 List Error Sessions

```
GET /api/v1/request-sessions/errors
```

Shorthand for `?errorsOnly=true`

### 5.6 Export Session

```
GET /api/v1/request-sessions/{id}/export
```

Returns session as downloadable JSON file.

---

## 6. Configuration

### 6.1 Config Schema

```json
{
  "logging": {
    "sessionLoggingEnabled": true,
    "clearLogsOnStartup": false,
    "clearSessionsOnStartup": false
  }
}
```

### 6.2 Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| SESSION_LOGGING_ENABLED | true | Enable/disable session logging |
| SESSION_RETENTION_DAYS | 1 | Days to retain sessions |
| SESSION_MAX_BODY_SIZE | 51200 | Max body capture in bytes |

---

## 7. Security Considerations

### 7.1 Header Redaction

The following headers are automatically redacted:

- `Authorization`
- `Cookie`
- `X-API-Key`
- `X-Auth-Token`

### 7.2 Body Truncation

Request and response bodies are truncated at 50KB to prevent:
- Disk exhaustion from large payloads
- Memory pressure during capture
- Slow reads when listing sessions

### 7.3 Retention

Sessions auto-expire after 1 day to:
- Limit disk usage
- Reduce exposure of sensitive data
- Keep queries performant

---

## 8. Implementation Files

| File | Purpose |
|------|---------|
| `backend/internal/api/middleware/session_logging.go` | Middleware implementation |
| `backend/internal/services/requestsession/store.go` | File-based storage |
| `backend/internal/api/handlers/request_session_handlers.go` | API handlers |
| `backend/internal/api/router.go` | Route registration |
| `backend/cmd/server/main.go` | Initialization |

---

## 9. Testing

### 9.1 Unit Tests

- Middleware captures all request fields
- Response writer wrapper works correctly
- Header redaction functions properly
- Body truncation at limit
- Error extraction from JSON response

### 9.2 Integration Tests

- Session persisted to disk
- Session retrievable via API
- Filters work correctly
- Pagination works correctly
- Cleanup runs on schedule

---

## 10. Monitoring

### 10.1 Metrics

- `request_sessions_total` - Total sessions created
- `request_sessions_errors` - Sessions with errors
- `request_sessions_duration_ms` - Capture overhead
- `request_sessions_disk_bytes` - Storage used

### 10.2 Alerts

- Disk usage > 1GB
- Error rate > 10%
- Capture overhead > 10ms

---

## 11. Future Enhancements

1. **Search** - Full-text search of request/response bodies
2. **Compression** - Gzip session files for storage efficiency
3. **Streaming** - Real-time session streaming via WebSocket
4. **Correlation** - Link related sessions (e.g., retry chains)
5. **Export** - Bulk export for external analysis

---

*Specification created: 2026-02-06*
