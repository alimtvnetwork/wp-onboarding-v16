# Issue #006: EnvelopeBuilder Class Not Found During Self-Update

> **Date:** 2026-03-16  
> **Severity:** Critical  
> **Status:** Fixed

---

## Summary

QUpload upload endpoint returned HTTP 500 with `Class "QUpload\Helpers\EnvelopeBuilder" not found` when uploading plugins (including self-updating QUpload).

## Root Cause

During a QUpload self-update via `/upload`, the plugin replaces its own files on disk. When the response is being built after the upload, PHP's autoloader tries to load `EnvelopeBuilder` from the newly-extracted (potentially incomplete or mid-write) files. If the class file is missing or corrupted at that moment, a fatal `Class not found` error occurs.

The error occurred in `ResponseTrait.php:77` inside `errorResponse()` which unconditionally called `EnvelopeBuilder::error()`.

## Solution

Modified `ResponseTrait.php` to implement a **resilient dual-path response builder**:

1. **`buildEnvelopeResponse()`** — wraps `EnvelopeBuilder` usage in `class_exists()` check + `try/catch (Throwable)`
2. **`buildFallbackResponse()`** — inline response envelope construction using only PHP primitives (no class dependencies)
3. **`successResponse()`** — new method for upload handlers to use instead of calling `EnvelopeBuilder` directly

Both success and error paths are protected. If `EnvelopeBuilder` is unavailable, the response still returns a valid JSON envelope matching the expected structure.

## Files Changed

- `wp-plugins/qupload/includes/Traits/Core/ResponseTrait.php` — Added `buildEnvelopeResponse()`, `buildFallbackResponse()`, `successResponse()`
- `wp-plugins/qupload/includes/Traits/Upload/UploadHandlerTrait.php` — Uses `successResponse()` instead of direct `EnvelopeBuilder` calls

## Prevention

- Any response-building code in QUpload must use `class_exists()` before referencing helper classes
- Boot-time and response-time code must never have hard dependencies on classes that could be missing during self-update cycles

## Cross-References

- Issue #006 (boot-time enum crash): `.lovable/memory/issues/06-qupload-boot-fatal-enum-crash` — same class of problem (missing classes during self-update)
- ResponseTrait design: `.lovable/memory/features/qupload-plugin.md`
