# Memory: architecture/php/file-loading-standards

> **Updated:** 2026-03-12

## Pattern

Both WordPress plugins use structured file loading instead of raw `require_once`. This prevents a single broken file from crashing the entire plugin, and provides diagnostic summaries with stack traces.

### Riseup Asia Uploader: `RiseupDependencyLoader`

- Uses a **manifest array** of `[label, path]` pairs
- Called via `RiseupDependencyLoader::loadManifest(array(...))`
- Summary logged via `logSummary($logger)`

### Plugins Onboard: `OnboardIncludeFiles`

- Uses **enum-like class constants** for every includable file (e.g., `OnboardIncludeFiles::DATABASE`)
- Called via `OnboardIncludeFiles::load(OnboardIncludeFiles::DATABASE)` or `loadMany(array(...))`
- Supports `$isInclude = true` param for `include_once` vs `require_once`
- Missing files are logged with **full stack trace** capture
- Summary logged via `OnboardIncludeFiles::logSummary()`

### QUpload: PSR-4 Autoloader (`QUploadAutoloader`)

- Uses `spl_autoload_register` with namespace-to-directory mapping
- Every loaded class is logged to `autoloader.log` for full audit trail
- **Failed loads re-throw after logging** — a broken class file crashes visibly instead of silently returning, preventing cascading undefined-class errors

### Foundation Files (Exception)

Both plugins load 3-4 "foundation" files via raw `require_once` because the loader depends on them:
- `constants.php`
- `class-logger.php` (or `class-boolean-helpers.php`)
- `class-boolean-helpers.php`
- The loader class itself

### Rules

1. Never use raw `require_once` for non-foundation files
2. Missing files must be logged as errors with stack traces
3. **Boot/load catch blocks must re-throw after logging** — silent failure in file loading is a critical defect (QUpload autoloader, route registration, enum priming)
4. Loading must continue for remaining files after a failure (manifest-based loaders only — autoloaders must re-throw)
5. Use `getFailures()` to inspect failures programmatically
