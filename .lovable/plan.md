## Session Diagnostics Pipeline — ✅ COMPLETED

All 5 phases have been implemented and are done.

### Phase 1 ✅ — Go Backend: Folder-Based Session Storage
Migrated session storage to `data/sessions/{uuid}/` with separate files for logs, request, response, and error data. Implemented application-filtered Go stack trace capture via `runtime.Callers()`.

### Phase 2 ✅ — Go Stack Trace Capture
Added structured stack frame extraction filtering to `wp-plugin-publish/` namespace, stored as JSON in `error.log`.

### Phase 3 ✅ — Diagnostics API Endpoint
Created `GET /api/v1/sessions/{id}/diagnostics` returning unified JSON with `request`, `response`, and `stackTrace` (Go + PHP) sections.

### Phase 4 ✅ — Frontend Diagnostic UI
Updated the Error Modal's Session tab with sub-tabs: **Logs**, **Request**, **Response**, and **Stack Trace** (Go/PHP toggle). Fetches logs and diagnostics in parallel.

### Phase 5 ✅ — WordPress Plugin Response Enrichment (v1.30.0)
Enriched all success and error responses with `requestUrl` and `responseUrl`. Bumped plugin version to 1.30.0.

---

## Diagnostic Pipeline Hardening — IN PROGRESS

### Phase 6 ✅ — CONFIRMED: Go Endpoint Map Delegation
Already implemented in `backend/internal/wordpress/endpoint_map.go`. The `GoEndpointMap` and `WPEndpointMap` provide enum-to-route mappings. `ResolveGoEndpoint()` and `ResolveWPEndpoint()` are used for delegation. No changes needed.

### Phase 7 ✅ — CONFIRMED: Error Log Format (Redefined Log Format)
Already implemented in `logToErrorFile()` (`backend/internal/services/site/service.go`). Logs include:
- **Site Request URL** (full PHP endpoint URL)
- **Backend URL** (Site Base URL)
- **Delegated Request** (Method, Endpoint, full Request Body)
- **Delegated Response** (Status Code, Response Body)
- **Error Summary** with Guard Rail detection
No changes needed.

### Phase 8 ✅ — CONFIRMED: Log Deduplication
MD5-based dedup in `logToErrorFile()` using action, siteID, plugin, endpoint, status code, and response body. "Clear Dedup Hashes" endpoint exists. No changes needed.

### Phase 9 🔧 — Configurable Go Stack Trace Depth (18-20 from config.json)
**Current**: `captureStackTrace()` in `uploader.go` hardcodes depth to 10 frames and buffer to 32.
**Target**: Read depth from `config.json` (`Logging.StackTraceDepth`, default 20). Apply everywhere `captureStackTrace` is called.

Files to change:
- `backend/internal/config/config.go` — Add `StackTraceDepth int` to `LoggingConfig` (default 20)
- `backend/internal/wordpress/uploader.go` — Accept depth parameter in `captureStackTrace()`
- `backend/pkg/apperror/error.go` — Update `captureStackTrace()` to use configurable depth

### Phase 10 🔧 — Maximize PHP Stack Trace Depth
**Current**: `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)` — limited to 10 frames.
**Target**: Remove the limit (use 0 for unlimited) in `error()` and `log_exception()`.

Files to change:
- `wp-plugins/riseup-asia-uploader/includes/class-file-logger.php` — Change limit to 0 in `error()`
- Version bump to 1.33.0

### Phase 11 🔧 — Upload Activation Error: Add Stack Trace + Traceability
**Current**: When `activate_plugin()` fails in `handle_upload()`, the response returns a plain success response without `stackTraceFrames`, `requestUrl`, or `responseUrl`.
**Target**: Include full diagnostic metadata in activation failure responses.

Files to change:
- `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` — Enrich activation failure response

### Phase 12 📋 — Universal Response Envelope Migration Plan (PHP Plugin)
**Current**: PHP plugin endpoints return ad-hoc JSON structures (`{success, plugins, ...}`).
**Target**: All endpoints should follow the Universal Response Envelope spec with PascalCase keys: `Status`, `Attributes`, `Results`, `Navigation`, `Errors`, `MethodsStack`.

Sub-phases:
1. Create a PHP `EnvelopeBuilder` utility class
2. Migrate read endpoints (status, list-plugins, error-logs)
3. Migrate write endpoints (upload, enable, disable, delete)
4. Migrate diagnostic endpoints (error-sessions)
5. Update Go backend parsing to handle envelope format
6. Update frontend to parse envelope from PHP responses

**NOTE**: Large migration — execute incrementally to avoid breaking integration.
