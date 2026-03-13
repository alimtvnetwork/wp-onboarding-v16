# 29 — Two-Step Remote Log Clearing

> **ID:** 29-two-step-remote-log-clearing
> **Date:** 2026-03-13
> **Category:** REST API / Security / Logging
> **Status:** Open

---

## Feature Summary

Both QUpload and Riseup Asia Uploader need a **two-step confirmation flow** for remotely clearing log files via REST API. The Go backend dashboard must also proxy these operations and display log status.

### What Gets Cleared (All-at-Once)

| Target | QUpload | Riseup Asia |
|---|---|---|
| Info log (`log.txt`) | ✅ | ✅ |
| Error log (`error.txt`) | ✅ | ✅ |
| Stack trace (`stacktrace.txt`) | ✅ | ✅ |
| Activity log (SQLite `Transactions` table) | — | ✅ |
| Error sessions (SQLite `ErrorSessions` table) | — | ✅ |

---

## Two-Step Flow Design

### Step 1: Initiate Clearing (Request Token)

```
DELETE /wp-json/{namespace}/v1/logs/clear
Headers:
  Authorization: Basic <app_username:app_password>
  X-Riseup-Source-Machine: <machine_hostname>
Body: (none)
```

**Validation:**
1. Authenticate via existing app credentials (`AppUsername` / `AppPassword`)
2. Read `X-Riseup-Source-Machine` header — REQUIRED
3. Check machine name against the **approved machines list** stored in plugin settings
4. If machine is NOT approved → `403 Forbidden` with `machine_not_approved` error code
5. If approved → generate a random confirmation token (32 hex chars)
6. Store token as WordPress transient with **60-second TTL**:
   - Key: `{plugin_slug}_clear_token_{md5(machine_name)}`
   - Value: `{ token, machine, requested_at, requested_by (IP) }`
7. Return response:

```json
{
  "success": true,
  "confirmation_required": true,
  "confirm_endpoint": "/wp-json/{namespace}/v1/logs/clear/confirm",
  "token": "a1b2c3d4e5f6...",
  "expires_in": 60,
  "message": "Confirmation required. Send POST to confirm_endpoint within 60 seconds."
}
```

### Step 2: Confirm Clearing (Execute Deletion)

```
POST /wp-json/{namespace}/v1/logs/clear/confirm
Headers:
  Authorization: Basic <app_username:app_password>
  X-Riseup-Source-Machine: <machine_hostname>
Body:
  { "token": "a1b2c3d4e5f6..." }
```

**Validation:**
1. Authenticate via app credentials (same as Step 1)
2. Read `X-Riseup-Source-Machine` — must match the machine from Step 1
3. Retrieve transient by key `{plugin_slug}_clear_token_{md5(machine_name)}`
4. If transient is missing or expired → `410 Gone` with `token_expired` error code
5. Validate token matches stored value
6. If machine name doesn't match stored machine → `403 Forbidden`
7. **Delete the transient immediately** (single-use)
8. Execute the actual log clearing (see below)
9. Return response:

```json
{
  "success": true,
  "cleared": {
    "log_file": true,
    "error_file": true,
    "stacktrace_file": true,
    "activity_log": true,     // Riseup Asia only
    "error_sessions": true    // Riseup Asia only
  },
  "cleared_by": {
    "machine": "DEV-01",
    "ip": "192.168.1.100",
    "timestamp": "2026-03-13T12:34:56Z"
  }
}
```

---

## Approved Machines List

### Storage Location

Each plugin stores approved machines in its settings (WordPress options table):

**QUpload:** `qupload_settings` option → `approved_machines` array
**Riseup Asia:** existing settings mechanism → `approved_machines` array

```json
{
  "approved_machines": ["DEV-01", "DEV-02", "PROD-DEPLOY"],
  "logging": { "maxLogSizeBytes": 524288 }
}
```

### Management

- Managed via WordPress admin UI (settings page) or seeded from `config.json`
- If `approved_machines` is empty or not set → **all machines are blocked** (fail-closed)
- Machine names are case-insensitive for comparison

---

## Actual Clearing Logic

### QUpload

