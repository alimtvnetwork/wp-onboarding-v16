# Issue #12: PHP Circular Dependency During Bootstrap

> **Date Fixed:** 2026-02-09  
> **Category:** WordPress/PHP  
> **Severity:** FATAL — Crashes entire WordPress site

---

## Symptom

```
FATAL ERROR: Uncaught Error: Class "RiseupFileLogger" not found in class-path-utils.php:39
```

Repeated on every page load. WordPress site completely down.

## Root Cause

Circular dependency chain during plugin initialization:

```
Riseup_File_Logger::write()
  → Riseup_File_Logger::initialize_paths()
    → RiseupInitHelpers::ensureDir()
      → PathHelper::ensureDir()
        → PathHelper::getLogger()  ← tries to instantiate RiseupFileLogger (not loaded yet!)
```

`PathHelper::getLogger()` attempted to create a `RiseupFileLogger` instance during the logger's own initialization, before the class was fully loaded.

## Fix

1. **`PathHelper::getLogger()`** — Added static `$bootstrapping` guard. When `$bootstrapping` is true, returns `null` instead of instantiating the logger. Uses `error_log()` native fallback.
2. **`PathHelper::safeLog()`** — Wrapped in try-catch for `Throwable`, falls back to `error_log()`.
3. **`Riseup_File_Logger::initialize_paths()`** — Builds log directory path using native string concatenation (`ABSPATH . 'wp-content/uploads/riseup-logs'`) instead of calling `PathHelper::getLogsDir()`.
4. **Removed** early dependency on `BooleanHelpers` inside path utility bootstrapping.

## Prevention Pattern

**Rule:** During class initialization, never call methods on classes that depend on the class being initialized. Use native PHP functions (`error_log()`, `mkdir()`, string concatenation) as fallbacks during bootstrap.

---

*Plugin version: v1.36.1*
