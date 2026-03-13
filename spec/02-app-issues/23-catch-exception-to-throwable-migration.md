# Issue: catch(Exception) to catch(Throwable) Migration

> **ID:** 23-catch-exception-to-throwable-migration
> **Date:** 2026-03-13
> **Category:** PHP / Exception Handling
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** Multiple PHP files across both plugins used `catch (Exception $e)` instead of `catch (Throwable $e)`, and two files in `riseup-asia-uploader` used fully qualified `\Throwable` instead of importing via `use Throwable`.
2. **Where it happened:**
   - `riseup-asia-uploader`: `RootDb.php` (`\Throwable` backslash), `AdminFeedbackAjaxTrait.php` (`\Throwable` backslash)
   - `plugins-onboard`: 20 `catch (Exception $e)` instances across `plugins-onboard.php`, `class-database.php`, `class-config.php`, `trait-database-settings.php`, `trait-database-schema.php`, `class-logger.php`, `class-init-helpers.php`, `class-paths.php`
3. **Symptoms and impact:** `catch (Exception $e)` does not catch `Error` subclasses (e.g., `TypeError`, `ParseError`, `DivisionByZeroError`). Fatal errors in catch-guarded code paths would propagate uncaught, crashing the plugin silently. The `\Throwable` backslash violations were cosmetic but violated the project's coding standard requiring imported types.
4. **How it was discovered:** Manual audit against the Throwable-first logging mandate in coding standards.

## Root Cause Analysis

1. **Direct cause:** `plugins-onboard` was written before the Throwable-first standard was established. `riseup-asia-uploader` had two residual `\Throwable` references that were missed during earlier refactors.
2. **Contributing factors:** No automated lint rule enforcing `Throwable` over `Exception` in catch blocks. `plugins-onboard` had not yet been subject to the same coding standards review as `riseup-asia-uploader`.
3. **Triggering conditions:** Any PHP `Error` (not `Exception`) thrown inside a `try` block — e.g., `TypeError` from strict type mismatches, `PDOException` subclasses, or `ParseError` from malformed data.
4. **Why the existing spec did not prevent it:** The Throwable-first rule existed in memory but had not been applied retroactively to `plugins-onboard`. No automated enforcement existed.

## Fix Description

1. **What was changed in the spec:** No spec change needed — the Throwable-first rule already existed. This fix applies it retroactively.
2. **New rules or constraints added:** None — existing rule enforced.
3. **Why the fix resolves the root cause:** `Throwable` is the base interface for both `Exception` and `Error` in PHP 7+. Catching `Throwable` ensures all possible thrown objects are handled.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None beyond existing error logging.

## Prevention and Non-Regression

1. **Prevention rule:** All `catch` blocks must use `Throwable`, never `Exception`. All type references must be imported via `use` — no leading backslash on `Throwable` in catch blocks or type hints.
2. **Acceptance criteria / test scenarios:** `grep -rn 'catch\s*(Exception' wp-plugins/` returns zero results (excluding `ManagerRestoreTrait.php` which is PHP 7.0 constrained). `grep -rn '\\\\Throwable' wp-plugins/` returns zero results.
3. **Guardrails or linting policies:** PHPStan level 6 now configured for both plugins. Future: add a custom PHPStan rule or pre-commit grep check for `catch (Exception`.
4. **References to updated spec sections:** `.lovable/memory/coding-standards/php-exception-handling.md`, `.lovable/memory/architecture/php/coding-standards-semantic-and-safety.md`

## TODO and Follow-Ups

1. Add pre-commit grep check for `catch (Exception` across `wp-plugins/`.

## Done Checklist

- [x] Spec updated under `../01-app/` (existing spec already covered this)
- [x] Issue write-up created under `./`
- [ ] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable (N/A — single-pass fix)
