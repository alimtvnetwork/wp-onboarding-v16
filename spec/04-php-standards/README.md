# PHP Coding Standards

> **Version:** 2.0.0  
> **Updated:** 2026-02-12  
> **Applies to:** WordPress companion plugins (PHP 7.4+)

---

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Class names | PascalCase | `RiseupEnvelopeBuilder`, `RiseupSnapshotFactory` |
| Method names | camelCase | `buildResponse()`, `getPluginInfo()` |
| Constants | UPPER_SNAKE_CASE | `RISEUP_VERSION`, `RISEUP_REST_NAMESPACE` |
| File names | `class-{kebab-case}.php` | `class-envelope-builder.php` |
| Variables | camelCase | `$pluginSlug`, `$stackTraceFrames` |
| Enum classes | PascalCase with `Enum` suffix | `HookEnum`, `PathEnum`, `ErrorTypeEnum` |

---

## Error Handling — Safe Execution Strategy

### Rule: Catch `Throwable`, not just `Exception`

PHP 7+ introduces `Error` and `TypeError` that are **not** subclasses of `Exception`. All endpoint handlers must catch `Throwable`:

```php
// ❌ FORBIDDEN: Misses PHP 7+ Errors (e.g., missing class)
try {
    $result = $manager->process();
} catch (Exception $e) {
    wp_send_json_error($e->getMessage());
}

// ✅ REQUIRED: Catches all throwables
try {
    $result = $manager->process();
} catch (\Throwable $e) {
    $this->logger->log_exception($e, 'process_failed');
    wp_send_json_error([
        'message'          => $e->getMessage(),
        'stackTrace'       => $e->getTraceAsString(),
        'stackTraceFrames' => $this->formatStackFrames($e),
    ], 500);
}
```

### Safe Execute Wrapper

All REST endpoint handlers must be wrapped in `safe_execute`:

```php
// ✅ Pattern: safe_execute wrapper
public function handle_upload($request) {
    return $this->safe_execute(function() use ($request) {
        // Business logic here
        return $this->envelope->success($result);
    });
}

private function safe_execute(callable $callback) {
    try {
        return $callback();
    } catch (\Throwable $e) {
        $this->logger->log_exception($e, 'endpoint_error');
        return $this->envelope->error($e->getMessage(), 500);
    }
}
```

### Global Shutdown Handler

Register a shutdown handler to catch fatal errors. **Delegate the type-check to a dedicated `ErrorChecker` class** so the logic is reusable and self-documenting:

```php
// ❌ FORBIDDEN: Inline error-type checking
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // ...
    }
});

// ✅ REQUIRED: Use ErrorChecker for readable, centralized fatal-error detection
register_shutdown_function(function() {
    $error = error_get_last();
    if (ErrorChecker::is_fatal_error($error)) {
        // Log to fatal-errors.log with memory usage
        // Send JSON response before process dies
    }
});
```

#### ErrorChecker Implementation

```php
/**
 * Centralized error-type inspection.
 *
 * Encapsulates the raw E_* constant checks so callers never need to
 * remember the specific list. Any new fatal category is added here once.
 */
class ErrorChecker {
    /** Fatal error type constants that terminate PHP execution */
    private const FATAL_ERROR_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
    ];

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
        return in_array($error['type'], self::FATAL_ERROR_TYPES, true);
    }
}
```

---

## Structured Error Responses

### Required Fields

Every error response must include:

```json
{
  "message": "Human-readable error description",
  "stackTrace": "Full trace as string (debug_backtrace with unlimited depth)",
  "stackTraceFrames": [
    {
      "file": "/path/to/file.php",
      "line": 42,
      "function": "methodName",
      "class": "ClassName"
    }
  ]
}
```

### Stack Trace Logging

The logger captures two outputs for every error:

1. **Structured frames** — `stackTraceFrames` array in JSON responses
2. **Raw backtrace** — Written to `stacktrace.txt` with `debug_backtrace(0, 0)` (unlimited depth)

```php
// ✅ Dual logging: structured + raw
public function log_exception(\Throwable $e, string $context = '') {
    // Structured frames for JSON responses
    $frames = $this->formatStackFrames($e);
    
    // Raw backtrace to file (unlimited depth)
    $backtrace = debug_backtrace(0, 0);
    file_put_contents($this->stacktraceFile, $this->formatBacktrace($backtrace), FILE_APPEND);
}
```

