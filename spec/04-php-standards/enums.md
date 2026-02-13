# PHP Enums — Complete Reference

> **Version:** 5.0.0  
> **Updated:** 2026-02-13  
> **Applies to:** WordPress companion plugins (PHP 8.1+)

---

## Overview

All enum-like constants MUST use **PHP 8.1+ native backed enums** with proper namespaces.
The old pattern of `class FooEnum { public const BAR = '...'; }` and `define()` constants
is **deprecated** and must be migrated.

### Naming Convention: `Type` Suffix

All enums MUST use the **`Type` suffix** in their name. This clearly distinguishes enums from classes and makes the type nature explicit at every usage site.

| ❌ Forbidden Name | ✅ Required Name |
|------------------|-----------------|
| `UploadSource` | `UploadSourceType` |
| `Capability` | `CapabilityType` |
| `HttpMethod` | `HttpMethodType` |
| `Hook` | `HookType` |

> **Non-enum constant classes** (`PathConst`, `ErrorType`) keep their existing names — they are `final class`, not `enum`.

### Architectural Rules

1. **All enums live in `includes/Enums/`** — one file per enum.
2. **File name = Definition name** — e.g., `UploadSourceType.php` → contains `enum UploadSourceType: string`.
3. **Namespace:** `RiseupAsia\\Enums` — every enum file declares this namespace.
4. **`Type` suffix required** — use `UploadSourceType`, not `UploadSource`.
5. **String-backed** (`enum Foo: string`) for all enums whose values are strings.
6. **Case names use PascalCase** — `case RestApi`, not `case REST_API`.
7. **No `RISEUP_` prefix** on anything — namespace provides scoping.
8. **`define()` constants are prohibited** for values that belong in an enum.
9. **Access pattern:** `UploadSourceType::Script` (the enum case) or `UploadSourceType::Script->value` (the raw string).
10. **Validation helpers** go as `static` methods on the enum itself.
11. **Non-enum constants classes** (PathConst, ErrorType) use the same namespace and folder but remain `final class` with `public const`.

### File Loading

Enum files are loaded via `require_once` before the dependency loader:

```php
// In riseup-asia-uploader.php (bootstrap)
require_once __DIR__ . '/includes/Enums/UploadSourceType.php';
require_once __DIR__ . '/includes/Enums/CapabilityType.php';
require_once __DIR__ . '/includes/Enums/HttpMethodType.php';
require_once __DIR__ . '/includes/Enums/HookType.php';
require_once __DIR__ . '/includes/Enums/PathConst.php';
require_once __DIR__ . '/includes/Enums/ErrorType.php';
```

At call sites, use the `use` import:

```php
use RiseupAsia\\Enums\\UploadSourceType;
use RiseupAsia\\Enums\\CapabilityType;
```

---

## UploadSourceType — Upload Origin

Identifies how a plugin upload was initiated.

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * Upload source identifiers for transaction logging.
 */
enum UploadSourceType: string
{
    case Script  = 'upload_script';
    case RestApi = 'rest_api';
    case AdminUi = 'admin_ui';
    case WpCli   = 'wp_cli';

