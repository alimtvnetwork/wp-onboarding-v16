# Memory: architecture/backend/remote-plugin-session-logging
Updated: 2026-02-05

## Overview

Remote plugin actions (enable/disable/delete) now use session-based logging for full diagnostic traceability.

## Session Types

```go
SessionTypeRemotePluginEnable  = "remote_plugin_enable"
SessionTypeRemotePluginDisable = "remote_plugin_disable"
SessionTypeRemotePluginDelete  = "remote_plugin_delete"
```

## Session Flow

1. **Start Session** - Creates UUID, opens log file
2. **Log Steps** - decrypt, connect, action execution
3. **Capture Errors** - PHP stack trace frames extracted from API response
4. **End Session** - Writes footer with status/duration

## PHP Stack Trace Capture

When WordPress returns an error, the Go backend:
1. Parses JSON response body
2. Extracts `error.details.stackTraceFrames` array
3. Logs frames to session file
4. Writes to `data/errors/error.log.txt`

## WebSocket Events

- `remote_plugin_action_started` - Broadcast when action begins
- `remote_plugin_action_complete` - Broadcast with success/error details

## Error Log Format

Errors are appended to `data/errors/error.log.txt`:

```
[2026-02-05 12:34:56] REMOTE PLUGIN DISABLE FAILED
  Site ID: 1
  Site URL: https://example.com
  Plugin: my-plugin
  Error: API error message
  Status Code: 500
  PHP Stack Trace Frames:
    #0 ClassName::methodName() at /path/to/file.php:123
    #1 anotherFunction() at /path/to/other.php:45
───────────────────────────────────────────────────────────────────────────────
```

## Related Files

- `backend/internal/services/site/service.go` - `executeRemotePluginAction()`, `extractErrorDetails()`
- `backend/internal/services/session/service.go` - Session types
- `backend/internal/ws/hub.go` - `BroadcastRemotePluginLogWithSession()`
