# Issue #007: Upload 404 rest_no_route on Remote Sites

> **Date:** 2026-03-16  
> **Severity:** High  
> **Status:** Root cause identified — deployment version mismatch

---

## Summary

Running `.\run.ps1 -uas` failed 6/6 with `404 rest_no_route` or `500 Internal Server Error` on all three sites (Atto, Test V1, Test V2).

## Root Cause

Two distinct issues:

1. **404 rest_no_route:** Remote sites were running older plugin versions (e.g., v2.11.0, v2.14.0) that did not have the newer endpoints (like `machines/approve`). The local code had v2.17.0+ features.

2. **500 EnvelopeBuilder not found:** During QUpload self-update, PHP autoloader tried to load `QUpload\Helpers\EnvelopeBuilder` from files that were being replaced. The class file was missing/corrupted mid-write, causing a fatal crash. See issue #006 for the fix.

## Resolution

- **404 issue:** Deploy v2.17.0+ first via `.\run.ps1 -uas` (requires initial bootstrap with QUpload's own API: `.\run.ps1 -q`)
- **500 issue:** Fixed `ResponseTrait.php` to use `class_exists()` before referencing `EnvelopeBuilder`, with inline fallback response

## Execution Order for Recovery

```powershell
.\run.ps1 -uas          # Deploy latest plugins (may need -q bootstrap first)
.\run.ps1 -am           # Authorize machine
.\run.ps1 -cla          # Verify log clearing
```

## Prevention

- Always deploy latest plugin versions before using new CLI features
- Preflight check in `-am` now prevents calling endpoints that don't exist yet
