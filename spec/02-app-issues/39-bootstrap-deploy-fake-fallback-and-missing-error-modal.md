# Issue: Bootstrap Deploy Used Fake Fallback and Hid Real Failures

> **ID:** 39-bootstrap-deploy-fake-fallback-and-missing-error-modal
> **Date:** 2026-03-22
> **Category:** Deployment / Error Reporting
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** Bulk uploader deployment showed shallow or misleading progress, failed with “No upload helper plugin available on site,” and did not reliably open the global error modal with useful details.
2. **Where it happened:** Go bootstrap service (`backend/internal/services/site/*`) and deploy dialog UI (`src/components/sites/DeployUploaderDialog.tsx`).
3. **Symptoms and impact:** Users saw fake deployment flow, missing endpoint-check logs, and no automatic modal for per-site failures even though PowerShell `-pas` showed the sites were reachable and helper plugins existed.
4. **How it was discovered:** User compared the Go deploy flow against the working PowerShell status/upload pipeline and reported that logs and modal behavior were not reflecting the real execution path.

## Root Cause Analysis

1. **Direct cause:** The bootstrap fallback path was mislabeled as an “Onboard” fallback, but `CheckOnboardAvailable()` and `UploadPluginViaOnboard()` were only compatibility aliases that routed back to Riseup Asia Uploader logic instead of using a true alternate uploader.
2. **Contributing factor:** The bootstrap flow did not emit explicit endpoint-check logs before choosing an upload path, so the UI log stream lacked the same diagnostic clarity as the PowerShell scripts.
3. **Contributing factor:** Bulk bootstrap returns HTTP success with per-site result objects; the frontend treated that as a normal successful request and never auto-opened the error modal when individual sites failed.
4. **Why existing safeguards failed:** `requireSuccess()` only catches transport/envelope failures, not partial failures encoded inside a successful payload.

## Affected Files

### Go Backend
- `backend/internal/services/site/ServiceBootstrapUpload.go` — incorrect fallback logic and missing endpoint-check logs.

### Frontend
- `src/components/sites/DeployUploaderDialog.tsx` — partial deployment failures were rendered in the dialog only, without promoting them to the global error modal.

## Fix Description

1. **Backend:** Replace the fake “Onboard” bootstrap fallback with a real QUpload fallback using `CheckQUploadAvailable()` and `UploadPluginViaQUpload()`.
2. **Backend:** Add explicit deployment logs for ZIP creation, helper endpoint checks, fallback selection, and upload target selection.
3. **Frontend:** When bulk deployment returns one or more failed site results, append detailed per-site logs and automatically open the global error modal with a synthesized summary that can be copied/exported.

## Prevention and Non-Regression

1. **Rule:** Bootstrap fallback paths must call a genuinely different remote transport, never a compatibility alias that loops back to the primary uploader.
2. **Rule:** Multi-site operations that return partial failures inside HTTP 200 responses must still surface those failures through the global error modal.
3. **Acceptance criteria:**
   - Deploy dialog logs show ZIP creation and endpoint checks.
   - Sites with only QUpload installed can bootstrap successfully.
   - Any failed site in bulk deploy opens a copyable error modal automatically.

## TODO and Follow-Ups

1. Verify deploy flow end-to-end against the same sites used with `run.ps1 -pas`.
2. Consider adding session-scoped deploy logs instead of relying on global WS log events.
3. Align bulk deploy UX with publish/sync operation log model if deeper progress tracking is needed.

## Done Checklist

- [x] Root cause documented
- [x] Fallback logic corrected
- [x] Endpoint-check logs added
- [x] Error modal auto-opened for partial failures
- [ ] End-to-end verification recorded