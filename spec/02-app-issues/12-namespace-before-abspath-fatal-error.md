# Issue: Namespace Declaration After ABSPATH Guard Causes Fatal ParseError

> **ID:** 12-namespace-before-abspath-fatal-error
> **Date:** 2026-02-25
> **Category:** PHP/Syntax
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** All 9 PHP files in the `Logging/` directory had the `if (!defined('ABSPATH')) { exit; }` guard placed **before** the `namespace` declaration. PHP requires `namespace` to be the very first statement in a file (only `declare` is permitted before it). This ordering violation causes a compile-time `ParseError`.
2. **Where it happened:** `includes/Logging/` — every file in the module.
   - `Logging/FileLogger.php`
   - `Logging/Logger.php`
   - `Logging/Traits/LoggerActionsTrait.php`
   - `Logging/Traits/LoggerContextTrait.php`
   - `Logging/Traits/LoggerDedupTrait.php`
   - `Logging/Traits/LoggerFormatTrait.php`
   - `Logging/Traits/LoggerLevelMethodsTrait.php`
   - `Logging/Traits/LoggerPathTrait.php`
   - `Logging/Traits/LoggerWriteTrait.php`
3. **Symptoms and impact:** `Fatal error: Namespace declaration statement has to be the very first statement or after any declare call in the script`. Since `FileLogger` is one of the first classes loaded during plugin initialization, this would crash the entire WordPress site on every page load. The autoloader's `catch (Throwable)` would catch the `ParseError`, but `FileLogger` would never load — causing a cascading "Class not found" failure through `Plugin`, `Database`, `Admin`, and every other component.
4. **How it was discovered:** Deep PHP audit scanning all files for potential deployment failures. The incorrect ordering was isolated to the `Logging/` directory; all other 280+ PHP files had the correct `namespace`-before-ABSPATH order.

## Root Cause Analysis

1. **Direct cause:** The ABSPATH guard was placed before the `namespace` declaration in all Logging files, violating PHP's syntax rules.
2. **Contributing factors:** No explicit ordering rule existed in the coding standards spec. The pattern was likely introduced during an early refactor of the Logging module and was never caught because development occurred on macOS/Windows where the error may have manifested differently, or the files were not re-tested after the ordering change.
3. **Triggering conditions:** Any attempt to autoload any class in the `RiseupAsia\Logging` namespace on a PHP 8.2+ server.
4. **Why the existing spec did not prevent it:** The coding standards spec documented ABSPATH guard requirements and namespace conventions separately but never specified their mandatory ordering relative to each other.

## Fix Description

1. **What was changed in the spec:** Added a mandatory statement ordering rule as the first paragraph of `coding-standards-semantic-and-safety.md`: `<?php` → PHPDoc → `namespace` → ABSPATH guard → `use` imports → class/trait body.
2. **New rules or constraints added:**
   - `namespace` **must** appear before `if (!defined('ABSPATH'))` — no exceptions for namespaced files.
   - `Autoloader.php` is the sole exception (no namespace, ABSPATH guard directly after `<?php`).
   - `declare(strict_types=1)` must not be added to individual files unless adopted project-wide (one file had it inconsistently — `ExporterBuildCollectTrait.php` — and it was removed).
3. **Why the fix resolves the root cause:** Swapping the two statements in all 9 files eliminates the `ParseError`. The spec rule prevents regression.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None — this is a compile-time syntax fix.

## Prevention and Non-Regression

1. **Prevention rule:** Every namespaced PHP file must follow strict statement ordering: `<?php` → PHPDoc → `namespace` → ABSPATH guard → `use` imports → class/trait body. Placing any code before `namespace` (except `declare`) is a fatal error.
2. **Acceptance criteria / test scenarios:**
   - Grep for files where `if (!defined('ABSPATH'))` appears on an earlier line number than `namespace` — must return zero results.
   - The autoloader's `runDiagnostics()` method (called on activation) will detect any file with parse errors via `token_get_all()`.
3. **Guardrails or linting policies:** A CI grep rule can enforce this: `find includes/ -name '*.php' -exec grep -l 'ABSPATH' {} \;` cross-referenced against namespace line positions.
4. **References to updated spec sections:** `.lovable/memory/architecture/php/coding-standards-semantic-and-safety.md` (updated 2026-02-25).

## TODO and Follow-Ups

1. —

## Done Checklist

- [x] Spec updated under `../01-app/` (coding standards memory file updated)
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable (single-pass fix, no iterations needed)
