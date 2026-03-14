# 30 — Email Log Files as Attachments

> **ID:** 30-email-logs-as-attachment
> **Date:** 2026-03-13
> **Category:** REST API / Notification / Logging
> **Status:** Resolved

---

## Feature Summary

Both QUpload and Riseup Asia Uploader need a REST endpoint that emails log files as attachments to a configured recipient. This enables remote log retrieval without direct file system access.

---

## How It Works

### Endpoint

```
POST /wp-json/{namespace}/v1/logs/email
Headers:
  Authorization: Basic <app_username:app_password>
  X-Riseup-Source-Machine: <machine_hostname>
Body:
  {
    "recipient": "admin@example.com",    // optional — falls back to configured support_email / admin_email
    "include_archives": false,           // optional — attach archived rotations too (default: false)
    "log_types": ["log", "error", "stacktrace"]  // optional — defaults to all three
  }
```

### Step-by-Step Flow

#### 1. Authentication & Validation
- Validate app credentials (Basic Auth — existing pattern)
- Read `X-Riseup-Source-Machine` header for audit trail
- Validate `recipient` is a valid email (if provided)
- If no `recipient` provided: fall back to plugin's configured support email, then `admin_email`

#### 2. Collect Log Files
- Read the logs directory path from `FileLogger` (existing `initializePaths()`)
- For each requested `log_type`, check if the active file exists:
  - `log.txt` ← info logs
  - `error.txt` ← error logs
  - `stacktrace.txt` ← stack traces
- If `include_archives` is true, also collect files from `archive/001/`, `archive/002/`, etc.
- Skip any files that don't exist (don't error)
- If NO files exist at all → return `404` with `no_logs_found`

#### 3. Build Email
- **Subject:** `[{PluginName}] Log Files — {site_name} — {timestamp}`
- **Body (plain text):**
  ```
  {PluginName} — Log File Export
  ==================================================

  Site URL:        https://example.com
  Plugin Version:  2.6.0
  Requested By:    DEV-01 (192.168.1.100)
  Timestamp:       2026-03-13T12:34:56Z

  Attached Files:
    - log.txt (45.3 KB)
    - error.txt (12.8 KB)
    - stacktrace.txt (8.1 KB)

  --------------------------------------------------
  This email was sent from the {PluginName} plugin.
  ```
- **Attachments:** The actual log files (NOT copies — `wp_mail()` reads from disk)

#### 4. Send via `wp_mail()`
- Use `wp_mail($recipient, $subject, $body, $headers, $attachments)`
- `$headers`: `['Content-Type: text/plain; charset=UTF-8']`
- `$attachments`: Array of absolute file paths to the log files
- **Important:** `wp_mail()` reads files by path — no need to copy them to temp. The files stay on disk unchanged.

#### 5. Handle Archives (if requested)
When `include_archives` is true:
- Iterate `archive/001/`, `archive/002/`, ... in order
- Rename each archived file temporarily for clarity: `log_001.txt`, `error_002.txt`
- Copy to temp dir with renamed names → attach → clean up temp copies after send
- This avoids confusion when multiple `log.txt` files would have the same name

#### 6. Response

**Success:**
```json
{
  "success": true,
  "sent_to": "admin@example.com",
  "files_attached": ["log.txt", "error.txt", "stacktrace.txt"],
  "total_size_bytes": 67584,
  "requested_by": {
    "machine": "DEV-01",
    "ip": "192.168.1.100"
  }
}
```

**Failure (email not sent):**
```json
{
  "success": false,
  "error": "wp_mail_failed",
  "message": "Failed to send email. Ensure WordPress has email sending configured (e.g., GoSMTP, WP Mail SMTP)."
}
```

---

## WordPress Email Prerequisites

`wp_mail()` requires a working mail transport. Common setups:
- **GoSMTP plugin** (recommended for production)
- **WP Mail SMTP** plugin
- **PHP mail()** function (unreliable, often blocked by hosting)

If `wp_mail()` returns `false`, the endpoint should return a clear error message guiding the user to configure an SMTP plugin.

---

## QUpload-Specific Notes

- QUpload does NOT currently have `wp_mail()` support — this will be the first email feature
- Recipient falls back to `admin_email` (no support_email setting yet)
- No database tables to export (QUpload is file-only)

## Riseup Asia-Specific Notes

- Already has `AdminMailer` and `AdminFeedbackAjaxTrait` using `wp_mail()` with attachments
- Recipient falls back to `support_email` from `OptionNameType::SupportSettings`, then `admin_email`
- Could optionally export `ErrorSessions` as a CSV attachment (future enhancement)
- The existing feedback email flow (`AdminFeedbackAjaxTrait`) provides a proven pattern for attachments

---

## Go Backend Integration

### New API Endpoints

```
POST /api/v1/sites/{id}/remote-logs/email    → proxy to WordPress
```

The Go backend proxies the request to the WordPress site, forwarding the body and injecting `X-Riseup-Source-Machine` from `os.Hostname()`.

### React Dashboard UI

Add an "Email Logs" button to the RemoteLogsPanel (from spec #29):
- Click → optional modal to configure recipient + which logs to include
- Sends request through Go proxy
- Shows success/error toast

---

## Rate Limiting

- Max **5 email requests per hour** per site (prevent spam)
- Use WordPress transient: `{plugin_slug}_log_email_count_{hour}`
- Return `429 Too Many Requests` if exceeded

---

## Affected Files

### QUpload (PHP)
- `includes/Enums/EndpointType.php` — add `LogsEmail = 'logs/email'`
- `includes/Traits/Route/RouteRegistrationTrait.php` — register new route
- New: `includes/Traits/Log/LogEmailTrait.php` — email handler

### Riseup Asia Uploader (PHP)
- Route registration — add `logs/email` endpoint
- New: `includes/Traits/Log/LogEmailTrait.php` — email handler (can reuse `AdminMailer` patterns)

### Go Backend
- `internal/wordpress/EndpointMap.go` — add `EPLogsEmail` entry
- `internal/api/RouterRoutes.go` — add route
- Handler in `HandlerRemoteLogs.go` — proxy POST

### Frontend
- `src/lib/api/methods.ts` — add `emailRemoteLogs` method
- `src/components/sites/RemoteLogsPanel.tsx` — add "Email Logs" button

---

## Security Considerations

1. **Recipient validation:** Must be a valid email address (sanitize_email + validate)
2. **File path traversal:** Only read from the known logs directory — never accept user-provided paths
3. **Size cap:** If total attachment size exceeds 10 MB, refuse and suggest using `include_archives: false`
4. **Rate limiting:** Prevent email flooding (5/hour)
5. **App credentials required:** No anonymous access

---

## TODO and Follow-Ups

1. Implement `LogEmailTrait` for QUpload
2. Implement `LogEmailTrait` for Riseup Asia
3. Add Go proxy endpoint
4. Add React UI (button + modal)
5. Add rate limiting
6. Test with and without SMTP plugin configured
7. Test archive attachment renaming

## Done Checklist

- [x] Spec created under `spec/02-app-issues/`
- [x] PHP implementation complete (QUpload)
- [x] PHP implementation complete (Riseup Asia)
- [x] Go backend proxy complete
- [x] React API methods added
- [x] React UI complete (Email Logs button + modal in RemoteLogsPanel)
- [ ] End-to-end tested
