# Log Retrieval — REST API Endpoint Specification

> **Endpoint:** `GET /logs/retrieve`  
> **Applies to:** QUpload (`qupload-api/v1`), RiseUp Asia (`riseup-api/v1`)

---

## Endpoint Definition

### `GET /logs/retrieve`

Returns the tail contents of one or more log files with configurable line limits.

**Authentication:** Application Password (`activate_plugins` capability)

---

## Request Parameters

All parameters are **optional query parameters** with sensible defaults.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_info_log` | bool | `true` | Include the general info log (`log.txt`) |
| `include_error_log` | bool | `true` | Include the error log (`error.txt`) |
| `include_stacktrace` | bool | `true` | Include the stack trace log (`stacktrace.txt`) |
| `max_lines` | int | `200` | Maximum number of lines to return per file (min: 10, max: 5000) |

### Example Requests

```
GET /wp-json/qupload-api/v1/logs/retrieve
GET /wp-json/qupload-api/v1/logs/retrieve?include_info_log=false&max_lines=500
GET /wp-json/riseup-api/v1/logs/retrieve?include_stacktrace=false&max_lines=100
```

---

## Response Schema

### Success Response (HTTP 200)

```json
{
  "Success": true,
  "Version": "2.18.0",
  "RequestedAt": "2026-03-18T14:30:00.000Z",
  "Settings": {
    "include_info_log": true,
    "include_error_log": true,
    "include_stacktrace": true,
    "max_lines": 200
  },
  "InfoLog": {
    "Exists": true,
    "File": "log.txt",
    "Path": "/var/www/html/wp-content/uploads/qupload/logs/log.txt",
    "Content": "2026-03-18T14:29:55.123Z [INFO] Status endpoint called\n...",
    "Lines": 150,
    "TotalLines": 150,
    "TotalSize": 24576,
    "Truncated": false
  },
  "ErrorLog": {
    "Exists": true,
    "File": "error.txt",
    "Path": "/var/www/html/wp-content/uploads/qupload/logs/error.txt",
    "Content": "2026-03-18T14:25:00.456Z [ERROR] Upload failed: ...",
    "Lines": 42,
    "TotalLines": 42,
    "TotalSize": 8192,
    "Truncated": false
  },
  "StacktraceLog": {
    "Exists": false,
    "File": "stacktrace.txt",
    "Path": "/var/www/html/wp-content/uploads/qupload/logs/stacktrace.txt",
    "Content": "",
    "Lines": 0,
    "TotalLines": 0,
    "TotalSize": 0,
    "Truncated": false
  }
}
```

### Log File Object Schema

Each log file entry (`InfoLog`, `ErrorLog`, `StacktraceLog`) follows this structure:

| Field | Type | Description |
|-------|------|-------------|
| `Exists` | bool | Whether the file exists on disk |
| `File` | string | File basename (e.g., `log.txt`) |
| `Path` | string | Full server path |
| `Content` | string | File content (last N lines, newline-separated) |
| `Lines` | int | Number of lines returned |
| `TotalLines` | int | Total lines in the file |
| `TotalSize` | int | File size in bytes |
| `Truncated` | bool | `true` if `TotalLines > max_lines` |

### When a File Is Not Requested

If `include_info_log=false`, the `InfoLog` key is **omitted entirely** from the response (not null, not empty — absent).

### When a File Does Not Exist

If the file doesn't exist on disk, the object is returned with `Exists: false` and all counts/content at zero/empty.

---

## Error Responses

### 401 Unauthorized

Missing or invalid Application Password.

### 403 Forbidden

Authenticated user lacks `activate_plugins` capability.

### 500 Internal Server Error

```json
{
  "Success": false,
  "Code": 500,
  "Message": "Failed to read log files",
  "Error": "Permission denied: /var/www/.../logs/log.txt"
}
```

---

## Constraints

1. **max_lines clamping:** Values below 10 are raised to 10; values above 5000 are capped at 5000.
2. **File reading:** Uses `file()` with `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES` and returns the last N lines via `array_slice`.
3. **Memory safety:** For very large files (>50MB), the endpoint should return a truncated result with a warning rather than attempting to load the entire file.
4. **No file writes:** This endpoint is strictly read-only.

---

## Endpoint Summary (both plugins)

| # | Method | Endpoint | Trait | Description |
|---|--------|----------|-------|-------------|
| — | GET | `/logs/retrieve` | `LogRetrievalTrait` | Return log file contents |

This endpoint is registered alongside the existing log management endpoints (`/logs/status`, `/logs/rotation-status`, `/logs/clear`, `/logs/clear/confirm`, `/logs/email`).