1. Delete `log.txt`, `error.txt`, `stacktrace.txt` from the logs directory using `PathHelper::deleteFile()`
2. Use the existing hardened deletion pattern (realpath + clearstatcache + chmod retry + post-delete verification)

### Riseup Asia Uploader

1. Delete `log.txt`, `error.txt`, `stacktrace.txt` (same as QUpload)
2. Truncate `Transactions` table: `DELETE FROM Transactions`
3. Truncate `ErrorSessions` table: `DELETE FROM ErrorSessions`
4. **Post-deletion audit entry:** After clearing, insert ONE new activity log entry recording the clearing action:

```php
$this->db->insertTransaction([
    'action'         => 'logs_cleared',
    'status'         => 'success',
    'source_machine' => $machineName,
    'details'        => json_encode([
        'cleared_files'    => ['log.txt', 'error.txt', 'stacktrace.txt'],
        'cleared_tables'   => ['Transactions', 'ErrorSessions'],
        'cleared_by_ip'    => $clientIp,
        'cleared_at'       => gmdate('Y-m-d\TH:i:s\Z'),
    ]),
]);
```

This ensures there is always an audit trail of WHO cleared the logs and WHEN, even after the logs themselves are gone.

---

## Go Backend Integration

### New API Endpoints (Go Router)

```
GET    /api/v1/sites/{id}/remote-logs          → list log files with metadata
DELETE /api/v1/sites/{id}/remote-logs/clear     → proxy Step 1 to WordPress
POST   /api/v1/sites/{id}/remote-logs/confirm   → proxy Step 2 to WordPress
```

### GET /api/v1/sites/{id}/remote-logs

Calls WordPress endpoint to retrieve log file information:
- File sizes (bytes)
- Last modified timestamps
- Line counts
- Whether archive folders exist and their count

**WordPress endpoint needed:**
```
GET /wp-json/{namespace}/v1/logs/status
```

Returns:
```json
{
  "success": true,
  "logs": {
    "log_file": { "exists": true, "size_bytes": 45320, "last_modified": "2026-03-13T10:00:00Z", "line_count": 312 },
    "error_file": { "exists": true, "size_bytes": 12800, "last_modified": "2026-03-13T09:30:00Z", "line_count": 48 },
    "stacktrace_file": { "exists": false, "size_bytes": 0, "last_modified": null, "line_count": 0 },
    "archive_count": 3
  },
  "database": {
    "transaction_count": 156,
    "error_session_count": 12
  }
}
```

### DELETE + POST Proxy

The Go backend acts as a transparent proxy:
1. User clicks "Clear Logs" in the React dashboard
2. Go sends DELETE to WordPress (Step 1), gets token back
3. UI shows confirmation dialog with details (machine name, what will be cleared)
4. User confirms → Go sends POST with token to WordPress (Step 2)
5. UI updates with results

The Go backend injects the `X-Riseup-Source-Machine` header using the server's hostname (from `os.Hostname()` or config).

### Endpoint Map Updates

Add to `EndpointMap.go`:

```go
EPLogsStatus:    {Method: httpmethod.Get,    Endpoint: ep.LogsStatus},
EPLogsClear:     {Method: httpmethod.Delete, Endpoint: ep.LogsClear},
EPLogsConfirm:   {Method: httpmethod.Post,   Endpoint: ep.LogsConfirm},
```

---

## React Dashboard UI

### Log Status Panel

Add a "Remote Logs" section to the site detail page showing:
- Log file cards (size, last modified, line count) for each file type
- Archive folder count badge
- Database record counts (Riseup Asia only)
- "Clear All Logs" button (danger-styled)

### Confirmation Flow

1. Click "Clear All Logs" → calls DELETE proxy endpoint
2. Shows confirmation modal:
   - "This will permanently delete all log files and database records on {site_name}"
   - Shows machine name that will be recorded
   - "Confirm" + "Cancel" buttons
   - 60-second countdown timer
3. On confirm → calls POST confirm endpoint
4. Shows result toast with cleared items

---

## PHP Implementation Details

### New Endpoints Per Plugin

