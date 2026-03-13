# Issue: Log Files Lack Rotation — Risk of Unbounded Growth

> **ID:** 28-log-rotation-both-plugins
> **Date:** 2026-03-13
> **Category:** WordPress/PHP/Logging
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** Both the QUpload and Riseup Asia Uploader plugins write log files (`log.txt`, `error.txt`, `stacktrace.txt`) without any size-based rotation, risking unbounded file growth on production servers.
2. **Where it happened:** `wp-plugins/qupload/includes/Logging/FileLogger.php` and `wp-plugins/riseup-asia-uploader/includes/Logging/FileLogger.php`
3. **Symptoms and impact:** On high-traffic sites or during error floods, log files can grow indefinitely, consuming disk space, degrading read performance in the admin log viewer, and potentially causing disk-full failures.
4. **How it was discovered:** Performance review and user request.

## Fix Description

### Log Rotation Strategy

1. **Trigger:** Before each log write, check the current file size.
2. **Default threshold:** 512 KB per log file (configurable via `settings.json`).
3. **Rotation action:** When a log file exceeds the threshold:
   - Create an archive directory: `{log_dir}/archive/{NNN}/` (zero-padded sequential: `001`, `002`, etc.)
   - Move the current log file into the archive directory
   - Start a fresh log file
4. **All three log types rotate independently:** `log.txt`, `error.txt`, `stacktrace.txt`

### Settings Configuration

Add to `settings.json` for both plugins:

```json
{
  "logging": {
    "maxLogSizeBytes": 524288,
    "maxRotations": 10,
    "archiveEnabled": true
  }
}
```

- `maxLogSizeBytes`: Default 524288 (512 KB). Maximum size before rotation.
- `maxRotations`: Default 10. Maximum number of archived rotations to keep. When exceeded, the **oldest** archive folder is deleted before creating a new one.
- `archiveEnabled`: Default `true`. Set to `false` to disable rotation.

### Rotation Pruning

When a new rotation would create archive folder `N` and `N > maxRotations`:
1. List all archive folders sorted numerically
2. Delete the oldest folder(s) until count is `maxRotations - 1`
3. Create the new archive folder

This ensures disk usage is bounded to approximately `maxRotations × maxLogSizeBytes` per log type.

### Archive Directory Structure

```
wp-content/uploads/{plugin-slug}/logs/
├── log.txt           (active)
├── error.txt         (active)
├── stacktrace.txt    (active)
└── archive/
    ├── 001/
    │   ├── log.txt
    │   ├── error.txt
    │   └── stacktrace.txt
    ├── 002/
    │   └── log.txt
    └── ...
```

Each rotation creates a new numbered folder only for the specific file that exceeded the threshold.

## Affected Files

### QUpload
- `wp-plugins/qupload/includes/Logging/FileLogger.php` — add rotation check before write
- `wp-plugins/qupload/settings.json` — add logging config (create if missing)

### Riseup Asia Uploader
- `wp-plugins/riseup-asia-uploader/includes/Logging/FileLogger.php` — add rotation check before write
- `wp-plugins/riseup-asia-uploader/settings.json` — add logging config

## Prevention and Non-Regression

1. **Prevention rule:** All file-based logging MUST implement size-based rotation with configurable thresholds.
2. **Acceptance criteria:**
   - Write >512 KB to a log file → file is rotated to `archive/001/`
   - Write another >512 KB → rotated to `archive/002/`
   - `settings.json` override works (e.g., set to 1 MB)
3. **Guardrails:** FileLogger constructor should validate `maxLogSizeBytes` is between 64 KB and 10 MB.

## TODO and Follow-Ups

1. Implement rotation logic in both FileLogger classes
2. Create/update `settings.json` for both plugins
3. Test rotation with synthetic large log writes
4. Verify archive folder structure is correct
5. Verify admin log viewer still works after rotation (shows active file only)

## Done Checklist

- [ ] Spec updated
- [x] Issue write-up created
- [ ] PHP implementation complete
- [ ] Settings.json updated
- [ ] Tested on dev site
