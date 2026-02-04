# Memory: architecture/backend/websocket-log-streaming
Updated: 2026-02-04

## WebSocket Log Streaming

Backend operations broadcast detailed execution logs via WebSocket for real-time frontend display.

### Log Event Structure

All log events use the `log` event type with this payload:
```json
{
  "type": "log",
  "data": {
    "operationType": "publish" | "sync" | "backup",
    "pluginId": 123,
    "siteId": 456,
    "log": {
      "timestamp": "2026-02-04T19:00:00Z",
      "level": "debug" | "info" | "warn" | "error",
      "step": "backup" | "package" | "upload" | "activate" | etc.,
      "message": "Human-readable log message",
      "details": { optional additional context }
    }
  }
}
```

### Hub Methods

```go
// Generic operation log
hub.BroadcastOperationLog(operationType string, pluginID, siteID int64, entry OperationLogEntry)

// Convenience methods
hub.BroadcastPublishLog(pluginID, siteID int64, level, step, message string, details map[string]interface{})
hub.BroadcastSyncLog(pluginID, siteID int64, level, step, message string, details map[string]interface{})
hub.BroadcastBackupLog(pluginID int64, level, step, message string, details map[string]interface{})
```

### Frontend Consumption

The frontend `PublishProgressDialog` and other live log components:
1. Subscribe to `log` events via `wsClient.on(WS_EVENTS.LOG, handler)`
2. Filter by `pluginId` and `siteId` to show only relevant logs
3. Store logs in state for display in collapsible log panel
4. Pass logs to error store when errors occur for full report generation

### Usage in Services

Every long-running operation step should broadcast a log:
```go
// At the start of a stage
s.wsHub.BroadcastPublishLog(pluginID, siteID, "info", "backup", "Creating backup...", nil)

// On success
s.wsHub.BroadcastPublishLog(pluginID, siteID, "info", "backup", "Backup created successfully", map[string]interface{}{
  "backupId": backupID,
  "size": fileSize,
})

// On error
s.wsHub.BroadcastPublishLog(pluginID, siteID, "error", "backup", "Backup failed: "+err.Error(), nil)
```

### Related Files
- `backend/internal/ws/hub.go` - WebSocket hub with log broadcast methods
- `backend/internal/services/publish/service.go` - Publish service with log streaming
- `backend/internal/services/sync/service.go` - Sync service with log streaming
- `src/components/plugins/PublishProgressDialog.tsx` - Frontend log display
- `src/stores/errorStore.ts` - BackendLogEntry type definition
