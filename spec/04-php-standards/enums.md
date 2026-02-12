# PHP Enums — Complete Reference

> **Version:** 4.0.0  
> **Updated:** 2026-02-12  
> **Applies to:** WordPress companion plugins (PHP 8.1+)

---

## Overview

All enum-like constants MUST use **PHP 8.1+ native backed enums** with proper namespaces.
The old pattern of `class FooEnum { public const BAR = '...'; }` and `define()` constants
is **deprecated** and must be migrated.

### Architectural Rules

1. **All enums live in `includes/Enums/`** — one file per enum, PascalCase filenames (PSR-4 style).
2. **Namespace:** `RiseupAsia\\Enums` — every enum file declares this namespace.
3. **No `Enum` suffix** in the enum name — use `UploadSource`, not `UploadSourceEnum`.
4. **String-backed** (`enum Foo: string`) for all enums whose values are strings.
5. **Case names use PascalCase** — `case RestApi`, not `case REST_API`.
6. **No `RISEUP_` prefix** on anything — namespace provides scoping.
7. **`define()` constants are prohibited** for values that belong in an enum.
8. **Access pattern:** `UploadSource::Script` (the enum case) or `UploadSource::Script->value` (the raw string).
9. **Validation helpers** go as `static` methods on the enum itself.
10. **Non-enum constants classes** (PathConst, ErrorType) use the same namespace and folder but remain `final class` with `public const` — they hold arrays/maps that can't be enum cases.

### File Loading

Enum files are loaded via `require_once` before the dependency loader:

```php
// In riseup-asia-uploader.php (bootstrap)
require_once __DIR__ . '/includes/Enums/UploadSource.php';
require_once __DIR__ . '/includes/Enums/Capability.php';
require_once __DIR__ . '/includes/Enums/HttpMethod.php';
require_once __DIR__ . '/includes/Enums/Hook.php';
require_once __DIR__ . '/includes/Enums/PathConst.php';
require_once __DIR__ . '/includes/Enums/ErrorType.php';
```

At call sites, use the `use` import:

```php
use RiseupAsia\\Enums\\UploadSource;
use RiseupAsia\\Enums\\Capability;
```

---

## UploadSource — Upload Origin

Identifies how a plugin upload was initiated.

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * Upload source identifiers for transaction logging and request validation.
 */
enum UploadSource: string
{
    case Script  = 'upload_script';
    case RestApi = 'rest_api';
    case AdminUi = 'admin_ui';
    case WpCli   = 'wp_cli';

