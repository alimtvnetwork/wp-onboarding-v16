# Memory: architecture/backend/session-logging
Updated: 2026-02-05

The session logging system provides isolated, auditable logs for every backend operation. Each operation (publish, sync, backup, connect) is assigned a unique UUID session ID, with logs persisted to `backend/data/sessions/{session_id}.log`. Sessions are automatically cleaned up after 7 days.

## Session Types

- `publish` - Plugin publishing to WordPress sites
- `sync` - File synchronization checks
- `backup` - Backup creation/restore operations
- `connect` - Connection testing
- `bulk_publish` - Publishing to multiple sites at once

## Key Service Methods

```go
sessionID, err := sessionService.StartSession(sessionType, pluginID, siteID, pluginName, siteName)
sessionService.Log(sessionID, level, step, message, details)
sessionService.LogStageStart(sessionID, stageName)
sessionService.LogStageEnd(sessionID, stageName, status, durationMs)
sessionService.EndSession(sessionID, status, errorMsg)
logs, err := sessionService.GetSessionLogs(sessionID)
```

## REST Endpoints

- `GET /api/v1/sessions` - List recent sessions
- `GET /api/v1/sessions/{id}` - Get session details
- `GET /api/v1/sessions/{id}/logs` - Get full logs
- `DELETE /api/v1/sessions/{id}` - Delete session

## WebSocket Integration

All WebSocket messages include `SessionId` for frontend correlation:

```json
{
  "Type": "publish_progress",
  "SessionId": "abc-123-def-456",
  "Data": { "PluginId": 3, "Stage": "upload" }
}
```

## Related Files

- `backend/internal/services/session/service.go` - Service implementation
- `backend/internal/api/handlers/sessions.go` - HTTP handlers
- `spec/wp-plugin-publish/01-backend/17-session-management.md` - Full specification