    public static function valid_values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function is_valid(string $source): bool
    {
        return self::tryFrom($source) !== null;
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\UploadSourceType;

// ❌ FORBIDDEN
define('UPLOAD_SOURCE_SCRIPT', 'upload_script');

// ✅ REQUIRED
$source  = UploadSourceType::Script;
$value   = UploadSourceType::Script->value;
$parsed  = UploadSourceType::tryFrom('rest_api');
$isValid = UploadSourceType::is_valid($input);
```

---

## CapabilityType — WordPress Capabilities

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * WordPress capability strings for permission checks.
 */
enum CapabilityType: string
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
use RiseupAsia\\Enums\\CapabilityType;

// ❌ FORBIDDEN
if (current_user_can('manage_options')) { ... }

// ✅ REQUIRED
if (current_user_can(CapabilityType::ManageOptions->value)) { ... }
```

---

## HttpMethodType — REST API Methods

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * HTTP method constants for REST route registration.
 */
enum HttpMethodType: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Patch  = 'PATCH';
    case Delete = 'DELETE';

    public static function editable(): string
    {
        return 'PUT, PATCH';
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\HttpMethodType;

// ❌ FORBIDDEN
register_rest_route($ns, '/upload', ['methods' => 'POST', ...]);

// ✅ REQUIRED
register_rest_route($ns, '/upload', ['methods' => HttpMethodType::Post->value, ...]);
```

---

## HookType — WordPress Hook Names

```php
<?php

namespace RiseupAsia\\Enums;

/**
 * WordPress action and filter hook names.
 */
enum HookType: string
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

    public static function ajax(string $action): string
    {
        return 'wp_ajax_' . $action;
    }

    public static function ajax_nopriv(string $action): string
    {
        return 'wp_ajax_nopriv_' . $action;
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\HookType;

// ❌ FORBIDDEN
add_action('rest_api_init', [$this, 'register_routes']);

// ✅ REQUIRED
add_action(HookType::RestApiInit->value, [$this, 'register_routes']);
add_action(HookType::ajax('riseup_test'), [$this, 'ajax_test']);
```

---

## PathConst — File Name Constants (Non-Enum Class)

`PathConst` is NOT a backed enum — it holds path fragments. Remains `final class`.

```php
<?php

namespace RiseupAsia\\Enums;

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

---

## ErrorType — PHP Error Type Constants (Non-Enum Class)

`ErrorType` holds arrays/maps — not a backed enum.

```php
<?php

namespace RiseupAsia\\Enums;

final class ErrorType
{
    public const FATAL_TYPES = [
        E_ERROR, E_PARSE, E_CORE_ERROR,
        E_COMPILE_ERROR, E_USER_ERROR,
    ];

    public const WARNING_TYPES = [
        E_WARNING, E_CORE_WARNING, E_USER_WARNING,
        E_NOTICE, E_USER_NOTICE,
        E_DEPRECATED, E_USER_DEPRECATED,
    ];

    public const RECOVERABLE_TYPES = [
        E_RECOVERABLE_ERROR, E_STRICT,
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

| Name               | Type         | Suffix | Why                                              |
|--------------------|--------------|--------|--------------------------------------------------|
| `UploadSourceType` | `enum`       | `Type` | Discrete set — "which source?"                   |
| `CapabilityType`   | `enum`       | `Type` | Discrete capabilities — "which permission?"      |
| `HttpMethodType`   | `enum`       | `Type` | Discrete HTTP verbs — "which method?"            |
| `HookType`         | `enum`       | `Type` | Discrete hook names — "which hook?"              |
| `PathConst`        | `final class`| —      | Path fragments, not a "which one" set            |
| `ErrorType`        | `final class`| —      | Arrays of E_* constants and label maps           |

### Decision Rule

> If the type answers **"which one of these?"** with a single value → `enum` with `Type` suffix.  
> If it holds **arrays, maps, or composable fragments** → `final class` with `public const`.

---

## ErrorChecker — Uses ErrorType

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
}
```

---

## Adding New Enum Cases — Checklist

1. **Add the case** to the appropriate enum in `includes/Enums/`.
2. **Add a PHPDoc comment** if the case is non-obvious.
3. **If PathConst:** Add a corresponding typed accessor to `RiseupPathUtils`.
4. **If HookType:** Update all `add_action`/`add_filter` calls.
5. **If CapabilityType:** Update all `current_user_can()` calls.
6. **If HttpMethodType:** Update all `register_rest_route()` calls.
7. **If ErrorType:** Add to the appropriate group array AND to `TYPE_LABELS`.
8. **Never skip the enum** — even for "one-time" usage.

---

## Cross-References

- [PHP Coding Standards](./README.md) — Parent spec with forbidden patterns
- [Naming Conventions](./naming-conventions.md) — PascalCase for enums, snake_case for methods

---

*PHP Enum specification v5.0.0 — 2026-02-13*
