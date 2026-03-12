# Issue: PHP Backed Enum Duplicate Value Fatal Error

> **ID:** 18-php-enum-duplicate-value-fatal
> **Date:** 2026-03-12
> **Category:** PHP / Enum
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** Both the QUpload and Riseup Asia Uploader plugins crashed on load with `Fatal error: Duplicate value in enum PluginConfigType for cases Slug and UploadsSubdir`.
2. **Where it happened:** `QUpload\Enums\PluginConfigType` and `RiseupAsia\Enums\PluginConfigType` — both in `includes/Enums/PluginConfigType.php`.
3. **Symptoms and impact:** Full site crash ("critical error on this website"). Both plugins failed to load, WordPress frontend and admin inaccessible.
4. **How it was discovered:** User reported fatal error stack trace from production server.

## Root Cause Analysis

1. **Direct cause:** PHP string-backed enums forbid two cases sharing the same backing value. `Slug = 'qupload'` and `UploadsSubdir = 'qupload'` had identical values.
2. **Contributing factors:** The `UploadsSubdir` case was added as a semantic alias for `Slug` without realizing PHP enforces value uniqueness on backed enums.
3. **Triggering conditions:** Any PHP version 8.1+ loading the enum class — immediate fatal error at compile time.
4. **Why the existing spec did not prevent it:** No rule existed prohibiting duplicate values in backed enums, and no pre-upload validation checked for this.

## Fix Description

1. **What was changed:** Removed the `UploadsSubdir` enum case entirely. Replaced it with a static method `uploadsSubdir()` that returns `self::Slug->value`, preserving the semantic alias without violating PHP's uniqueness constraint.
2. **New rules or constraints added:** PHP backed enums must never have two cases with the same string/int value. Use static methods for semantic aliases instead.
3. **Why the fix resolves the root cause:** The duplicate value no longer exists in the enum definition. The static method provides the same runtime value without being a separate enum case.
4. **Config changes or defaults affected:** `ApiNamespace` values were also made unique (`'qupload-api'` and `'riseup-asia-api'`) to avoid future collisions.
5. **Logging or diagnostics required:** None — this is a compile-time PHP error that cannot be caught by application-level logging.

## Prevention and Non-Regression

1. **Prevention rule:** Never assign duplicate backing values to PHP string/int-backed enum cases. If two cases need the same runtime value, use a static method alias instead of a separate case.
2. **Acceptance criteria / test scenarios:** Plugin loads without fatal errors; `PluginConfigType::uploadsSubdir()` returns the slug value; all consumers compile and resolve correctly.
3. **Guardrails or linting policies:** Add to pre-upload checklist: verify no duplicate values in backed enums (`grep` for duplicate string values in enum files).
4. **References to updated spec sections:** This file.

## TODO and Follow-Ups

1. — None

## Full Audit (2026-03-12)

Audited all 80 backed enum files across both plugins. **No additional duplicates found.**

### Int-backed enums (7 files):
- `SnapshotConfigType` — ✅ fixed (`WorkerPoolMax` → static method)
- `HttpStatusType` (both plugins) — ✅ all unique HTTP codes
- `HttpConfigType` — ✅ (30, 15)
- `PaginationConfigType` — ✅ fixed (`LogRetrievalMaxLines` → static method)
- `UpdateConfigType` — ✅ (7, 5)
- `BackupConfigType` — ✅ (5, 200)

### String-backed enums (73 files):
- `PluginConfigType` (both plugins) — ✅ fixed (`UploadsSubdir` → static method)
- `ResponseKeyType` (155 cases) — ✅ all unique PascalCase values
- `SettingsKeyType` (24 cases) — ✅ all unique
- `ActionType` (27 cases) — ✅ all unique
- `EndpointType` (44 cases) — ✅ all unique route fragments
- `SelfUpdateStatusType` (17 cases) — ✅ all unique
- All remaining string enums — ✅ no duplicates

## Done Checklist

- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A, single-pass fix
