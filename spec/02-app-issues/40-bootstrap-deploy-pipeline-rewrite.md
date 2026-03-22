# Issue: Bootstrap Deploy Pipeline Needs Full Rewrite to Match PowerShell

> **ID:** 40-bootstrap-deploy-pipeline-rewrite
> **Date:** 2026-03-22
> **Category:** Deployment / Architecture
> **Status:** Open

---

## Issue Summary

1. **What happened:** The Go bootstrap deployer has fundamental architectural flaws causing repeated 500 errors, infinite-looking retry loops, redundant ZIP creation, and missing cross-upload strategy.
2. **Where it happened:** Go bootstrap service (`backend/internal/services/site/ServiceBootstrap*.go`), bulk handler (`SiteBootstrapHandlers.go`), and deploy dialog UI (`DeployUploaderDialog.tsx`).
3. **Symptoms and impact:**
   - Upload via `riseup-asia-api/v1/upload` returns HTTP 500 on all 3 sites (plugin can't self-update while running)
   - Deployment retries endlessly, creating a new ZIP per attempt per site (seen: 20+ ZIPs created)
   - No cross-upload strategy: should ALWAYS use QUpload to upload Riseup Asia, and Riseup Asia to upload QUpload
   - ZIP created per-site instead of once upfront
   - Sites processed sequentially instead of in parallel
   - No pre-flight version comparison (like PowerShell `-pas`)
   - No progress bar / phased UI visualization
   - Error modal missing delegated server logs section
4. **How it was discovered:** User deployed via Go UI and compared against working PowerShell `-uas` pipeline.

## Root Cause Analysis

### RC-1: Self-Update Failure (HTTP 500)
**Direct cause:** The Go deployer tries to upload the Riseup Asia Uploader via its OWN REST endpoint (`riseup-asia-api/v1/upload`). A WordPress plugin cannot reliably replace its own files while its PHP code is executing — the upload handler is part of the plugin being replaced. This causes a 500 error.

**PowerShell solution:** The PowerShell pipeline uses cross-upload: QUpload's endpoint uploads Riseup Asia, and Riseup Asia's endpoint uploads QUpload. This is documented in `memory/architecture/deployment-resilience`.

**Go code error:** `executeBootstrapUpload()` in `ServiceBootstrapUpload.go` checks if Riseup Asia is available and if so, uploads VIA Riseup Asia's own endpoint. It only falls back to QUpload when Riseup Asia is NOT installed. This is backwards — when Riseup Asia IS installed, it should use QUpload's endpoint (cross-upload).

### RC-2: ZIP Created Per-Site
**Direct cause:** `BootstrapUploader()` calls `prepareBootstrapZip()` inside the per-site loop. For 3 sites, this creates 3 identical ZIPs.

**PowerShell solution:** Phase 1 zips ALL plugins once in parallel, then Phase 2 uploads the pre-built ZIPs to all sites.

### RC-3: Sequential Site Processing
**Direct cause:** `BulkBootstrapUploader` handler iterates `input.SiteIds` in a sequential `for` loop.

**PowerShell solution:** Phase 2 uploads to all sites in parallel using background jobs.

### RC-4: No Pre-Flight Status
**Direct cause:** The deploy dialog shows no version comparison or endpoint availability check before deployment starts.

**PowerShell solution:** `-pas` shows local version, remote version, plugin status, and endpoint availability for each site.

### RC-5: No Retry Limiting
**Direct cause:** The logs show the same sites being deployed 7+ times each. The frontend or backend has no mechanism to limit retries. Each attempt creates a new ZIP and repeats the full cycle.

### RC-6: Missing Delegated Error Section in Error Modal
**Direct cause:** When bulk deployment fails, the error modal shows the synthesized summary but lacks the dedicated "Delegated" tab with PHP stack traces and remote response bodies from the 500 errors.

## Correct Deployment Pipeline (from PowerShell)

### Phase 0: Pre-Flight
- Check endpoint availability for all plugins on all sites
- Show local vs remote version comparison
- Validate credentials/connectivity

### Phase 1: Parallel ZIP Creation
- ZIP all deployable plugins ONCE (not per-site)
- Use best compression (flate.BestCompression / SmallestSize)
- Respect skip list from config
- Show progress bar during ZIP creation

### Phase 2: Cross-Upload (Plugin-Sequential, Site-Parallel)
- **Step 2A:** Upload Riseup Asia Uploader to ALL sites via QUpload endpoint (parallel across sites)
- **Step 2B:** Upload QUpload to ALL sites via Riseup Asia endpoint (parallel across sites)
- This order ensures the API provider is stable before the dependent plugin uses it
- Each upload is a single attempt, no retry loop

### Phase 3: Summary
- Show per-site, per-plugin results
- Failed sites trigger error modal with full diagnostics
- Include remote response body in delegated error section

## Fix Plan — Task Breakdown

### Task 1: Rewrite Go Bootstrap Service — ZIP Once
- Create ZIP once in the bulk handler, pass path to all site uploads
- Remove `prepareBootstrapZip` from per-site `BootstrapUploader`
- Add new `BulkBootstrapPipeline` service method with phased architecture

### Task 2: Implement Cross-Upload Strategy
- When uploading Riseup Asia: ALWAYS use QUpload endpoint (never self-update)
- When uploading QUpload: ALWAYS use Riseup Asia endpoint
- Only fall back to self-endpoint if partner plugin is genuinely not installed
- Add explicit "cross-upload" log messages

### Task 3: Parallel Site Uploads in Go
- Replace sequential `for` loop with goroutine fan-out
- Use `sync.WaitGroup` or channel-based collection
- Broadcast per-site progress via WebSocket as each completes

### Task 4: Pre-Flight Endpoint Check UI
- Before deploy starts, show table of sites with:
  - Local plugin version
  - Remote plugin version (or "Not installed")
  - Endpoint status (Available / Unavailable)
  - QUpload endpoint status
- Similar to PowerShell `-pas` output

### Task 5: Phased Progress UI in Deploy Dialog
- Phase 1: ZIP progress bar (with plugin names)
- Phase 2: Per-site upload cards with spinning indicators
  - Show which endpoint is being used
  - Show success/failure as each completes
- Phase 3: Summary with copy/export

### Task 6: Delegated Error Logs in Error Modal
- Extract remote response body from 500 errors
- Parse PHP stack traces from response
- Show in dedicated "Delegated" tab in error modal
- Include endpoint URL, status code, response preview

### Task 7: Retry Limiting
- Maximum 1 attempt per plugin per site per deploy run
- No automatic retry — user must click deploy again
- Clear "no retry" policy in logs

## Affected Files

### Go Backend
- `backend/internal/services/site/ServiceBootstrap.go` — per-site ZIP removal
- `backend/internal/services/site/ServiceBootstrapUpload.go` — cross-upload logic
- `backend/internal/services/site/ServiceBootstrapZip.go` — shared ZIP creation
- `backend/internal/api/handlers/SiteBootstrapHandlers.go` — parallel fan-out, pre-built ZIP

### Frontend
- `src/components/sites/DeployUploaderDialog.tsx` — phased progress UI, pre-flight
- Error modal — delegated error tab with remote response bodies

## Prevention and Non-Regression

1. **Rule:** Plugin uploads MUST use cross-upload strategy — never upload a plugin via its own REST endpoint.
2. **Rule:** ZIP archives MUST be created once per deploy run, not per site.
3. **Rule:** Bulk operations MUST process sites in parallel with goroutines.
4. **Rule:** Deploy dialog MUST show pre-flight status before allowing deployment.
5. **Rule:** Each deploy attempt is exactly 1 try per plugin per site — no hidden retries.

## Done Checklist

- [x] Root cause documented
- [ ] Task 1: ZIP once (Go)
- [ ] Task 2: Cross-upload strategy (Go)
- [ ] Task 3: Parallel site uploads (Go)
- [ ] Task 4: Pre-flight UI
- [ ] Task 5: Phased progress UI
- [ ] Task 6: Delegated error logs
- [ ] Task 7: Retry limiting
- [ ] End-to-end verification
