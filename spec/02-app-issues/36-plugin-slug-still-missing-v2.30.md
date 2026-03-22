# Plugin Slug Error Still Occurring on v2.30.0

> **Created:** 2026-03-22  
> **Status:** 🟡 Deployment-dependent

---

## Problem

The error `Missing required plugin slug parameter` is still being reported from remote sites running plugin version **2.30.0**.

## Analysis

The Go backend fix (sending both `plugin` and `plugin_slug` via `NewPluginSlugRequest()`) was applied in the codebase but the remote PHP plugin has not been redeployed since.

The error context confirms:
- `PluginVersion: 2.30.0`
- Endpoint: `POST /wp-json/riseup-asia-api/v1/plugins/enable`

## Root Cause

This is **not a code bug** — it's a deployment gap. The Go backend code already sends both fields. The PHP plugin at v2.30.0 reads `plugin_slug` which is now included.

## Possible Explanations

1. **Stale cached version** — the site hasn't received the latest Go backend binary
2. **Go backend not restarted** — old binary still running with the old `PluginSlugRequest` struct
3. **Frontend sending direct request** — bypassing Go backend entirely

## Resolution

1. Redeploy using `.\run.ps1 -d`
2. Verify Go binary is rebuilt and running
3. Confirm `NewPluginSlugRequest` is used in `UploaderLifecycle.go` (already verified in codebase)

## References

- `spec/02-app-issues/33-remote-plugin-slug-contract-mismatch.md` — original fix
- `backend/internal/wordpress/RequestTypes.go` — `NewPluginSlugRequest()`
- `backend/internal/wordpress/UploaderLifecycle.go` — lifecycle calls
