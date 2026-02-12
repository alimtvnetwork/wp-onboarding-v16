# PHP Enum Classes — Complete Reference

> **Version:** 1.0.0  
> **Updated:** 2026-02-12  
> **Applies to:** WordPress companion plugins (PHP 7.4+)

---

## Overview

PHP 7.4 does not support native enums (PHP 8.1+). All "enum" classes use **class constants** to simulate enum behavior. Every magic string in the codebase must trace back to one of these enum classes or to `constants.php`.

### Rules

1. **Never use a string literal** where an Enum constant exists — always reference the constant.
2. **Add new constants** to the enum class before using them anywhere.
3. **Enum class names** use PascalCase with `Enum` suffix: `HookEnum`, `PathEnum`, `ErrorTypeEnum`.
4. **Constants** use `UPPER_SNAKE_CASE`.
5. **Each enum class lives in its own file**: `class-hook-enum.php`, `class-path-enum.php`, etc.
6. **Enum classes are foundation files** — loaded via raw `require_once` before the dependency loader.

---

## HookEnum — WordPress Hook Names

Centralizes all WordPress action and filter hook names. Prevents typos and enables IDE auto-completion.

```php
/**
 * WordPress hook name constants.
 *
 * Every add_action() or add_filter() call MUST reference a constant
 * from this class instead of a string literal.
 *
 * When WordPress adds new hooks your plugin needs, add the constant
 * here FIRST, then use it in the registration call.
 */
class HookEnum {

    // ── Core Lifecycle ──────────────────────────────────────────
    /** Fires after WordPress finishes loading but before headers are sent */
    public const INIT             = 'init';

    /** Fires after all active plugins are loaded */
    public const PLUGINS_LOADED   = 'plugins_loaded';

    /** Fires when the REST API is fully initialized */
    public const REST_API_INIT    = 'rest_api_init';

    /** Fires at the beginning of every admin page */
    public const ADMIN_INIT       = 'admin_init';

    /** Fires just before PHP shuts down */
    public const SHUTDOWN         = 'shutdown';

    // ── Admin UI ────────────────────────────────────────────────
    /** Fires after core admin notices are printed */
    public const ADMIN_NOTICES    = 'admin_notices';

    /** Fires to enqueue admin scripts and styles */
    public const ADMIN_ENQUEUE    = 'admin_enqueue_scripts';

    // ── AJAX ────────────────────────────────────────────────────
    /** Prefix for authenticated AJAX hooks: HookEnum::WP_AJAX_PREFIX . 'my_action' */
    public const WP_AJAX_PREFIX   = 'wp_ajax_';

    /** Prefix for unauthenticated AJAX hooks */
    public const WP_AJAX_NOPRIV_PREFIX = 'wp_ajax_nopriv_';

    // ── Filters ─────────────────────────────────────────────────
    /** Filters the REST API response before sending */
    public const REST_POST_DISPATCH = 'rest_post_dispatch';

    /** Filters the plugin action links on the Plugins page */
    public const PLUGIN_ACTION_LINKS = 'plugin_action_links';
}
```

### Usage Examples

```php
// ❌ FORBIDDEN
add_action('init', [$this, 'setup']);
add_action('rest_api_init', [$this, 'register_routes']);
add_filter('rest_post_dispatch', [$this, 'enrich_error_response'], 10, 3);

// ✅ REQUIRED
add_action(HookEnum::INIT, [$this, 'setup']);
add_action(HookEnum::REST_API_INIT, [$this, 'register_routes']);
add_filter(HookEnum::REST_POST_DISPATCH, [$this, 'enrich_error_response'], 10, 3);
```

---

## PathEnum — File Name Constants

Centralizes all file name segments that are appended to directory paths. The enum holds the **filename portion only** — directory resolution is handled by `RiseupPathUtils`.

### Design Principle

A typed accessor in `RiseupPathUtils` composes: `getDataDir()` + `PathEnum::CONSTANT`. This means:

