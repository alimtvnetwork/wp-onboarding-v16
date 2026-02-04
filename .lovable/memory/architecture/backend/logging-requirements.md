# Backend Logging Requirements

## File-Based Logging

All backend logs are written to persistent files in `data/errors/`:
- `log.txt` - All log entries (INFO, WARN, ERROR, FATAL)
- `error.log.txt` - Error and fatal logs only

## API Endpoint

A `/api/v1/errors/bundle` endpoint creates and serves a ZIP bundle containing both log files plus a manifest.json with metadata for easy sharing and support.

## External API Failure Logging

When an external API call fails (especially WordPress REST API calls), logs MUST include:
1. The fully resolved request URL
2. The HTTP method used  
3. The response status code
4. The full response body (truncated to 8KB if larger)

This requirement applies to all WordPress client methods including activation, upload, and mutation token requests.

## Real Plugin Upload

The publish service uses the companion plugin (`onboard-plugin/v1`) for real plugin uploads when available. The `UploadPluginZip` method:
1. Requests a mutation token for 'upload' action
2. POSTs the ZIP as multipart/form-data
3. Logs all request/response details for diagnostics

If the companion plugin is not installed, upload is simulated with a warning log.

## Mandatory Logging Standards

Every backend operation MUST include detailed inner logs that capture:

### 1. Connection Operations
- **WordPress Connection**: Log site URL, username (not password), connection initiation
- **Database Operations**: Log table name, operation type, affected rows
- **External API calls**: Log full resolved URL, method, request size, response status, and response body snippet (first ~8KB) on non-2xx

### 2. File Operations
- **ZIP Creation**: Log source path, output path, file count, ZIP size in bytes
- **File Upload**: Log file path, destination URL, file size
- **File Extraction**: Log source archive, destination path, extracted count

### 3. Stage-by-Stage Logging
Every multi-stage operation (publish, sync, backup) MUST log:
```
[TIMESTAMP] [LEVEL] [STEP] Message with context
```

Include structured details in log entries:
```go
s.broadcastDetailedLog(pluginID, siteID, "info", "package", "Creating ZIP", map[string]interface{}{
    "pluginPath": plug.Path,
    "zipPath":    zipPath,
    "fileCount":  fileCount,
    "zipSize":    info.Size(),
})
```

### 4. Error Context
All errors MUST include:
- The step/stage where error occurred
- Full error message
- Relevant context (IDs, paths, URLs)
- Stack trace for unexpected errors

## Frontend Log Display

The frontend MUST:
1. Display logs in a dedicated tab (not inline to avoid scroll issues)
2. Use `LogViewer` component with proper height constraints
3. Show log count in tab badge
4. Support copy-to-clipboard for all logs

## On-disk Log Files (Troubleshooting Bundles)

The backend MUST maintain persistent local log files under `backend/data/errors/`:
- `log.txt` - all backend logs
- `error.log.txt` - errors/fatals only

These files are used as the first-line artifact when investigating publish/sync/connection failures.

## Timestamp Format Consistency

All streamed operation logs (WebSocket `log` events) MUST use consistent UTC ISO8601 format:
```
2026-02-04T19:21:17.339Z
```

NOT mixed formats like:
- `2026-02-05T03:21:17+08:00` (local timezone)
- `2026-02-04T19:21:17.339Z` (UTC)

Pick ONE format and use it everywhere.

---

*Last Updated: 2026-02-04*
