# Issue: Missing PHPDoc Opening Causes Trait Parse Error and Site Crash

> **ID:** 19-missing-phpdoc-opening-trait-parse-error
> **Date:** 2026-03-12
> **Category:** PHP / Parse Error
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** The Riseup Asia Uploader plugin crashed with `Fatal error: Trait "RiseupAsia\Traits\Plugin\PluginListTrait" not found` on Plugin.php line 54.
2. **Where it happened:** `wp-plugins/riseup-asia-uploader/includes/Traits/Plugin/PluginListTrait.php` line 109 — a PHPDoc comment block was missing its opening `/**`, leaving a bare `     * Collect all installed plugins into a normalized array.` line which is invalid PHP syntax.
3. **Symptoms and impact:** Full site crash — WordPress frontend and admin completely inaccessible. The autoloader's `try-catch` caught the parse `Throwable` silently, so the trait class was never registered. When `Plugin.php` then tried `use PluginListTrait;`, PHP threw "Trait not found" as a fatal error.
4. **How it was discovered:** User reported the fatal error stack trace from production.

## Root Cause Analysis

1. **Direct cause:** Line 109 of `PluginListTrait.php` contained `     * Collect all installed plugins...` without the preceding `/**` line. PHP interprets the `*` as the multiplication operator in an invalid expression context, causing a parse error.
2. **Contributing factors:** The autoloader wraps `require_once` in a `try-catch(Throwable)` and logs the error, but does NOT re-throw. This means PHP parse errors in trait files are silently swallowed, and the trait is simply never available — causing a confusing "Trait not found" error later instead of the actual parse error.
3. **Triggering conditions:** Any request that loads the Plugin class (i.e., every page load).
4. **Why the existing spec did not prevent it:** No pre-deployment PHP syntax validation was run on the trait file. The boot-level fatal handler only catches runtime errors, not compile-time parse errors caught by the autoloader.

## Fix Description

1. **What was changed:** Added the missing `/**` opening line before the PHPDoc block on `collectPluginList()`.
2. **New rules or constraints added:** All PHPDoc blocks must have complete `/** ... */` delimiters. A bare `*` line outside a comment block is always a fatal parse error.
3. **Why the fix resolves the root cause:** The PHPDoc block is now syntactically valid, allowing the trait file to parse and load correctly via the autoloader.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** The autoloader already logs failed class loads. Consider adding the actual parse error message to the "Trait not found" context.

## Prevention and Non-Regression

1. **Prevention rule:** Every PHPDoc block must begin with `/**` and end with `*/`. Before deploying, run `php -l` (lint) on all modified PHP files to catch parse errors.
2. **Acceptance criteria / test scenarios:** Plugin loads without fatal errors; `handleListPlugins()` endpoint returns plugin list successfully.
3. **Guardrails or linting policies:** Add `php -l` syntax check to the ZIP/upload pipeline before deployment.
4. **References to updated spec sections:** This file.

## TODO and Follow-Ups

1. Consider adding `php -l` pre-flight check to `upload-plugin-U-Q.ps1` before uploading.

## Done Checklist

- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A, single-pass fix
