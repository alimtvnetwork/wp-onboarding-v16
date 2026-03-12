# Issue #21 — Wrong QUpload API Namespace in Upload Script

> **Created:** 2026-03-12
> **Category:** PowerShell / REST API
> **Severity:** Critical (every QUpload upload would 404)

---

## 1. Issue Summary

The PowerShell upload script `upload-plugin-U-Q.ps1` used the wrong REST API namespace for the QUpload plugin:

- **Script used:** `/wp-json/qupload/v1/upload`
- **Actual namespace:** `/wp-json/qupload-api/v1/upload`

Every upload attempt via QUpload would fail with HTTP 404 ("No route was found matching the URL and request method").

**How discovered:** User attempted `.\run.ps1 -u` to upload Riseup Asia Uploader, which failed because the plugin wasn't active on the target site. When redirected to use QUpload (`.\run.ps1 -q`), code review revealed the namespace mismatch.

## 2. Root Cause Analysis

- **Direct cause:** The script hardcoded `qupload/v1` as the API path, but `PluginConfigType::ApiNamespace` is `'qupload-api'` (not `'qupload'`), making the full namespace `qupload-api/v1`.
- **Contributing factors:** No auth pre-check existed to catch 404s early with a meaningful error message. The script went straight to the upload POST without validating the API was reachable.
- **Why the spec didn't prevent it:** The QUpload spec (`spec/15-qupload-plugin/03-powershell-script.md`) documented the endpoint as `POST /wp-json/qupload/v1/upload` — the spec itself had the wrong namespace.

## 3. Fix Description

1. **Fixed namespace** in `upload-plugin-U-Q.ps1`: `qupload/v1` → `qupload-api/v1`
2. **Added auth pre-check step** (Step 3/5): Hits `GET /status` before uploading to verify:
   - QUpload plugin is active (catches 404 early)
   - Credentials are valid (catches 401/403 early)
   - Shows QUpload version and WP version on success
3. **Added full endpoint URLs** in all log output for debugging
4. **Updated step numbering** from 4 steps to 5 steps
5. **Added `-u -q` combo** to `run.ps1`: uploads Riseup Asia Uploader via QUpload API
6. **Fixed spec** (`spec/15-qupload-plugin/03-powershell-script.md`)

## 4. Prevention and Non-Regression

**Prevention rule:** All REST API endpoint URLs in scripts must derive from the same source of truth as the PHP `PluginConfigType::ApiNamespace` enum. Never hardcode API paths — reference the config or spec that defines the namespace.

**Acceptance criteria:**
- `grep -n 'qupload/v1' wp-plugins/scripts/upload-plugin-U-Q.ps1` returns zero matches (should be `qupload-api/v1`)
- `.\run.ps1 -u -q` successfully uploads Riseup Asia Uploader through QUpload
- Auth pre-check catches 404/401/403 before attempting upload

## 5. Done Checklist

- [x] Script fixed (`upload-plugin-U-Q.ps1`)
- [x] Auth pre-check added
- [x] Endpoint URLs shown in logs
- [x] `-u -q` shorthand added to `run.ps1`
- [x] Issue write-up created
- [x] Spec updated (`spec/15-qupload-plugin/03-powershell-script.md`)
- [x] Memory updated with prevention rule
