# PHP Enum Classes — Complete Reference

> **Version:** 2.0.0  
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
7. **Plugin-specific custom hooks** (e.g., cron hooks) may use `define()` constants in `constants.php` OR class constants in an enum — but never inline string literals.

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

    // ── Plugin Lifecycle ────────────────────────────────────────
    /** Fires after a plugin is activated */
    public const ACTIVATED_PLUGIN   = 'activated_plugin';

    /** Fires after a plugin is deactivated */
    public const DEACTIVATED_PLUGIN = 'deactivated_plugin';

    /** Fires after a plugin is deleted */
    public const DELETED_PLUGIN     = 'deleted_plugin';

    // ── Admin UI ────────────────────────────────────────────────
    /** Fires after core admin notices are printed */
    public const ADMIN_NOTICES    = 'admin_notices';

    /** Fires to enqueue admin scripts and styles */
    public const ADMIN_ENQUEUE    = 'admin_enqueue_scripts';

    /** Fires to register admin menu pages */
    public const ADMIN_MENU       = 'admin_menu';

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

    /** Filters the update_plugins site transient before it is set */
    public const PRE_SET_SITE_TRANSIENT_UPDATE_PLUGINS = 'pre_set_site_transient_update_plugins';

    /** Filters plugin information for the "View Details" modal */
    public const PLUGINS_API = 'plugins_api';

    /** Filters custom cron schedule intervals */
    public const CRON_SCHEDULES = 'cron_schedules';
}
```

### Usage Examples

```php
// ❌ FORBIDDEN: String literals for hooks
add_action('init', [$this, 'setup']);
add_action('rest_api_init', [$this, 'register_routes']);
add_action('activated_plugin', [$this, 'on_plugin_activated'], 10, 2);
add_action('admin_menu', [$this, 'add_admin_menu']);
add_filter('rest_post_dispatch', [$this, 'enrich_error_response'], 10, 3);
add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_plugin_update']);
add_filter('cron_schedules', [$this, 'registerCronSchedules']);

// ✅ REQUIRED: Enum constants for hooks
add_action(HookEnum::INIT, [$this, 'setup']);
add_action(HookEnum::REST_API_INIT, [$this, 'register_routes']);
add_action(HookEnum::ACTIVATED_PLUGIN, [$this, 'on_plugin_activated'], 10, 2);
add_action(HookEnum::ADMIN_MENU, [$this, 'add_admin_menu']);
add_filter(HookEnum::REST_POST_DISPATCH, [$this, 'enrich_error_response'], 10, 3);
add_filter(HookEnum::PRE_SET_SITE_TRANSIENT_UPDATE_PLUGINS, [$this, 'check_for_plugin_update']);
add_filter(HookEnum::CRON_SCHEDULES, [$this, 'registerCronSchedules']);

// ✅ ACCEPTABLE: Plugin-specific custom hooks from constants.php
// These are defined via define() in constants.php, which is also valid.
add_action(RISEUP_CRON_SNAPSHOT_SCHEDULED, [$this, 'executeScheduledSnapshot']);
add_action(RISEUP_CRON_SNAPSHOT_CLEANUP, [$this, 'executeCleanup']);
```

### AJAX Hook Pattern

```php
// ❌ FORBIDDEN: Magic string
add_action('wp_ajax_riseup_test_connection', [$this, 'ajax_test']);

// ❌ FORBIDDEN: Inline concatenation at call site
add_action(HookEnum::WP_AJAX_PREFIX . 'riseup_test_connection', [$this, 'ajax_test']);

// ✅ REQUIRED: Compose a named constant, then use it
// In constants.php:
define('ACTION_TEST_CONNECTION', 'riseup_test_connection');
define('HOOK_AJAX_TEST_CONNECTION', HookEnum::WP_AJAX_PREFIX . ACTION_TEST_CONNECTION);

