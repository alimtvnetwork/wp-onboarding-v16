# Issue: Log Files Lack Size-Based Rotation

> **Severity:** Medium (performance risk on production)
> **Date:** 2026-03-13
> **Status:** Open

## Summary

Both QUpload and Riseup Asia Uploader write logs without rotation. Default threshold: 512 KB. When exceeded, active log rotates to `archive/{NNN}/` folder. Configurable via `settings.json` under `logging.maxLogSizeBytes`.

## Reference

- Full write-up: `spec/02-app-issues/28-log-rotation-both-plugins.md`