- The **directory** comes from a method (`getDataDir()`, `getLogsDir()`)
- The **filename** comes from a `PathEnum` constant
- The **full path** is returned by a single typed accessor — callers never see either piece

```php
/**
 * File name constants for all plugin data files.
 *
 * Every file the plugin reads or writes MUST have an entry here.
 * Path accessors in RiseupPathUtils compose: directory method + PathEnum::CONSTANT.
 *
 * WHY: If a filename changes (e.g., 'a-root.db' → 'primary.db'), you update
 * ONE constant here. Every accessor automatically picks up the change.
 */
class PathEnum {

    // ── Databases ───────────────────────────────────────────────
    /** Root SQLite database file */
    public const ROOT_DB         = '/a-root.db';

    /** Activity/audit log database */
    public const ACTIVITY_DB     = '/activity.db';

    /** Snapshot tracking database */
    public const SNAPSHOT_DB     = '/snapshots.db';

    // ── Log Files ───────────────────────────────────────────────
    /** General diagnostic log */
    public const LOG_FILE        = '/log.txt';

    /** Fatal PHP error log (written by shutdown handler) */
    public const FATAL_ERROR_LOG = '/fatal-errors.log';

    /** Raw PHP stack trace dump */
    public const STACKTRACE_FILE = '/stacktrace.txt';

    /** Structured error entries */
    public const ERROR_FILE      = '/error.txt';

    // ── Config Files ────────────────────────────────────────────
    /** Plugin detection marker */
    public const DETECTION_FILE  = '/wp-plugin-detected.json';
}
```

### RiseupPathUtils — Typed Accessors

Each accessor is a **one-liner** that composes a directory method with a `PathEnum` constant. No caller ever constructs a path manually.

```php
/**
 * Fully-typed path accessors.
 *
 * RULE: If a file path does not have an accessor here, create one
 * BEFORE using it. Never concatenate getDataDir() + string literal
 * in business logic.
 */
class RiseupPathUtils {

    /** Base data directory — all other paths derive from this */
    public static function getDataDir(): string {
        return WP_CONTENT_DIR . '/uploads/' . RISEUP_PLUGIN_SLUG;
    }

    /** Logs subdirectory */
    public static function getLogsDir(): string {
        return self::getDataDir() . '/logs';
    }

    // ── Database Paths ──────────────────────────────────────────
    public static function getRootDb(): string {
        return self::getDataDir() . PathEnum::ROOT_DB;
    }

    public static function getActivityDb(): string {
        return self::getDataDir() . PathEnum::ACTIVITY_DB;
    }

    public static function getSnapshotDb(): string {
        return self::getDataDir() . PathEnum::SNAPSHOT_DB;
    }

    // ── Log Paths ───────────────────────────────────────────────
    public static function getLogFile(): string {
        return self::getLogsDir() . PathEnum::LOG_FILE;
    }

    public static function getFatalErrorLog(): string {
        return self::getLogsDir() . PathEnum::FATAL_ERROR_LOG;
    }

    public static function getStacktraceFile(): string {
        return self::getLogsDir() . PathEnum::STACKTRACE_FILE;
    }

    public static function getErrorFile(): string {
        return self::getLogsDir() . PathEnum::ERROR_FILE;
    }

    // ── Config Paths ────────────────────────────────────────────
    public static function getDetectionFile(): string {
        return self::getDataDir() . PathEnum::DETECTION_FILE;
    }
}
```

### Forbidden vs Required

```php
// ❌ FORBIDDEN: Manual path construction
$path = WP_CONTENT_DIR . '/uploads/riseup-asia-uploader/a-root.db';

// ❌ FORBIDDEN: Partial accessor with magic string fragment
$path = RiseupPathUtils::getDataDir() . '/a-root.db';

// ❌ FORBIDDEN: Using PathEnum directly in business logic
$path = RiseupPathUtils::getDataDir() . PathEnum::ROOT_DB;

// ✅ REQUIRED: Single typed accessor — no path fragments visible to caller
$path = RiseupPathUtils::getRootDb();
```

