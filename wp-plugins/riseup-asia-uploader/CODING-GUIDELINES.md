# Riseup Asia Uploader — Coding Guidelines

**Version:** 1.21.0
**Updated:** 2026-02-07

This document codifies the mandatory development standards for the WordPress companion plugin. All contributors must follow these rules.

---

## 1. Naming Conventions

### PHP Classes
- **PascalCase without underscores** for all new classes.
- Legacy classes (`Riseup_File_Logger`, `Riseup_Database`, etc.) retain underscore style for backward compatibility.

| ❌ Prohibited | ✅ Required |
|---------------|-------------|
| `Riseup_Path_Utils` | `RiseupPathUtils` |
| `Riseup_Snapshot_Provider` | `RiseupSnapshotProvider` |

### Methods
- **camelCase** for all methods: `ensureDir()`, `isSafePath()`, `formatBytes()`.

### Constants
- **SCREAMING_SNAKE_CASE** without `RISEUP_` prefix: `REST_NAMESPACE`, `ACTION_UPLOAD`.

---

## 2. No Magic Strings

All API namespaces, endpoints, action types, status values, table names, and configuration keys must be defined as constants in `includes/constants.php`. Never use raw string literals for these values in class methods.

```php
// ❌ Wrong
$this->log_action('upload', 'success');

// ✅ Correct
$this->log_action(RISEUP_ACTION_UPLOAD, RISEUP_STATUS_SUCCESS);
```

---

## 3. Path Handling

All file path operations must go through `RiseupPathUtils`. Raw `filepath.join()`, string concatenation, or `DIRECTORY_SEPARATOR` usage is prohibited.

### Typed Directory Methods (preferred)
```php
$base   = RiseupPathUtils::getBaseDir();
$logs   = RiseupPathUtils::getLogsDir();
$snaps  = RiseupPathUtils::getSnapshotsDir();
$temp   = RiseupPathUtils::getTempDir();
$db     = RiseupPathUtils::getDbPath();
```

### Path Operations
```php
$path = RiseupPathUtils::join($base, $subdir, $filename);
$dir  = RiseupPathUtils::ensurePath(true, $base, $subdir);
$safe = RiseupPathUtils::isSafePath($path, $base);
```

### Security
Sensitive directories must receive `.htaccess` (`Deny from all`) and `index.php` (silence) files via `RiseupPathUtils::addSecurityFiles()` or `ensureDir($path, true)`.

---

## 4. Boolean Helpers — No Raw Negations

> **Canonical source:** [No Raw Negations spec](../../spec/01-coding-guidelines/no-negatives.md)

**Never use `!` on a function call in a condition.** All boolean logic must use positively named guard functions from `RiseupBooleanHelpers` instead of raw negations.

| ❌ Forbidden (raw negation) | ✅ Required (positive guard) |
|----------------------------|------------------------------|
| `!file_exists($path)` | `RiseupBooleanHelpers::is_file_missing($path)` |
| `!is_dir($path)` | `RiseupBooleanHelpers::is_dir_missing($path)` |
| `!class_exists('X')` | `RiseupBooleanHelpers::is_class_missing('X')` |
| `!function_exists('f')` | `RiseupBooleanHelpers::is_func_missing('f')` |
| `!extension_loaded('e')` | `RiseupBooleanHelpers::is_extension_missing('e')` |
| `!$plugin->is_active()` | `$plugin->is_disabled()` |
| `!$var` (falsy check) | Native `!$var` is OK for simple booleans |

**Note:** Trivial wrappers like `is_falsy()`, `is_truthy()`, `is_null()`, `is_set()`, `is_empty()`, `has_content()` are **deprecated** — use native PHP instead. Only domain-specific guards (file/dir/class/extension checks) are allowed because they encapsulate multi-step logic with safety guards.

---

## 5. Initialization Helpers

Use `RiseupInitHelpers` for idempotent resource setup:

- **Directory creation**: `ensureDir($path, $secure)` — cached per-request to avoid redundant filesystem checks.
- **SQLite connections**: `initSqliteConnection($path, $logger)` — checks PDO/driver availability, enables WAL mode and auto-vacuum.
- **Component startup**: `initComponent($name, $callable)` — wraps init in try/catch with timing, records results for diagnostics.

```php
$db = RiseupInitHelpers::initComponent('Database', function () {
    $db = Riseup_Database::get_instance();
    if (RiseupBooleanHelpers::is_falsy($db->init())) {
        throw new Exception('Database initialization failed');
    }
    return $db;
});

RiseupInitHelpers::logStartupSummary($this->file_logger);
```

---

## 6. Dependency Loading

Use `RiseupDependencyLoader` for structured file loading with error tracking. Foundation files (constants, boolean helpers, init helpers, dependency loader) load raw; all others go through the manifest.

```php
RiseupDependencyLoader::loadManifest(array(
    array('FileLogger',  $includes . '/class-file-logger.php'),
    array('Database',    $includes . '/class-database.php'),
    // ...
));

// Log results in constructor
RiseupDependencyLoader::logSummary($this->file_logger);
```

A broken or missing file is recorded with a full stack trace and reported instead of crashing the entire plugin.

### Rules

- **Never use raw `require_once`** for non-foundation files
- Every file in the manifest gets a human-readable label
- Missing files are logged as errors with stack traces — loading continues for remaining files
- Use `RiseupDependencyLoader::getFailures()` to programmatically inspect failures
- Foundation files (constants, boolean helpers, init helpers, dependency loader itself) are the only files that load via raw `require_once`

---

## 7. Error Handling & Reporting

### Safe Execution
All endpoint handlers must be wrapped in `safe_execute` callbacks that catch `\Throwable` (not just `Exception`) to handle PHP 7+ errors like missing classes.

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
if (RiseupBooleanHelpers::is_class_missing('PDO')) {
    $logger->error('PDO extension not installed');
    return null;
}

if (RiseupBooleanHelpers::is_extension_missing('pdo_sqlite')) {
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
- `constants.php` (`RISEUP_VERSION`)
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

| Class | Purpose |
|-------|---------|
| `RiseupBooleanHelpers` | Semantic boolean checks (replaces raw negations) |
| `RiseupPathUtils` | Path joining, validation, typed dir accessors |
| `RiseupInitHelpers` | Idempotent dir/DB setup, component startup tracking |
| `RiseupDependencyLoader` | Structured file loading with error capture |
