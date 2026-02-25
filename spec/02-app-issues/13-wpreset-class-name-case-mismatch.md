# Issue: SnapshotProviderWPReset Class Name Case Mismatch

> **ID:** 13-wpreset-class-name-case-mismatch
> **Date:** 2026-02-25
> **Category:** PHP/Naming Convention
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** The class `SnapshotProviderWPReset` used an uppercase abbreviation (`WP`) in its name, but the file was named `SnapshotProviderWpReset.php` with PascalCase (`Wp`). On case-sensitive Linux filesystems, the PSR-4 autoloader maps the class name to the filename — the mismatch would cause a fatal "Class not found" error.
2. **Where it happened:** `includes/Snapshot/SnapshotProviderWpReset.php` (class declaration), `includes/Snapshot/Traits/DetectorSettingsTrait.php` (`use` import and instantiation), `includes/Snapshot/Traits/DetectorProviderTrait.php` (method name `detectWPReset()`).
3. **Symptoms and impact:** On Linux servers (production), autoloading `SnapshotProviderWPReset` would look for `SnapshotProviderWPReset.php` — which doesn't exist. The class would fail to load, breaking the snapshot detection feature. On macOS/Windows (development), the case-insensitive filesystem masks the bug entirely.
4. **How it was discovered:** Systematic audit of all `use` import statements cross-referenced against actual file paths.

## Root Cause Analysis

1. **Direct cause:** The class name used `WP` (all-caps abbreviation) while the file used `Wp` (PascalCase abbreviation). PSR-4 autoloading requires these to match exactly.
2. **Contributing factors:** The PascalCase abbreviation rule (`Wp` not `WP`, `Api` not `API`) was documented in the coding standards but was not applied during the initial creation of this class. Development and testing occurred on case-insensitive filesystems where the mismatch is invisible.
3. **Triggering conditions:** Deploying to any case-sensitive filesystem (Linux, which is the production environment).
4. **Why the existing spec did not prevent it:** The PascalCase abbreviation rule existed but had no enforcement mechanism. No CI check validates that class names match filenames.

## Fix Description

1. **What was changed in the spec:** Reinforced the PascalCase abbreviation rule with an explicit note that class names must match filenames exactly for PSR-4 compatibility.
2. **New rules or constraints added:**
   - All abbreviations in class names, method names, and enum cases must use PascalCase: `Wp` not `WP`, `Api` not `API`, `Http` not `HTTP`, `Url` not `URL`, `Db` not `DB`.
   - The class name must exactly match the filename (minus `.php`) — this is a PSR-4 hard requirement.
3. **Why the fix resolves the root cause:** Renaming the class from `SnapshotProviderWPReset` to `SnapshotProviderWpReset` aligns it with the filename. Renaming `detectWPReset()` to `detectWpReset()` maintains consistency.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None — autoloader diagnostics (`runDiagnostics()`) would catch this via `token_get_all()`.

## Prevention and Non-Regression

1. **Prevention rule:** All abbreviations in PHP identifiers (class, method, enum case) must use PascalCase. Class names must exactly match filenames for PSR-4 compatibility.
2. **Acceptance criteria / test scenarios:**
   - Grep for uppercase abbreviations (`API`, `REST`, `HTTP`, `URL`, `WP`, `DB`, `SQL`, etc.) in class/trait/enum/interface declarations — must return zero results.
   - The autoloader's `runDiagnostics()` method validates all files load without errors.
3. **Guardrails or linting policies:** CI rule: for every PHP file, extract the class/trait/enum name and verify it matches the filename stem exactly.
4. **References to updated spec sections:** `.lovable/memory/coding-standards/php-modernization` (PascalCase abbreviation rule).

## TODO and Follow-Ups

1. —

## Done Checklist

- [x] Spec updated under `../01-app/` (coding standards already documented PascalCase rule)
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable (single-pass fix, no iterations needed)