---

## ErrorTypeEnum — PHP Error Type Constants

Centralizes the `E_*` error type constants used for fatal error detection. Used by `ErrorChecker`.

```php
/**
 * PHP error type groupings.
 *
 * Centralizes E_* constant lists so that error-checking logic
 * never needs to remember which error types are "fatal."
 *
 * WHY: If PHP adds a new fatal error type in a future version,
 * you update ONE array here. ErrorChecker automatically picks it up.
 */
class ErrorTypeEnum {

    /**
     * Error types that terminate PHP execution.
     * Used by ErrorChecker::is_fatal_error().
     *
     * E_ERROR         — Fatal run-time error (out of memory, etc.)
     * E_PARSE         — Compile-time parse error (syntax error)
     * E_CORE_ERROR    — Fatal error during PHP startup
     * E_COMPILE_ERROR — Fatal compile-time error (Zend Engine)
     */
    public const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
    ];

    /**
     * Warning-level error types (non-fatal but logged).
     *
     * E_WARNING      — Run-time warning
     * E_CORE_WARNING — Warning during PHP startup
     * E_NOTICE       — Run-time notice
     * E_DEPRECATED   — Deprecation notice
     */
    public const WARNING_TYPES = [
        E_WARNING,
        E_CORE_WARNING,
        E_NOTICE,
        E_DEPRECATED,
    ];
}
```

### ErrorChecker — Uses ErrorTypeEnum

```php
/**
 * Centralized error-type inspection.
 *
 * Encapsulates raw E_* constant checks so callers never need to
 * remember the specific list. Delegates to ErrorTypeEnum for the
 * actual constant groupings.
 *
 * WHY THIS CLASS EXISTS:
 * - Inline `in_array($error['type'], [E_ERROR, ...])` is duplicated
 *   across shutdown handlers, loggers, and health checks.
 * - A single is_fatal_error() call is self-documenting for AI and humans.
 * - Adding a new fatal type requires changing ONE place (ErrorTypeEnum).
 */
class ErrorChecker {

    /**
     * Determine whether the given error array represents a fatal PHP error.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return bool  True when $error is non-null and its type is fatal.
     */
    public static function is_fatal_error(?array $error): bool {
        if ($error === null) {
            return false;
        }
        return in_array($error['type'], ErrorTypeEnum::FATAL_TYPES, true);
    }

    /**
     * Determine whether the given error array is a warning-level error.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return bool
     */
    public static function is_warning(?array $error): bool {
        if ($error === null) {
            return false;
        }
        return in_array($error['type'], ErrorTypeEnum::WARNING_TYPES, true);
    }

    /**
     * Get a human-readable label for the error severity.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return string  'fatal', 'warning', or 'unknown'
     */
    public static function get_severity_label(?array $error): string {
        if (self::is_fatal_error($error)) {
            return 'fatal';
        }
        if (self::is_warning($error)) {
            return 'warning';
        }
        return 'unknown';
    }
}
```

---

## Adding New Enum Constants — Checklist

When you need a new hook name, file path, or error type:

1. **Add the constant** to the appropriate Enum class
2. **Add a PHPDoc comment** explaining what the constant represents
3. **If PathEnum:** Add a corresponding typed accessor to `RiseupPathUtils`
4. **If HookEnum:** Update all `add_action`/`add_filter` calls that used the string literal
5. **Never skip the enum** — even for "one-time" usage

---

## Cross-References

- [PHP Coding Standards](./README.md) — Parent spec with forbidden patterns
- [WordPress Error Handling](../07-wordpress-plugin-development/07-error-handling.md) — ErrorChecker usage in shutdown handlers
- [WordPress Initialization](../07-wordpress-plugin-development/01-initialization-patterns.md) — HookEnum usage in bootstrap
- [WordPress API Design](../07-wordpress-plugin-development/04-api-design.md) — HookEnum for REST_API_INIT

---

*PHP Enum specification v1.0.0 — 2026-02-12*