| Plugin | Endpoint | Method | Handler |
|---|---|---|---|
| QUpload | `/logs/status` | GET | `handleLogsStatus` |
| QUpload | `/logs/clear` | DELETE | `handleLogsClearRequest` |
| QUpload | `/logs/clear/confirm` | POST | `handleLogsClearConfirm` |
| Riseup Asia | `/logs/status` | GET | `handleLogsStatus` |
| Riseup Asia | `/logs/clear` | DELETE | `handleLogsClearRequest` |
| Riseup Asia | `/logs/clear/confirm` | POST | `handleLogsClearConfirm` |

### New Enum Cases

**QUpload `EndpointType`:**
```php
case LogsStatus  = 'logs/status';
case LogsClear   = 'logs/clear';
case LogsConfirm = 'logs/clear/confirm';
```

**QUpload `HttpMethodType`:**
```php
case Delete = 'DELETE';
```

### Trait Organization

- `LogClearingTrait` — shared log file clearing logic (both plugins)
- `LogStatusTrait` — shared log status retrieval (both plugins)
- Riseup Asia adds DB clearing in its own handler extension

### Security Considerations

1. **Rate limiting:** Max 3 clear requests per machine per hour (use existing `onboard_rate_limit` pattern)
2. **Token is single-use:** Deleted from transients immediately upon consumption
3. **Machine validation:** Fail-closed — empty approved list blocks all requests
4. **Audit trail:** Riseup Asia always keeps one post-clearing activity entry
5. **App credentials required on BOTH steps** — token alone is insufficient
6. **Machine name must match between Step 1 and Step 2**

---

## Affected Files

### QUpload (PHP)
- `includes/Enums/EndpointType.php` — add LogsStatus, LogsClear, LogsConfirm cases
- `includes/Enums/HttpMethodType.php` — add Delete case
- `includes/Traits/Route/RouteRegistrationTrait.php` — register 3 new routes
- New: `includes/Traits/Log/LogClearingTrait.php` — two-step clearing handlers
- New: `includes/Traits/Log/LogStatusTrait.php` — log status handler

### Riseup Asia Uploader (PHP)
- `includes/Enums/EndpointType.php` — add LogsStatus, LogsClear, LogsConfirm cases (if enum-based)
- `includes/Traits/Route/` — register 3 new routes
- New: `includes/Traits/Log/LogClearingTrait.php` — two-step clearing + DB truncation
- New: `includes/Traits/Log/LogStatusTrait.php` — log status + DB counts

### Go Backend
- `internal/wordpress/EndpointMap.go` — add 3 new endpoint entries per plugin
- `internal/api/RouterRoutes.go` — add 3 new routes
- New: `internal/api/handlers/HandlerRemoteLogs.go` — proxy handlers
- `internal/wordpress/Client.go` — may need DELETE method support

### Frontend (React/TypeScript)
- `src/lib/api/methods.ts` — add `getRemoteLogs`, `clearRemoteLogs`, `confirmClearRemoteLogs`
- New: `src/components/sites/RemoteLogsPanel.tsx` — log status + clear UI

### PowerShell
- `scripts/upload-plugin.ps1` — optional: add `-ClearLogs` flag for scripted clearing

---

## TODO and Follow-Ups

1. Add `approved_machines` to plugin settings schema (both plugins)
2. Implement `LogsStatus` endpoint (both plugins)
3. Implement two-step clearing endpoints (both plugins)
4. Add DB clearing for Riseup Asia (Transactions + ErrorSessions)
5. Add post-deletion audit entry for Riseup Asia
6. Add Go proxy endpoints + handlers
7. Add React dashboard UI (RemoteLogsPanel)
8. Add frontend API methods
9. Update EndpointMap.go with new entries
10. Add rate limiting to clearing endpoints
11. Test end-to-end: Go dashboard → WordPress clearing → audit trail

## Done Checklist

- [x] Spec created under `spec/02-app-issues/`
- [ ] PHP implementation complete (QUpload)
- [ ] PHP implementation complete (Riseup Asia)
- [ ] Go backend proxy complete
- [ ] React UI complete
- [ ] Settings UI for approved machines
- [ ] End-to-end tested