---

## Constants & Enums — No Magic Strings

### Rule: All identifiers in `constants.php` or Enum classes

Every endpoint path, action name, capability string, option key, **hook name**, and **file path segment** must be defined centrally. Use PHP `constants.php` for simple values and **Enum classes** for categorized groups.

### Hook Names — HookEnum

```php
// ❌ FORBIDDEN: Magic hook strings
add_action('init', [$this, 'setup']);
add_action('rest_api_init', [$this, 'register_routes']);
add_action('plugins_loaded', [$this, 'on_plugins_loaded']);

// ✅ REQUIRED: Hook names from HookEnum
class HookEnum {
    public const INIT             = 'init';
    public const REST_API_INIT    = 'rest_api_init';
    public const PLUGINS_LOADED   = 'plugins_loaded';
    public const ADMIN_INIT       = 'admin_init';
    public const ADMIN_NOTICES    = 'admin_notices';
    public const SHUTDOWN         = 'shutdown';
    public const WP_AJAX_PREFIX   = 'wp_ajax_';
}

// Usage:
add_action(HookEnum::INIT, [$this, 'setup']);
add_action(HookEnum::REST_API_INIT, [$this, 'register_routes']);
add_action(HookEnum::PLUGINS_LOADED, [$this, 'on_plugins_loaded']);
```

### Action Names — Constants

```php
// ❌ FORBIDDEN: Magic strings
add_action('wp_ajax_my_action', [$this, 'handle']);
$url = rest_url('riseup-asia-uploader/v1/upload');

// ✅ REQUIRED: Centralized constants
// In constants.php:
define('RISEUP_REST_NAMESPACE', 'riseup-asia-uploader/v1');
define('RISEUP_ACTION_UPLOAD', 'upload');

// In handlers:
add_action(HookEnum::WP_AJAX_PREFIX . RISEUP_ACTION_UPLOAD, [$this, 'handle']);
$url = rest_url(RISEUP_REST_NAMESPACE . '/' . RISEUP_ACTION_UPLOAD);
```

---

## Dependency Checks

### Rule: Check before using

Before using external dependencies (PDO, extensions), verify availability:

```php
// ✅ Runtime dependency check
if (!class_exists('PDO') || !extension_loaded('pdo_sqlite')) {
    $this->logger->error('PDO/SQLite not available');
    return $this->envelope->error('SQLite support not available', 500);
}
```

Throttle repeated initialization errors to prevent log bloat.

---

## File Path Resolution

### Rule: Use fully-typed path accessors with PathEnum constants

Never construct file paths with string concatenation or partial accessors. Every path must resolve to a **single typed accessor method** that internally composes the directory with a `PathEnum` constant for the filename segment.

```php
// ❌ FORBIDDEN: Manual path construction
$path = WP_CONTENT_DIR . '/uploads/riseup-asia-uploader/data.db';

// ❌ WRONG: Partial accessor — still has a magic string fragment
$path = RiseupPathUtils::getDataDir() . '/data.db';

// ✅ REQUIRED: Fully-typed accessor that encapsulates the filename
$path = RiseupPathUtils::getRootDb();
```

#### PathEnum Implementation

```php
/**
 * Centralized file-name constants for all data files.
 *
 * Every file that the plugin reads or writes must have an entry here.
 * Path accessors in RiseupPathUtils compose getDataDir() + PathEnum::*.
 */
class PathEnum {
    /** Root SQLite database */
    public const ROOT_DB         = '/a-root.db';

    /** Activity log database */
    public const ACTIVITY_DB     = '/activity.db';

    /** Fatal error log */
    public const FATAL_ERROR_LOG = '/fatal-errors.log';

    /** Stack trace dump */
    public const STACKTRACE_FILE = '/stacktrace.txt';

    /** General log */
    public const LOG_FILE        = '/log.txt';
}
```

#### RiseupPathUtils Accessors

```php
class RiseupPathUtils {
    /**
     * Base data directory — all other paths derive from this.
     */
    public static function getDataDir(): string {
        return WP_CONTENT_DIR . '/uploads/' . RISEUP_PLUGIN_SLUG;
    }

    /** Root SQLite database path */
    public static function getRootDb(): string {
        return self::getDataDir() . PathEnum::ROOT_DB;
    }

    /** Activity log database path */
    public static function getActivityDb(): string {
        return self::getDataDir() . PathEnum::ACTIVITY_DB;
    }

    /** Fatal error log path */
    public static function getFatalErrorLog(): string {
        return self::getDataDir() . PathEnum::FATAL_ERROR_LOG;
    }

    // ... one accessor per file, never expose raw concatenation
}
```

