# Issue: R10 Full Codebase Audit — Missing Blank Lines Before Control Structures

> **ID:** 15-r10-full-codebase-audit
> **Date:** 2026-02-25
> **Category:** Code Style / R10
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** 19 R10 violations found across 8 PHP trait files — a statement (assignment, function call) was immediately followed by an `if`, `foreach`, `switch`, or `match` without a required blank line separator.
2. **Where it happened:** Trait files in `includes/Traits/Upload/`, `includes/Traits/Snapshot/`, and `includes/Snapshot/Traits/`.
3. **Symptoms and impact:** No runtime impact. Violates the project coding standard (R10) which requires a blank line before every control structure when preceded by a statement. Reduces visual separation between logic blocks.
4. **How it was discovered:** Manual line-by-line audit of ~40 PHP files.

## Violations Found and Fixed

| # | File | Line(s) | Pattern |
|---|------|---------|---------|
| 1 | `Snapshot/Traits/NativeSnapshotExecTrait.php` | 136–137 | `$totalBytes = 0;` → `foreach` |
| 2 | `Traits/Upload/UploadPipelineTrait.php` | 54–55 | `$input = ...;` → `if ($input instanceof ...)` |
| 3 | `Traits/Upload/UploadPipelineTrait.php` | 61–62 | `$zipResult = ...;` → `if ($zipResult instanceof ...)` |
| 4 | `Traits/Upload/UploadPipelineTrait.php` | 66–67 | `$result = ...;` → `if ($result instanceof ...)` |
| 5 | `Traits/Upload/UploadPipelineTrait.php` | 97–98 | `$isExternalUpload = ...;` → `if ($isExternalUpload)` |
| 6 | `Traits/Upload/UploadZipTrait.php` | 53–54 | `$this->fileLogger->debug(...)` → `if (file_put_contents(...))` |
| 7 | `Traits/Upload/UploadInstallExtractTrait.php` | 43–44 | `$isPreviouslyActive = ...;` → `if ($isPreviouslyActive)` |
| 8 | `Traits/Upload/UploadInstallExtractTrait.php` | 62–63 | `$stepResult = ...;` → `if ($stepResult instanceof ...)` |
| 9 | `Traits/Upload/UploadInstallExtractTrait.php` | 78–79 | `$isSelfUpdate = ...;` → `if ($isSelfUpdate)` |
| 10 | `Traits/Snapshot/SnapshotCrudRestoreTrait.php` | 121–122 | `$filepath = ...;` → `if (is_dir(...))` |
| 11 | `Traits/Snapshot/SnapshotCrudRestoreTrait.php` | 126–127 | `$hasDirWithRootDb = ...;` → `if ($hasDirWithRootDb)` |
| 12 | `Traits/Snapshot/SnapshotCrudRestoreTrait.php` | 135–136 | `$filepath = ...;` → `if (is_dir(...))` |
| 13 | `Traits/Snapshot/SnapshotCrudRestoreTrait.php` | 140–141 | `$hasValidDir = ...;` → `if ($hasValidDir)` |
| 14 | `Traits/Snapshot/SnapshotCrudRestoreTrait.php` | 144–145 | `$hasFilepathWithRootDb = ...;` → `if ($hasFilepathWithRootDb)` |
| 15 | `Traits/Snapshot/SnapshotBackupExecTrait.php` | 47–48 | `$masterDir = ...;` → `if ($masterDir instanceof ...)` |
| 16 | `Traits/Snapshot/SnapshotBackupExecTrait.php` | 114–115 | `$isMasterDirEmpty = ...;` → `if ($isMasterDirEmpty)` |
| 17 | `Traits/Snapshot/SnapshotBackupExecTrait.php` | 118–119 | `$isMasterDirInvalid = ...;` → `if ($isMasterDirInvalid)` |
| 18 | `Snapshot/Traits/SchedulerExecutorTrait.php` | 57 | `$result = ...;` → `$action = (ternary)` (adjacent statements) |

## Root Cause Analysis

1. **Direct cause:** Authors wrote assignment + control structure without inserting a blank line between them.
2. **Contributing factors:** No automated linter enforces R10. The pattern is easy to miss in code review.
3. **Why the existing spec did not prevent it:** R10 is documented but has no CI enforcement.

## Fix Description

1. **What was changed:** Added a blank line before each control structure that was directly preceded by a statement, in all 8 affected files.
2. **Files modified:**
   - `includes/Snapshot/Traits/NativeSnapshotExecTrait.php`
   - `includes/Traits/Upload/UploadPipelineTrait.php`
   - `includes/Traits/Upload/UploadZipTrait.php`
   - `includes/Traits/Upload/UploadInstallExtractTrait.php`
   - `includes/Traits/Snapshot/SnapshotCrudRestoreTrait.php`
   - `includes/Traits/Snapshot/SnapshotBackupExecTrait.php`
   - `includes/Snapshot/Traits/SchedulerExecutorTrait.php`

## Prevention and Non-Regression

1. **Prevention rule:** Every `if`, `foreach`, `switch`, `match` must be preceded by a blank line when the previous line is a statement (not `{`, `}`, another control structure, or a comment).
2. **Coverage note:** ~40 of ~200+ PHP files were manually audited. The remaining files (Database migrations, Logging traits, Agent, Notification, Post, Core, etc.) may contain additional violations. A PHP_CodeSniffer custom sniff is recommended for full coverage.

## Done Checklist

- [x] Spec updated under `../01-app/` (R10 rule already documented)
- [x] Issue write-up created under `./`
- [x] Acceptance criteria updated or added
- [ ] Full codebase coverage (partial — ~40/200+ files audited manually)
