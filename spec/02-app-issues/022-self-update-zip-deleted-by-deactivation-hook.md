# Issue #22 — Self-Update ZIP Deleted by Deactivation Hook

> **Created:** 2026-03-27
> **Category:** PHP / Upload Pipeline
> **Severity:** Critical (self-update always fails on all sites)

---

## 1. Issue Summary

When QUpload uploads **itself** (self-update), the upload always fails with:

```
"Failed to open ZIP for extraction"
```

**All 3 sites** failed identically. The same bug exists in Riseup Asia Uploader.

## 2. Root Cause Analysis

The upload pipeline follows this sequence during an update:

1. **Write ZIP** to `wp-content/uploads/qupload/temp/qupload.zip`
2. **Create backup** at `wp-content/uploads/qupload/temp/backup_qupload_...`
3. **Deactivate** the existing plugin via `deactivate_plugins()`
4. **Delete** the old plugin directory (`wp-content/plugins/qupload/`)
5. **Extract** the ZIP to the plugin directory

**The fatal flaw is in step 3.** `deactivate_plugins()` fires WordPress's `register_deactivation_hook`, which calls `handleDeactivate()` → `deleteTempDirectory()`. This **recursively deletes the entire temp directory**, destroying:

- The ZIP file needed for step 5
- The backup needed for rollback

When step 5 tries to open the ZIP file, it no longer exists → `file_exists()` returns false → "Failed to open ZIP for extraction" with no error code details (because the file simply doesn't exist, not a ZIP format error).

**Why only self-update is affected:** When uploading a *different* plugin (e.g., `category-generator`), QUpload is never deactivated, so the deactivation hook never fires and the temp directory survives.

**Why the error message lacked details:** The `handleZipOpenFailure` function is called from the `file_exists === false` path (line 60), which passes default empty parameters — no error code, no error message, no file size — because the file doesn't exist at all.

## 3. Fix Description

Add a static flag `$isUploadInProgress` to the upload pipeline that prevents the deactivation hook from cleaning temp files during a self-update:

1. Set `$isUploadInProgress = true` before calling `deactivateIfUpdating()`
2. Check this flag in `handleDeactivate()` — skip temp cleanup if true
3. Clear the flag after extraction completes (success or failure)

Applied to both QUpload and Riseup Asia Uploader.

## 4. Prevention and Non-Regression

**Prevention rule:** Any deactivation hook that performs filesystem cleanup must check whether the plugin is currently mid-operation (upload, migration, etc.) before destroying shared resources like temp directories.

**Acceptance criteria:**
- `.\run.ps1 -uas` successfully uploads QUpload to all sites (self-update works)
- Riseup Asia Uploader self-update also works
- Normal deactivation (via WP admin or REST API) still cleans temp files
- Rollback still works when extraction fails for other reasons

## 5. Done Checklist

- [x] Root cause identified (deactivation hook deletes temp ZIP)
- [x] Issue write-up created
- [x] QUpload fix applied (upload-in-progress guard)
- [x] Riseup Asia Uploader fix applied (same guard)
- [ ] Verified self-update works on all sites
