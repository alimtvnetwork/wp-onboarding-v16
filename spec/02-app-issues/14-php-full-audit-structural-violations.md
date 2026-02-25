# Issue: PHP Full Audit — Structural Violations

> **ID:** 14-php-full-audit-structural-violations
> **Date:** 2026-02-25
> **Category:** PHP/Audit
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** A full audit of all 290 PHP files uncovered 1 fatal bug, 1 duplicate code bug, 2 ordering convention violations, 4 missing PHPDoc headers, and 2 single-line ABSPATH guards. All have been fixed.
2. **Where it happened:** Multiple files across the codebase.
3. **Symptoms and impact:** The `ErrorResponse.php` bug would cause a fatal `ParseError` identical to issue 12 — crashing any code path that autoloads `ErrorResponse`. Other issues are convention violations that don't cause runtime errors but violate project standards.
4. **How it was discovered:** Systematic audit of all PHP files checking for: (a) ABSPATH-before-namespace ordering, (b) missing/duplicate ABSPATH guards, (c) missing PHPDoc headers, (d) `use`-before-ABSPATH ordering.

## Findings and Fixes

### 🚨 CRITICAL — Fatal ParseError

| File | Issue | Fix |
|------|-------|-----|
| `ErrorHandling/ErrorResponse.php` | ABSPATH guard (line 9) placed **before** `namespace` (line 13) — identical to issue 12 | Swapped to correct order: `namespace` → ABSPATH → `use` |

### 🔴 HIGH — Duplicate Code

| File | Issue | Fix |
|------|-------|-----|
| `Database/DbResult.php` | Duplicate ABSPATH guard (two identical `if (!defined('ABSPATH'))` blocks) | Removed the duplicate |

### 🟡 MEDIUM — Convention Violations

| File | Issue | Fix |
|------|-------|-----|
| `Database/DbExecResult.php` | `use Throwable` placed before ABSPATH guard (should be after) | Reordered to: `namespace` → ABSPATH → `use` |
| `Snapshot/Traits/IncrementalDiscoveryTrait.php` | Missing ABSPATH guard entirely | Added ABSPATH guard after `namespace` |

### 🟠 MEDIUM — Missing PHPDoc Headers

| File | Fix |
|------|-----|
| `Snapshot/SnapshotScheduler.php` | Added full PHPDoc header with `@package` and `@since` |
| `Snapshot/SnapshotWorker.php` | Added full PHPDoc header with `@package` and `@since` |
| `Snapshot/SnapshotOrchestrator.php` | Added full PHPDoc header with `@package` and `@since` |
| `Helpers/ErrorChecker.php` | Added full PHPDoc header with `@package` and `@since` |

### 🔵 LOW — Single-line ABSPATH Guards

| File | Fix |
|------|-----|
| `Snapshot/SnapshotScheduler.php` | Expanded `if (!defined('ABSPATH')) { exit; }` to multi-line |
| `Snapshot/SnapshotWorker.php` | Expanded `if (!defined('ABSPATH')) { exit; }` to multi-line |
| `Snapshot/SnapshotOrchestrator.php` | Expanded `if (!defined('ABSPATH')) { exit; }` to multi-line |

### ⚪ Unused Imports — Not Audited

Checking 2,196 `use` imports across 204 files for unused references requires per-file static analysis (reading each file body and matching symbol usage). This is best handled by a PHP linter (e.g., `phpstan`, `php-cs-fixer`) and was not performed in this audit.

## Root Cause Analysis

1. **Direct cause:** Files were created or refactored without following the mandatory statement ordering convention.
2. **Contributing factors:** No automated CI check enforces PHPDoc headers, ABSPATH guard presence, or statement ordering.
3. **Triggering conditions:** Autoloading any affected class on a PHP 8.2+ server.
4. **Why the existing spec did not prevent it:** The ordering rule was added after issue 12 but these files were missed in the original sweep (ErrorResponse.php was in ErrorHandling/, not Logging/). The PHPDoc and ABSPATH requirements existed but lacked enforcement.

## Prevention and Non-Regression

1. **Prevention rule:** All audit checks from issues 12 and 14 should be automated in CI.
2. **Acceptance criteria / test scenarios:**
   - Zero files with ABSPATH guard before `namespace`.
   - Zero files with `use` statements before ABSPATH guard.
   - Zero namespaced files missing ABSPATH guard (except `Autoloader.php`).
   - Zero files missing `@package` PHPDoc tag.
   - Zero single-line ABSPATH guards.
3. **Guardrails or linting policies:** CI grep rules for all of the above.
4. **References to updated spec sections:** `.lovable/memory/architecture/php/coding-standards-semantic-and-safety.md`, issue 12.

## TODO and Follow-Ups

1. Integrate unused-import detection via `phpstan` or `php-cs-fixer` in CI.

## Done Checklist

- [x] Spec updated under `../01-app/` (existing rules cover all findings)
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable (single-pass audit)
