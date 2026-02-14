# PHP Enums — Complete Reference

> **Version:** 6.0.0  
> **Updated:** 2026-02-14  
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

> **Non-enum constant classes** (`ErrorType`) keep their existing names — they are `final class`, not `enum`. The former `PathConst` class has been decomposed into 4 domain-specific enums (see below).

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
10. **Validation helpers** go as `static` methods on the enum itself (camelCase: `validValues()`, `isValid()`).
11. **Non-enum constants classes** (ErrorType) use the same namespace and folder but remain `final class` with `public const`.

### File Loading

Enum files are loaded via `require_once` before the dependency loader:

```php
// In riseup-asia-uploader.php (bootstrap)
require_once __DIR__ . '/includes/Enums/UploadSourceType.php';
require_once __DIR__ . '/includes/Enums/CapabilityType.php';
require_once __DIR__ . '/includes/Enums/HttpMethodType.php';
require_once __DIR__ . '/includes/Enums/HookType.php';
require_once __DIR__ . '/includes/Enums/PathSubdirType.php';
require_once __DIR__ . '/includes/Enums/PathDatabaseType.php';
require_once __DIR__ . '/includes/Enums/PathLogFileType.php';
require_once __DIR__ . '/includes/Enums/PathConfigType.php';
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

    public static function validValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $source): bool
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
$isValid = UploadSourceType::isValid($input);
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

    public static function ajaxNopriv(string $action): string
    {
        return 'wp_ajax_nopriv_' . $action;
    }
}
```

### Usage

```php
use RiseupAsia\\Enums\\HookType;

// ❌ FORBIDDEN
add_action('rest_api_init', [$this, 'registerRoutes']);

// ✅ REQUIRED
add_action(HookType::RestApiInit->value, [$this, 'registerRoutes']);
add_action(HookType::ajax('riseup_test'), [$this, 'ajaxTest']);
```

---

## Path Enums — 4 Domain-Specific Enums (replaces PathConst)

The former `PathConst` final class has been decomposed into 4 backed enums. Each answers "which one?" for its domain, qualifying as a proper enum with the `Type` suffix.

### PathSubdirType — Plugin Subdirectories

```php
<?php

namespace RiseupAsia\Enums;

/**
 * Plugin subdirectory path fragments.
 */
enum PathSubdirType: string
{
    case Logs      = '/logs';
    case Temp      = '/temp';
    case Snapshots = '/snapshots';
    case Exports   = '/exports';
}
```

### PathDatabaseType — SQLite Database Files

```php
<?php

namespace RiseupAsia\Enums;

/**
 * SQLite database file path fragments.
 */
enum PathDatabaseType: string
{
    case Root     = '/a-root.db';
    case Activity = '/activity.db';
    case Snapshot = '/snapshots.db';
    case Plugin   = '/riseup-asia-uploader.db';
}
```

### PathLogFileType — Log File Names

```php
<?php

namespace RiseupAsia\Enums;

/**
 * Log file path fragments.
 */
enum PathLogFileType: string
{
    case Log        = '/log.txt';
    case FatalError = '/fatal-errors.log';
    case Stacktrace = '/stacktrace.txt';
    case Error      = '/error.txt';
}
```

### PathConfigType — Config File Names

```php
<?php

namespace RiseupAsia\Enums;

/**
 * Configuration file path fragments.
 */
enum PathConfigType: string
{
    case Detection = '/wp-plugin-detected.json';
}
```

### Usage in RiseupPathUtils

```php
use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\PathLogFileType;

// ❌ FORBIDDEN: Legacy define() constants
$logsDir = self::join(self::getBaseDir(), LOGS_SUBDIR);
$dbPath  = self::join(self::getBaseDir(), DB_FILENAME);

// ✅ REQUIRED: Enum values
$logsDir = self::join(self::getBaseDir(), PathSubdirType::Logs->value);
$dbPath  = self::join(self::getBaseDir(), PathDatabaseType::Plugin->value);
```

---

## ErrorType — PHP Error Type Constants (Non-Enum Class)

`ErrorType` holds arrays/maps — not a backed enum.

```php
<?php

namespace RiseupAsia\Enums;

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
| `PathSubdirType`   | `enum`       | `Type` | Discrete subdirectories — "which directory?"     |
| `PathDatabaseType` | `enum`       | `Type` | Discrete DB files — "which database?"            |
| `PathLogFileType`  | `enum`       | `Type` | Discrete log files — "which log?"                |
| `PathConfigType`   | `enum`       | `Type` | Discrete config files — "which config?"          |
| `ErrorType`        | `final class`| —      | Arrays of E_* constants and label maps           |

### Decision Rule

> If the type answers **"which one of these?"** with a single value → `enum` with `Type` suffix.  
> If it holds **arrays, maps, or composable fragments** → `final class` with `public const`.

---

## ErrorChecker — Uses ErrorType

```php
use RiseupAsia\Enums\ErrorType;

class ErrorChecker {

    public static function isFatalError(?array $error): bool {
        if ($error === null) {
            return false;
        }

        return in_array($error['type'], ErrorType::FATAL_TYPES, true);
    }

    public static function getTypeLabel(int $type): string {
        return ErrorType::TYPE_LABELS[$type] ?? 'UNKNOWN_ERROR_TYPE';
    }
}
```

---

## Adding New Enum Cases — Checklist

1. **Add the case** to the appropriate enum in `includes/Enums/`.
2. **Add a PHPDoc comment** if the case is non-obvious.
3. **If PathSubdirType:** Add a corresponding typed accessor to `RiseupPathUtils`.
4. **If PathDatabaseType/PathLogFileType/PathConfigType:** Add a typed accessor to `RiseupPathUtils`.
5. **If HookType:** Update all `add_action`/`add_filter` calls.
6. **If CapabilityType:** Update all `current_user_can()` calls.
7. **If HttpMethodType:** Update all `register_rest_route()` calls.
8. **If ErrorType:** Add to the appropriate group array AND to `TYPE_LABELS`.
9. **Never skip the enum** — even for "one-time" usage.

---

## Cross-References

- [PHP Coding Standards](./README.md) — Parent spec with forbidden patterns
- [Naming Conventions](./naming-conventions.md) — PascalCase for enums, camelCase for methods

---

*PHP Enum specification v6.0.0 — 2026-02-14*