// In handlers:
add_action(HOOK_AJAX_TEST_CONNECTION, [$this, 'ajax_test']);
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

    // ── Subdirectories ─────────────────────────────────────────
    /** Logs subdirectory name */
    public const LOGS_SUBDIR     = '/logs';

    /** Temp working directory name */
    public const TEMP_SUBDIR     = '/temp';

    // ── Databases ───────────────────────────────────────────────
    /** Root SQLite database file */
    public const ROOT_DB         = '/a-root.db';

    /** Activity/audit log database */
    public const ACTIVITY_DB     = '/activity.db';

    /** Snapshot tracking database */
    public const SNAPSHOT_DB     = '/snapshots.db';

    /** Main plugin database (riseup-asia-uploader) */
    public const PLUGIN_DB       = '/riseup-asia-uploader.db';

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
        return self::getDataDir() . PathEnum::LOGS_SUBDIR;
    }

    /** Temp working directory */
    public static function getTempDir(): string {
        return self::getDataDir() . PathEnum::TEMP_SUBDIR;
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

    public static function getPluginDb(): string {
        return self::getDataDir() . PathEnum::PLUGIN_DB;
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
     * E_USER_ERROR    — User-triggered fatal error via trigger_error()
     */
    public const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    /**
     * Warning-level error types (non-fatal but logged).
     *
     * E_WARNING        — Run-time warning
     * E_CORE_WARNING   — Warning during PHP startup
     * E_USER_WARNING   — User-triggered warning via trigger_error()
     * E_NOTICE         — Run-time notice
     * E_USER_NOTICE    — User-triggered notice
     * E_DEPRECATED     — Deprecation notice
     * E_USER_DEPRECATED — User-triggered deprecation
     */
    public const WARNING_TYPES = [
        E_WARNING,
        E_CORE_WARNING,
        E_USER_WARNING,
        E_NOTICE,
        E_USER_NOTICE,
        E_DEPRECATED,
        E_USER_DEPRECATED,
    ];

    /**
     * Recoverable error types (can be caught by error handler).
     *
     * E_RECOVERABLE_ERROR — Catchable fatal error
     * E_STRICT            — PHP suggests code changes for interoperability
     */
    public const RECOVERABLE_TYPES = [
        E_RECOVERABLE_ERROR,
        E_STRICT,
    ];

    /**
     * Complete mapping of E_* constants to human-readable labels.
     * Used by ErrorChecker::get_type_label() for log output.
     */
    public const TYPE_LABELS = [
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_WARNING           => 'E_WARNING',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_STRICT            => 'E_STRICT',
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
     * Determine whether the given error is recoverable.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return bool
     */
    public static function is_recoverable(?array $error): bool {
        if ($error === null) {
            return false;
        }
        return in_array($error['type'], ErrorTypeEnum::RECOVERABLE_TYPES, true);
    }

    /**
     * Get a human-readable label for the error severity.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return string  'fatal', 'warning', 'recoverable', or 'unknown'
     */
    public static function get_severity_label(?array $error): string {
        if (self::is_fatal_error($error)) {
            return 'fatal';
        }
        if (self::is_warning($error)) {
            return 'warning';
        }
        if (self::is_recoverable($error)) {
            return 'recoverable';
        }
        return 'unknown';
    }

    /**
     * Get the human-readable E_* constant name for an error type integer.
     * Replaces inline mapping arrays like riseup_error_type_to_string().
     *
     * @param int $type  The E_* error type constant value.
     * @return string    e.g., 'E_ERROR', 'E_WARNING', or 'UNKNOWN_ERROR_TYPE'
     */
    public static function get_type_label(int $type): string {
        return ErrorTypeEnum::TYPE_LABELS[$type] ?? 'UNKNOWN_ERROR_TYPE';
    }

    // ── Runtime Dependency Checks ───────────────────────────────

    /**
     * Check whether the PDO/SQLite extension is unavailable.
     * Replaces inline `!class_exists('PDO') || !extension_loaded('pdo_sqlite')`.
     *
     * @return bool  True when PDO or pdo_sqlite is missing.
     */
    public static function is_invalid_pdo_extension(): bool {
        return !class_exists('PDO') || !extension_loaded('pdo_sqlite');
    }
}
```

### Forbidden vs Required

```php
// ❌ FORBIDDEN: Inline fatal type arrays
$fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
if (!in_array($error['type'], $fatal_types)) {
    return;
}

// ✅ REQUIRED: Centralized check
if (!ErrorChecker::is_fatal_error($error)) {
    return;
}

// ❌ FORBIDDEN: Inline type-to-string mapping
function riseup_error_type_to_string($type) {
    $types = array(E_ERROR => 'E_ERROR', E_PARSE => 'E_PARSE', ...);

    return isset($types[$type]) ? $types[$type] : 'UNKNOWN_ERROR_TYPE';
}

// ✅ REQUIRED: Centralized label lookup
$label = ErrorChecker::get_type_label($error['type']);
```

---

## CapabilityEnum — WordPress Capability Strings

Centralizes WordPress capability strings used in `current_user_can()`, `register_rest_route()` permission callbacks, and `add_menu_page()`.

```php
/**
 * WordPress capability string constants.
 *
 * Every current_user_can() or permission_callback check MUST
 * reference a constant from this class instead of a string literal.
 */
class CapabilityEnum {

    // ── Admin Capabilities ──────────────────────────────────────
    /** Full administrator access */
    public const MANAGE_OPTIONS     = 'manage_options';

    /** Can install/activate/update/delete plugins */
    public const ACTIVATE_PLUGINS   = 'activate_plugins';

    /** Can install/activate/switch themes */
    public const SWITCH_THEMES      = 'switch_themes';

    /** Can manage other users */
    public const MANAGE_USERS       = 'manage_users' ;

    // ── Content Capabilities ────────────────────────────────────
    /** Can edit own posts */
    public const EDIT_POSTS         = 'edit_posts';

    /** Can publish posts */
    public const PUBLISH_POSTS      = 'publish_posts';

    /** Can upload files */
    public const UPLOAD_FILES       = 'upload_files';

    // ── Network / Multisite ─────────────────────────────────────
    /** Super admin on multisite */
    public const MANAGE_NETWORK     = 'manage_network';
}
```

### Usage Examples

```php
// ❌ FORBIDDEN: Magic capability strings
if (current_user_can('manage_options')) { ... }

add_menu_page('Settings', 'Settings', 'manage_options', ...);

register_rest_route(REST_NAMESPACE, '/config', [
    'permission_callback' => function() {
        return current_user_can('manage_options');
    },
]);

// ✅ REQUIRED: CapabilityEnum constants
if (current_user_can(CapabilityEnum::MANAGE_OPTIONS)) { ... }

add_menu_page('Settings', 'Settings', CapabilityEnum::MANAGE_OPTIONS, ...);

register_rest_route(REST_NAMESPACE, '/config', [
    'permission_callback' => function() {
        return current_user_can(CapabilityEnum::MANAGE_OPTIONS);
    },
]);
```

---

## HttpMethodEnum — REST Method Constants

Centralizes HTTP method strings used in `register_rest_route()` and request handling.

```php
/**
 * HTTP method constants for REST route registration.
 *
 * Every register_rest_route() call MUST use these constants
 * instead of WP_REST_Server constants or string literals.
 */
class HttpMethodEnum {

    /** Safe, idempotent read */
    public const GET     = 'GET';

    /** Create a new resource */
    public const POST    = 'POST';

    /** Full replacement of a resource */
    public const PUT     = 'PUT';

    /** Partial update of a resource */
    public const PATCH   = 'PATCH';

    /** Remove a resource */
    public const DELETE  = 'DELETE';

    // ── Composite Methods ───────────────────────────────────────
    /** Read-only routes (alias for GET) */
    public const READABLE  = 'GET';

    /** Write routes (alias for POST) */
    public const CREATABLE = 'POST';

    /** Update routes (PUT + PATCH) */
    public const EDITABLE  = 'PUT, PATCH';

    /** Delete routes (alias for DELETE) */
    public const DELETABLE = 'DELETE';
}
```

### Usage Examples

```php
// ❌ FORBIDDEN: Magic method strings or WP_REST_Server constants
register_rest_route(REST_NAMESPACE, '/upload', [
    'methods'  => 'POST',
    'callback' => [$this, 'handle_upload'],
]);

register_rest_route(REST_NAMESPACE, '/plugins', [
    'methods'  => WP_REST_Server::READABLE,
    'callback' => [$this, 'get_plugins'],
]);

// ✅ REQUIRED: HttpMethodEnum constants
register_rest_route(REST_NAMESPACE, '/upload', [
    'methods'  => HttpMethodEnum::POST,
    'callback' => [$this, 'handle_upload'],
]);

register_rest_route(REST_NAMESPACE, '/plugins', [
    'methods'  => HttpMethodEnum::GET,
    'callback' => [$this, 'get_plugins'],
]);

register_rest_route(REST_NAMESPACE, '/config', [
    'methods'  => HttpMethodEnum::EDITABLE,
    'callback' => [$this, 'update_config'],
]);
```

---

## Adding New Enum Constants — Checklist

When you need a new hook name, file path, capability, HTTP method, or error type:

1. **Add the constant** to the appropriate Enum class
2. **Add a PHPDoc comment** explaining what the constant represents
3. **If PathEnum:** Add a corresponding typed accessor to `RiseupPathUtils`
4. **If HookEnum:** Update all `add_action`/`add_filter` calls that used the string literal
5. **If CapabilityEnum:** Update all `current_user_can()` and permission callbacks
6. **If HttpMethodEnum:** Update all `register_rest_route()` calls
7. **If ErrorTypeEnum:** Add to the appropriate group array AND to `TYPE_LABELS`
8. **Never skip the enum** — even for "one-time" usage

---

## Cross-References

- [PHP Coding Standards](./README.md) — Parent spec with forbidden patterns
- [WordPress Error Handling](../07-wordpress-plugin-development/07-error-handling.md) — ErrorChecker usage in shutdown handlers
- [WordPress Initialization](../07-wordpress-plugin-development/01-initialization-patterns.md) — HookEnum usage in bootstrap
- [WordPress API Design](../07-wordpress-plugin-development/04-api-design.md) — HookEnum for REST_API_INIT

---

*PHP Enum specification v3.0.0 — 2026-02-12*
