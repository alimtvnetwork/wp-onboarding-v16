# Issue: QUpload Activate Endpoint Uses POST Instead of PUT

> **Severity:** Low (design issue, no runtime breakage)
> **Date:** 2026-03-13
> **Status:** Resolved (2026-03-17)

## Summary

The QUpload `/activate` endpoint and the Riseup Asia Uploader `/enable` endpoint both used POST, but should use PUT since plugin activation is an idempotent state change on an existing resource.

## Resolution

All layers updated to PUT:
- PHP route registration (QUpload + plugins-onboard)
- Go backend client and endpoint maps
- Frontend API client (already PUT) + error context strings
- Specs and memory docs

## Scope of Change

All layers: PHP route registration → Go backend client → frontend API → PowerShell scripts → specs.

## Reference

- Full write-up: `spec/02-app-issues/26-qupload-activate-should-use-put.md`
