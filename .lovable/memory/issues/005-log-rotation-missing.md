# Issue: Log Files Lack Size-Based Rotation

> **Severity:** Medium (performance risk on production)  
> **Date:** 2026-03-13  
> **Status:** ✅ Resolved — Already Implemented

## Summary

Both QUpload and Riseup Asia Uploader write logs with built-in rotation. The implementation was already present in `FileLogger.php` before this issue was raised.

## Implementation Details

The `FileLogger.php` in QUpload (`wp-plugins/qupload/includes/Logging/FileLogger.php`) includes:

- **Size-based rotation:** `rotateIfNeeded()` checks file size on every write. When a file exceeds `maxLogSizeBytes` (default 512 KB), it moves to `archive/{NNN}/`
- **Pruning:** `pruneOldArchives()` deletes oldest archive folders when count reaches `maxRotations` (default 10)
- **Configurable:** Via `settings.json` under `logging`:
  - `maxLogSizeBytes` — 64 KB to 10 MB range (default: 512 KB)
  - `maxRotations` — 1 to 100 range (default: 10)
  - `archiveEnabled` — boolean (default: true)
- **All three log files** are rotated: `log.txt`, `error.txt`, `stacktrace.txt`

## Constants

```php
DEFAULT_MAX_LOG_SIZE_BYTES = 524288;  // 512 KB
DEFAULT_MAX_ROTATIONS = 10;
MIN_MAX_LOG_SIZE_BYTES = 65536;       // 64 KB
MAX_MAX_LOG_SIZE_BYTES = 10485760;    // 10 MB
```

## Reference

- Full write-up: `spec/02-app-issues/28-log-rotation-both-plugins.md`
- FileLogger: `wp-plugins/qupload/includes/Logging/FileLogger.php`
