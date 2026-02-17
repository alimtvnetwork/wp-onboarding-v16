# Riseup Asia Uploader — Coding Guidelines

**Version:** 1.23.0
**Updated:** 2026-02-16

This document codifies the mandatory development standards for the WordPress companion plugin. All contributors must follow these rules.

---

## 1. Naming Conventions

### PHP Classes
- **PascalCase** for all classes within the `RiseupAsia\` namespace.
- The `Riseup` prefix is removed from class names — namespacing handles identity.

| ❌ Prohibited | ✅ Required |
|---------------|-------------|
| `RiseupPathHelper` (global) | `RiseupAsia\Helpers\PathHelper` |
| `RiseupSnapshotManager` (global) | `RiseupAsia\Snapshot\SnapshotManager` |

### Methods
- **camelCase** for all methods: `ensureDir()`, `isSafePath()`, `formatBytes()`.

### Constants
- **PascalCase** enum members: `case Success`, `case ActionUpload`.
- Configuration values live in typed enums under `RiseupAsia\Enums\`.

---

## 2. No Magic Strings

All API namespaces, endpoints, action types, status values, table names, and configuration keys must be defined as typed enum members in `includes/Enums/`. Never use raw string literals for these values in class methods.

```php
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;

// ❌ Wrong
$this->logAction('upload', 'success');

// ✅ Correct
$this->logAction(ActionType::Upload, StatusType::Success);
```

---

## 3. Path Handling

All file path operations must go through `PathHelper`. Raw `filepath.join()`, string concatenation, or `DIRECTORY_SEPARATOR` usage is prohibited.

### Typed Directory Methods (preferred)
```php
use RiseupAsia\Helpers\PathHelper;

$base   = PathHelper::getBaseDir();
$logs   = PathHelper::getLogsDir();
$snaps  = PathHelper::getSnapshotsDir();
$temp   = PathHelper::getTempDir();
$db     = PathHelper::getDbPath();
```

### Path Operations
```php
$path = PathHelper::join($base, $subdir, $filename);
$dir  = PathHelper::ensurePath(true, $base, $subdir);
$safe = PathHelper::isSafePath($path, $base);
```

### Security
Sensitive directories must receive `.htaccess` (`Deny from all`) and `index.php` (silence) files via `PathHelper::addSecurityFiles()` or `ensureDir($path, true)`.

---

## 4. Boolean Helpers — No Raw Negations

> **Canonical source:** [No Raw Negations spec](../01-coding-guidelines/no-negatives.md)

**Never use `!` on a function call in a condition.** All boolean logic must use positively named guard functions instead of raw negations.

### File/Directory Guards — `PathHelper`

```php
use RiseupAsia\Helpers\PathHelper;
```

| ❌ Forbidden (raw negation) | ✅ Required (positive guard) |
|----------------------------|------------------------------|
| `!file_exists($path)` | `PathHelper::isFileMissing($path)` |
| `!is_dir($path)` | `PathHelper::isDirMissing($path)` |

### Non-Path Guards — `BooleanHelpers`

```php
use RiseupAsia\Helpers\BooleanHelpers;
```

| ❌ Forbidden (raw negation) | ✅ Required (positive guard) |
|----------------------------|------------------------------|
| `!class_exists('X')` | `BooleanHelpers::isClassMissing('X')` |
| `!function_exists('f')` | `BooleanHelpers::isFuncMissing('f')` |
| `!extension_loaded('e')` | `BooleanHelpers::isExtensionMissing('e')` |
| `!$plugin->isActive()` | `$plugin->isDisabled()` |
| `!$var` (falsy check) | Native `!$var` is OK for simple booleans |

**Note:** Trivial wrappers like `isFalsy()`, `isTruthy()`, `isNull()`, `isSet()`, `isEmpty()`, `hasContent()` are **deprecated** — use native PHP instead. Only domain-specific guards (file/dir/class/extension checks) are allowed because they encapsulate multi-step logic with safety guards.

---

## 5. Initialization Helpers

Use `InitHelpers` for idempotent resource setup:

- **Directory creation**: `ensureDir($path, $secure)` — cached per-request to avoid redundant filesystem checks.
- **SQLite connections**: `initSqliteConnection($path, $logger)` — checks PDO/driver availability, enables WAL mode and auto-vacuum.
- **Component startup**: `initComponent($name, $callable)` — wraps init in try/catch with timing, records results for diagnostics.

```php
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Database\Database;

