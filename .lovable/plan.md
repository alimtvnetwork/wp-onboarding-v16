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

### Phase 9 ✅ — Configurable Go Stack Trace Depth (18-20 from config.json)
Added `StackTraceDepth` to `LoggingConfig` (default 20). `captureStackTraceN()` accepts configurable depth. Wired through `ClientConfig` → `Client` → all call sites.

### Phase 10 ✅ — Maximize PHP Stack Trace Depth
Changed `debug_backtrace()` from 10-frame limit to `0` (unlimited) in `error()`.

### Phase 11 ✅ — Upload Activation Error: Add Stack Trace + Traceability
Added `stackTraceFrames`, `requestUrl`, `responseUrl` to activation failure response in `handle_upload()`.

### Phase 12 — Universal Response Envelope Migration (v1.34.0)

#### Sub-phase 12.1 ✅ — Create PHP EnvelopeBuilder
Created `class-envelope-builder.php` with fluent builder API: `RiseupEnvelopeBuilder::success()` / `::error()` with PascalCase output matching the spec.

#### Sub-phase 12.2 ✅ — Migrate first endpoints (status, list-plugins, error_response)
- `handle_status()` → Envelope with single result (PascalCase keys)
- `handle_list_plugins()` → Envelope with Results array of plugins
- `error_response()` → Envelope error format with `Errors.Backend` and `Errors.DelegatedServiceErrorStack`

#### Sub-phase 12.3 ✅ — Go backend envelope-aware parsing
- Created `envelope.go` with `IsEnvelope()`, `UnwrapResults()`, `UnwrapSingleResult()` utilities
- Updated `GetUploaderStatus()` and `ListPluginsViaUploader()` with backward-compatible envelope parsing
- Updated error response parser to handle both `Errors.Backend` (envelope) and `error.details.stackTraceFrames` (legacy)

#### Sub-phase 12.4 ✅ — Migrate write endpoints (upload, enable, disable, delete)
- `handle_upload()` → Envelope with single result (plugin_slug, is_update, activated, activation_error)
- `handle_enable_plugin()` → Envelope with single result (plugin_slug, activated)
- `handle_disable_plugin()` → Envelope with single result (plugin_slug, deactivated)
- `handle_delete_plugin()` → Envelope with single result (plugin_slug, deleted)
- Bumped plugin version to 1.34.0
#### Sub-phase 12.5 ✅ — Migrate diagnostic endpoints (query-logs, logs-stats, error-logs, error-sessions)
- `handle_query_logs()` → Envelope with Results array + pagination metadata
- `handle_logs_stats()` → Envelope with single result (stats object)
- `handle_error_logs()` → Envelope with single result (version, settings, log tails)
- `handle_error_sessions()` → Envelope with Results array of entries + pagination
- Removed legacy `stackTraceFrames` from diagnostic responses (now handled by EnvelopeBuilder errors block)
#### Sub-phase 12.6 🔧 — Update frontend to parse envelope from PHP responses
