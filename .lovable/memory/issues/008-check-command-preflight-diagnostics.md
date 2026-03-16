# Issue #008: `-check` Command — Preflight Diagnostics

> **Date:** 2026-03-16  
> **Severity:** Enhancement  
> **Status:** ✅ Implemented

---

## Summary

Added `.\run.ps1 -check` command for read-only preflight readiness checks across all sites without performing any actions. Queries `/status` on each plugin for every enabled site.

## Implementation

- **File created:** `wp-plugins/scripts/modules/mode-check.ps1`
- **Wired into:** `run.ps1` (param, dot-source, early exit, help text)

## Features

- Queries both plugin APIs (Riseup Asia Uploader + QUpload) per site
- Reports: plugin version, WP version, response time (ms), readiness status
- Color-coded output: Green (READY), Yellow (OUTDATED), Red (UNREACHABLE)
- Supports `-site`, `-i`, `-xs` for targeted/filtered checks
- Version threshold: v2.17.0+ = READY, below = OUTDATED
- Summary with actionable recommendations
- Exit code: 0 = all ready, 1 = issues found

## Usage

```powershell
.\run.ps1 -check              # Check all sites
.\run.ps1 -check -site "Test V1"  # Check specific site
.\run.ps1 -check -i 1,2       # Check by index
```

## Cross-References

- CLI reference: `.lovable/memory/workflow/powershell-automation/cli-reference.md`
- Preflight in `-am`: `.lovable/memory/features/qupload-plugin.md` (Machine Authorization section)
