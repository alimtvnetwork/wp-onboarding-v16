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
