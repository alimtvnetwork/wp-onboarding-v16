# Memory: architecture/backend/session-management
Updated: 2026-02-05

Every backend operation (e.g., publish, sync, connection test) is assigned a unique UUID session ID. Technical logs for each operation are persisted in separate files within 'backend/data/sessions/' to provide an isolated audit trail that can be retrieved by the frontend via a session-specific API endpoint.

## Session Types

- `publish` - Plugin publishing to WordPress sites
- `sync` - File synchronization checks
- `backup` - Backup creation/restore operations
- `connect` - Connection testing
- `bulk_publish` - Publishing to multiple sites at once

## Session Service API

```go
// Start a new session
sessionID, err := sessionService.StartSession(sessionType, pluginID, siteID, pluginName, siteName)

// Log to session
sessionService.Log(sessionID, level, step, message, details)
sessionService.LogStageStart(sessionID, "upload")
sessionService.LogStageEnd(sessionID, "upload", "success", 4200)

// End session
sessionService.EndSession(sessionID, "success", "")
```

## REST Endpoints

- `GET /api/v1/sessions` - List recent sessions (default 100)
- `GET /api/v1/sessions/{id}` - Get session details
- `GET /api/v1/sessions/{id}/logs` - Get full session logs (JSON or plain text)
- `DELETE /api/v1/sessions/{id}` - Delete a session

## Session Log Format

```
═══════════════════════════════════════════════════════════════════════════════
 SESSION: abc-123-def-456
 TYPE: publish
 STARTED: 2026-02-05 01:24:27 UTC
 PLUGIN: Category Generator (ID: 3)
 SITE: Atto Property Demo (ID: 1)
═══════════════════════════════════════════════════════════════════════════════

───────────────────────────────────────────────────────────────────────────────
 STAGE: UPLOAD
───────────────────────────────────────────────────────────────────────────────
[2026-02-05 01:24:27] [INFO] [upload] Starting upload to https://example.com
    {
      "zipPath": "...",
      "remoteSlug": "category-generator"
    }

✓ STAGE UPLOAD completed (success) in 4200ms

═══════════════════════════════════════════════════════════════════════════════
 SESSION ENDED: 2026-02-05 01:24:41 UTC
 STATUS: success
 DURATION: 14.402s
═══════════════════════════════════════════════════════════════════════════════
```

## WebSocket Integration

All WebSocket messages now include `sessionId` in the payload:

```json
{
  "type": "publish_progress",
  "sessionId": "abc-123-def-456",
  "data": {
    "pluginId": 3,
    "siteId": 1,
    "stage": "upload",
    "status": "running"
  }
}
```

## Retention

Sessions are automatically cleaned up after 7 days (configurable).

## Related Files

- `backend/internal/services/session/service.go` - Session service implementation
- `backend/internal/services/session/types.go` - Interface definition
- `backend/internal/api/handlers/sessions.go` - HTTP handlers
- `backend/internal/ws/hub.go` - WebSocket session-aware broadcasts
