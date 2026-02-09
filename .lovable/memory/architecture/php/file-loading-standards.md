# Memory: architecture/php/file-loading-standards

> **Updated:** 2026-02-09

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

### Foundation Files (Exception)

Both plugins load 3-4 "foundation" files via raw `require_once` because the loader depends on them:
- `constants.php`
- `class-logger.php` (or `class-boolean-helpers.php`)
- `class-boolean-helpers.php`
- The loader class itself

### Rules

1. Never use raw `require_once` for non-foundation files
2. Missing files must be logged as errors with stack traces
3. Loading must continue for remaining files after a failure
4. Use `getFailures()` to inspect failures programmatically