    /**
     * All valid source values as a flat array.
     *
     * @return string[]
     */
    public static function valid_values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a raw string is a valid upload source.
     *
     * @param string $source Source to validate.
     * @return bool
     */
    public static function is_valid(string $source): bool
    {
        return self::tryFrom($source) !== null;
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\UploadSource;

// ❌ FORBIDDEN: define() constants
define('UPLOAD_SOURCE_SCRIPT', 'upload_script');

// ❌ FORBIDDEN: Class-based fake enum
class UploadSourceEnum {
    public const SCRIPT = 'upload_script';
}

// ✅ REQUIRED: Native backed enum
$source = UploadSource::Script;              // The enum case
$value  = UploadSource::Script->value;       // 'upload_script'
$parsed = UploadSource::tryFrom('rest_api'); // UploadSource::RestApi or null
$isValid = UploadSource::is_valid($input);   // bool

// Validation
if (!UploadSource::is_valid($request['source'])) {
    return new WP_Error('invalid_source', 'Unknown upload source');
}
```

---

## Capability — WordPress Capabilities

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * WordPress capability strings for permission checks.
 *
 * Every current_user_can() call MUST reference a case from this enum.
 */
enum Capability: string
{
    case ManageOptions   = 'manage_options';
    case ActivatePlugins = 'activate_plugins';
    case PublishPosts    = 'publish_posts';
    case UploadFiles     = 'upload_files';
    case EditPosts       = 'edit_posts';
    case DeletePlugins   = 'delete_plugins';
    case InstallPlugins  = 'install_plugins';
    case UpdatePlugins   = 'update_plugins';
    case SwitchThemes    = 'switch_themes';
    case ManageUsers     = 'manage_users';
    case ManageNetwork   = 'manage_network';
}
```

### Usage

```php
use RiseupAsia\\Enums\\Capability;

// ❌ FORBIDDEN
if (current_user_can('manage_options')) { ... }

// ✅ REQUIRED
if (current_user_can(Capability::ManageOptions->value)) { ... }

add_menu_page('Settings', 'Settings', Capability::ManageOptions->value, ...);
```

---

## HttpMethod — REST API Methods

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * HTTP method constants for REST route registration.
 *
 * Every register_rest_route() call MUST use these cases
 * instead of WP_REST_Server constants or string literals.
 */
enum HttpMethod: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Patch  = 'PATCH';
    case Delete = 'DELETE';

    /**
     * Editable methods string for WordPress route registration.
     * WordPress accepts comma-separated methods.
     */
    public static function editable(): string
    {
        return 'PUT, PATCH';
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\HttpMethod;

// ❌ FORBIDDEN
register_rest_route($ns, '/upload', ['methods' => 'POST', ...]);

// ✅ REQUIRED
register_rest_route($ns, '/upload', ['methods' => HttpMethod::Post->value, ...]);
register_rest_route($ns, '/config', ['methods' => HttpMethod::editable(), ...]);
```

---

## Hook — WordPress Hook Names

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * WordPress action and filter hook names.
 *
 * Every add_action() or add_filter() call MUST reference a case from this enum.
 */
enum Hook: string
{
    // ── Core Lifecycle ──────────────────────────────────────────
    case Init           = 'init';
    case PluginsLoaded  = 'plugins_loaded';
    case RestApiInit    = 'rest_api_init';
    case AdminInit      = 'admin_init';
    case Shutdown       = 'shutdown';

    // ── Plugin Lifecycle ────────────────────────────────────────
    case ActivatedPlugin   = 'activated_plugin';
    case DeactivatedPlugin = 'deactivated_plugin';
    case DeletedPlugin     = 'deleted_plugin';

    // ── Admin UI ────────────────────────────────────────────────
    case AdminNotices   = 'admin_notices';
    case AdminEnqueue   = 'admin_enqueue_scripts';
    case AdminMenu      = 'admin_menu';

    // ── Filters ─────────────────────────────────────────────────
    case RestPostDispatch                  = 'rest_post_dispatch';
    case PluginActionLinks                 = 'plugin_action_links';
    case PreSetSiteTransientUpdatePlugins  = 'pre_set_site_transient_update_plugins';
    case PluginsApi                        = 'plugins_api';
    case CronSchedules                     = 'cron_schedules';

    /**
     * Build an authenticated AJAX hook name.
     *
     * @param string $action The AJAX action slug.
     * @return string Full hook name (e.g., 'wp_ajax_riseup_test_connection').
     */
    public static function ajax(string $action): string
    {
        return 'wp_ajax_' . $action;
    }

    /**
     * Build an unauthenticated AJAX hook name.
     *
     * @param string $action The AJAX action slug.
     * @return string Full hook name.
     */
    public static function ajax_nopriv(string $action): string
    {
        return 'wp_ajax_nopriv_' . $action;
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\Hook;

// ❌ FORBIDDEN
add_action('rest_api_init', [$this, 'register_routes']);
add_action('wp_ajax_riseup_test', [$this, 'ajax_test']);

// ✅ REQUIRED
add_action(Hook::RestApiInit->value, [$this, 'register_routes']);
add_action(Hook::ajax('riseup_test'), [$this, 'ajax_test']);
```

---

## PathConst — File Name Constants (Non-Enum Class)

`PathConst` is NOT a backed enum because its values are path fragments composed with
directory methods — they don't form a discrete, finite set of "which one" choices.
It remains a `final class` with `public const`, but under the same namespace.

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * File name constants for all plugin data files.
 *
 * Path accessors in RiseupPathUtils compose: directory method + PathConst::CONSTANT.
 */
final class PathConst
{
    // ── Subdirectories ─────────────────────────────────────────
    public const LOGS_SUBDIR      = '/logs';
    public const TEMP_SUBDIR      = '/temp';
    public const SNAPSHOTS_SUBDIR = '/snapshots';
    public const EXPORTS_SUBDIR   = '/exports';

    // ── Databases ───────────────────────────────────────────────
    public const ROOT_DB     = '/a-root.db';
    public const ACTIVITY_DB = '/activity.db';
    public const SNAPSHOT_DB = '/snapshots.db';
    public const PLUGIN_DB   = '/riseup-asia-uploader.db';

    // ── Log Files ───────────────────────────────────────────────
    public const LOG_FILE        = '/log.txt';
    public const FATAL_ERROR_LOG = '/fatal-errors.log';
    public const STACKTRACE_FILE = '/stacktrace.txt';
    public const ERROR_FILE      = '/error.txt';

    // ── Config Files ────────────────────────────────────────────
    public const DETECTION_FILE = '/wp-plugin-detected.json';
}
```

### Usage

```php
use RiseupAsia\\Enums\\PathConst;

// Always accessed via RiseupPathUtils typed accessors, never directly:
// $path = RiseupPathUtils::get_root_db();  // internally uses PathConst::ROOT_DB
```

---

## ErrorType — PHP Error Type Constants (Non-Enum Class)

`ErrorType` is NOT a backed enum because it holds **arrays** of PHP E_* constants
and a label map. These are groupings, not discrete cases.

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * PHP error type groupings for fatal/warning/recoverable classification.
 *
 * Used by ErrorChecker for centralized error-type inspection.
 */
final class ErrorType
{
    public const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    public const WARNING_TYPES = [
        E_WARNING,
        E_CORE_WARNING,
        E_USER_WARNING,
        E_NOTICE,
        E_USER_NOTICE,
        E_DEPRECATED,
        E_USER_DEPRECATED,
    ];

    public const RECOVERABLE_TYPES = [
        E_RECOVERABLE_ERROR,
        E_STRICT,
    ];

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

---

## Classification: Enum vs Const Class

| Name           | Type         | Why                                                              |
|----------------|--------------|------------------------------------------------------------------|
| `UploadSource` | `enum`       | Discrete set of string-backed choices — "which source?"          |
| `Capability`   | `enum`       | Discrete WordPress capability strings — "which permission?"      |
| `HttpMethod`   | `enum`       | Discrete HTTP verbs — "which method?"                            |
| `Hook`         | `enum`       | Discrete WordPress hook names — "which hook?"                    |
| `PathConst`    | `final class`| Path fragments composed with directories — not a "which one" set |
| `ErrorType`    | `final class`| Arrays of PHP E_* constants and label maps — not single values   |

### Decision Rule

> If the type answers **"which one of these?"** with a single value → `enum`.  
> If it holds **arrays, maps, or composable fragments** → `final class` with `public const`.

---

## ErrorChecker — Uses ErrorType

`ErrorChecker` is a utility class (not an enum). It stays in `includes/` as `class-error-checker.php`
but updates its imports to use `RiseupAsia\\Enums\\ErrorType`.

```php
use RiseupAsia\\Enums\\ErrorType;

class ErrorChecker {

    public static function is_fatal_error(?array $error): bool {
        if ($error === null) {
            return false;
        }

        return in_array($error['type'], ErrorType::FATAL_TYPES, true);
    }

    public static function get_type_label(int $type): string {
        return ErrorType::TYPE_LABELS[$type] ?? 'UNKNOWN_ERROR_TYPE';
    }

    // ... remaining methods unchanged
}
```

---

## Adding New Enum Cases — Checklist

1. **Add the case** to the appropriate enum in `includes/Enums/`.
2. **Add a PHPDoc comment** if the case is non-obvious.
3. **If PathConst:** Add a corresponding typed accessor to `RiseupPathUtils`.
4. **If Hook:** Update all `add_action`/`add_filter` calls that used the string literal.
5. **If Capability:** Update all `current_user_can()` and permission callbacks to use `->value`.
6. **If HttpMethod:** Update all `register_rest_route()` calls.
7. **If ErrorType:** Add to the appropriate group array AND to `TYPE_LABELS`.
8. **Never skip the enum** — even for "one-time" usage.

---

## Cross-References

- [PHP Coding Standards](./README.md) — Parent spec with forbidden patterns
- [Naming Conventions](./naming-conventions.md) — PascalCase for enums, snake_case for methods

---

*PHP Enum specification v4.0.0 — 2026-02-12*