> **Rule:** If a path does not have a typed accessor in `RiseupPathUtils`, create one before using it. Never concatenate `getDataDir()` with a string literal in business logic.

---

## Initialization — No WordPress Calls in Constructors

### Rule: Lazy initialization with HookEnum

Never call WordPress functions (`add_action`, `register_rest_route`, etc.) in class constructors. All hook registrations must use `HookEnum` constants:

```php
// ❌ FORBIDDEN: WordPress call in constructor + magic string
class MyPlugin {
    public function __construct() {
        add_action('init', [$this, 'setup']); // May fail if WP not loaded
    }
}

// ✅ REQUIRED: Lazy initialization with HookEnum
class MyPlugin {
    private $initialized = false;
    
    public function initialize() {
        if ($this->initialized) return;
        $this->initialized = true;
        add_action(HookEnum::INIT, [$this, 'setup']);
    }
}
```

---

## Boolean Logic

### Rule: Use semantic method names — no wrapper helpers

Boolean checks must be self-documenting through **semantic method names** on the object itself. Never use generic boolean helper classes (`isTruthy`, `isFalsy`) — they add indirection without clarity.

```php
// ❌ FORBIDDEN: Generic boolean helper — obscures intent
if (RiseupBooleanHelpers::isFalsy($plugin->is_active())) { ... }
if (RiseupBooleanHelpers::isTruthy($value)) { ... }

// ❌ FORBIDDEN: Raw negation — easy to miss the "!"
if (!$plugin->is_active()) { ... }
if (!!$value) { ... }

// ✅ REQUIRED: Semantic inverse methods on the object
if ($plugin->is_disabled()) { ... }

// ✅ REQUIRED: Descriptive boolean variable names (Is/Has prefix)
if ($is_value) { ... }
if ($has_permission) { ... }
```

### Guidelines

1. **Every `is_*()` method should have a semantic inverse** (e.g., `is_active()` ↔ `is_disabled()`) rather than relying on `!is_active()`.
2. **Boolean variables must use `$is_*` or `$has_*` prefix** — never store a boolean in `$value` or `$result`.
3. **Never create a generic "BooleanHelpers" utility class** — if the boolean check is complex, it belongs as a method on the domain object or a dedicated Checker class (like `ErrorChecker::is_fatal_error()`).

---

## Forbidden Patterns

| Pattern | Why | Alternative |
|---------|-----|-------------|
| `catch (Exception $e)` | Misses PHP 7+ `Error` types | `catch (\Throwable $e)` |
| Magic strings in hooks | Unmaintainable, typo-prone | `HookEnum` constants |
| Magic strings in handlers | Unmaintainable | `constants.php` |
| `wp_die()` in REST handlers | Breaks JSON responses | `wp_send_json_error()` |
| Manual path concatenation | Fragile paths | `RiseupPathUtils` fully-typed accessors |
| `getDataDir() . '/file.db'` | Partial accessor, still magic | Add a typed accessor to `RiseupPathUtils` |
| Constructor WordPress calls | Load order issues | Lazy initialization |
| `error_log()` for diagnostics | No structure | Use `RiseupLogger` |
| Unchecked `new PDO()` | Fatal if extension missing | `class_exists()` check first |
| `RiseupBooleanHelpers` | Obscures intent, adds indirection | Semantic methods (`is_disabled()`) |
| `!$obj->is_active()` | Easy to miss negation | `$obj->is_disabled()` |
| `$error && in_array(...)` inline | Duplicated, hard to read | `ErrorChecker::is_fatal_error()` |
| `$value` for booleans | Ambiguous naming | `$is_value`, `$has_value` |

---

## Cross-References

- [WordPress Plugin Development Spec](../07-wordpress-plugin-development/) — Full 10-document guide
- [Error Handling Spec](../05-error-manage/01-error-handling/) — Cross-language error strategy
- [Generic Enforce Spec](../12-generic-enforce/) — Type safety rules
- [DRY Principles](../01-coding-guidelines/dry-principles.md) — Cross-language DRY rules

---

*PHP standards specification v2.0.0 — 2026-02-12*