$db = InitHelpers::initComponent('Database', function () {
    $db = Database::getInstance();

    return $db->init() ? $db : null;
});

InitHelpers::logStartupSummary($this->fileLogger);
```

---

## 6. Dependency Resolution

All classes are resolved via the PSR-4 autoloader. The entry file (`riseup-asia-uploader.php`) contains a single `require_once` for `Autoloader.php` — no manual class includes or manifest loading.

```php
// PSR-4 AUTOLOADER — the only require_once in the entry file
require_once __DIR__ . '/includes/Autoloader.php';
```

### Rules

- **No `require_once` for classes** — the autoloader resolves all `RiseupAsia\` classes on first use
- **No `DependencyLoader::loadManifest()`** in the entry file or `Plugin` constructor
- Missing classes trigger a "Class not found" fatal error, caught by `FatalErrorHandler`'s registered shutdown function
- `DependencyLoader` remains available as a utility for test harnesses or edge-case scenarios but is **not used** during normal plugin bootstrap

---

## 7. Error Handling & Reporting

### Safe Execution
All endpoint handlers must be wrapped in `safe_execute` callbacks that catch `Throwable` (not just `Exception`) to handle PHP 7+ errors like missing classes.

### Structured Error Responses
Every error response must return HTTP 500 with a JSON body containing:
- `stackTrace` (string) — full trace text
- `stackTraceFrames` (array) — structured frames with `file`, `line`, `function`, `class`

### Fatal Error Handler
A global `register_shutdown_function` intercepts fatal errors, logs memory usage for OOM detection, and ensures a JSON response.

---

## 8. Dependency Checks

Before using external dependencies (PDO, pdo_sqlite, ZipArchive), explicitly check availability:

```php
use RiseupAsia\Helpers\BooleanHelpers;

if (BooleanHelpers::isClassMissing('PDO')) {
    $logger->error('PDO extension not installed');

    return null;
}

if (BooleanHelpers::isExtensionMissing('pdo_sqlite')) {
    $logger->error('PDO SQLite driver not loaded');

    return null;
}
```

Fail gracefully with structured error messages, never with uncaught fatals.

---

## 9. API Design

- **POST only** for all write/lifecycle operations.
- **Fixed, lowercase paths** — no dynamic URL parameters.
- All identifiers (plugin slugs, site IDs) passed in JSON request body.
- **Basic Auth** required on all endpoints (including status/health).
- All endpoints documented via OpenAPI 3.0 specification.

---

## 10. Versioning

Every modification to the plugin must bump the version number in both:
- `VersionType` enum (`VersionType::Current->value`)
- `riseup-asia-uploader.php` (plugin header `Version:`)

---

## 11. Storage Layout

All persistent data lives under `wp-content/uploads/riseup-asia-uploader/`:

```
riseup-asia-uploader/
├── riseup-asia-uploader.db   (SQLite database)
├── logs/
│   ├── log.txt               (general activity)
│   └── error.txt             (error logs)
├── snapshots/                 (backup snapshots)
└── temp/                      (temporary files)
```

Each directory has `.htaccess` and `index.php` security files.

---

## Quick Reference: Utility Classes

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `BooleanHelpers` | `RiseupAsia\Helpers` | Semantic boolean checks (function/class/extension/database guards) |
| `PathHelper` | `RiseupAsia\Helpers` | Path joining, validation, typed dir accessors, file/dir boolean guards |
| `DependencyLoader` | `RiseupAsia\Helpers` | Structured file loading with error capture |
| `InitHelpers` | `RiseupAsia\Helpers` | Idempotent dir/DB setup, component startup tracking |
| `EnvelopeBuilder` | `RiseupAsia\Helpers` | REST API response envelope construction |
| `Plugin` | `RiseupAsia\Core` | Main plugin shell |
| `ActivationHandler` | `RiseupAsia\Activation` | Plugin activation hook handler |
