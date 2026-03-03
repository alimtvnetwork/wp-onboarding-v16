# Issue #001: Missing Stack Traces in Catch Blocks

**Severity:** Critical  
**Discovered:** 2026-03-03  
**Status:** ✅ Complete (All phases)

## Root Cause

Multiple `catch (Throwable $e)` blocks across all WordPress plugins were logging only `$e->getMessage()`, completely discarding the stack trace. This applied to:
1. `error_log()` calls — trace entirely lost
2. `$this->fileLogger->error('msg: ' . $e->getMessage())` — captures call-site backtrace but loses the **exception's original trace**
3. `$this->log(level, msg, ['error' => $e->getMessage()])` — same issue
4. Empty catch blocks — no logging at all

## Phase 1 Fix (14 blocks — error_log + Plugins Onboard)

### QUpload
- `qupload.php` — `qupload_init()` and `qupload_deactivate()` (2)
- `includes/Autoloader.php` — PSR-4 class loading (1)

### Riseup Asia Uploader
- `includes/Autoloader.php` — PSR-4 class loading + file pre-scan (2)

### Plugins Onboard
- `plugins-onboard.php` — activation, settings init, plugin init, cleanup (4)
- `includes/class-database.php` — get_setting, save_setting, get_all_settings (3)
- `includes/class-config.php` — DB read, config set (2)

## Phase 2 Fix (21 blocks — fileLogger/log context + empty catches)

### QUpload
- `Traits/Route/RouteRegistrationTrait.php` — switched `error()` → `logException()` (1)
- `Traits/Upload/UploadExtractTrait.php` — added `'trace'` to error context (1)

### Riseup Asia Uploader
- `riseup-asia-uploader.php` — BootErrorCollector::addError (2)
- `Traits/Core/LifecycleHooksTrait.php` — switched `error()` → `logException()` (1)
- `Traits/Plugin/PluginLifecycleEnableTrait.php` — added `'trace'` to lifecycle log (2)
- `Traits/Plugin/PluginLifecycleDeleteTrait.php` — added `'trace'` to lifecycle log (1)
- `Traits/Error/ErrorSessionHandlerTrait.php` — added `'trace'` to warn context (1)
- `Activation/ActivationHandler.php` — added trace to errorLogWithPrefix (1)
- `Helpers/DependencyLoader.php` — added trace to recordResult (1)
- `Snapshot/Traits/OrchestratorRegistrationTrait.php` — added `'trace'` (1)
- `Snapshot/Traits/WorkerJobLifecycleTrait.php` — added `'trace'` (2)
- `Snapshot/Traits/WorkerProgressTrait.php` — added `'trace'` + fixed empty catch (2)
- `Snapshot/Traits/WorkerTableExportTrait.php` — added log call + `'trace'` (1)
- `Snapshot/Traits/UpdraftCrudTrait.php` — added `'trace'` (1)
- `Snapshot/SnapshotCleaner.php` — added `'trace'` to 3 phases (3)

## Phase 3 Fix (7 blocks — Plugins Onboard audit)

- `includes/class-database.php` — 5 PDOException catches: added `getTraceAsString()` to `error_log()` (5)
- `plugins-onboard.php` — 2 empty catches converted to `error_log()` with traces (2)
- `includes/class-paths.php` — added `error_log()` with trace alongside existing error collection (1)

## Phase 4 Fix (8 blocks — silent catch elimination)

### Riseup Asia Uploader
- `Database/Traits/OrmQueryTrait.php` — `findOne()`, `findMany()`, `count()`: added `error_log()` with traces (3)
- `Snapshot/Traits/ImportValidationTrait.php` — `readRootDbTables()`, `readRootDbIncrementals()`, `readRootDbPlugins()`: added `error_log()` with traces (3)
- `Snapshot/Traits/WorkerJobProgressTrait.php` — `loadTableProgress()`: added `error_log()` with trace (1)
- `Snapshot/Traits/DetectorProviderTrait.php` — SQLite version check: added `error_log()` with trace (1)

## Phase 5 Fix (4 blocks — final silent catch cleanup)

### Riseup Asia Uploader
- `Snapshot/Traits/ImportValidationTrait.php` — `readRootDbMetadata()`: added `'trace'` to `$this->log()` context (1)
- `Admin/Traits/AdminErrorStateTrait.php` — `getUnseenErrorCount()`: added `error_log()` with trace (1)
- `Admin/Traits/AdminErrorStateTrait.php` — `getFlashValue()`: added `error_log()` with trace (1)
- `Traits/Plugin/PluginRouteRegistrationTrait.php` — media endpoint registration: replaced empty comment with `error_log()` with trace (1)

## Phase 6 Fix (16 blocks — silent catches + trace-incomplete context arrays)

### Riseup Asia Uploader — Silent catches converted to `error_log()` with trace (5)
- `Snapshot/Traits/OrchestratorZipTrait.php` — `createZipExport()`: was returning error array with no logging (1)
- `Snapshot/Traits/IncrementalDeltaTrait.php` — `getMaxIdFromMasterSqlite()`: was returning null with no logging (1)
- `Snapshot/Traits/IncrementalExportTrait.php` — `exportTableFull()`: was returning error array with no logging (1)
- `Snapshot/Traits/CleanerHelperTrait.php` — `logCleanupAction()`: replaced `$this->log()` with `error_log()` + trace (1)
- `Snapshot/Traits/RestoreHelperTrait.php` — `logRestoreAudit()`: replaced `$this->log()` with `error_log()` + trace (1)

### Riseup Asia Uploader — Trace-incomplete context arrays: added `'trace' => $e->getTraceAsString()` (11)
- `Snapshot/Traits/IncrementalDeltaTrait.php` — `exportTableDelta()` + `getMaxIdFromTableSqlite()` (2)
- `Snapshot/Traits/IncrementalRegistrationTrait.php` — `registerIncremental()` + `invalidateParentZipExport()` (2)
- `Database/Traits/RootDbRegistrationTrait.php` — `readRootDbContents()` (1)
- `Snapshot/SnapshotImport.php` — `cleanupOnFailure()` (1)
- `Traits/FileSystem/FileSystemPluginTrait.php` — `findPluginFile()` cache clear (1)
- `Database/Traits/DatabaseConvenienceTrait.php` — `queryRows()` (1)
- `Database/Traits/DatabaseMigrationsV1V3Trait.php` — column existence check (1)
- `Database/Traits/DatabaseMigrationsV4V5Trait.php` — column existence check (1)
- `Database/Traits/DatabaseMigrationsV9V11Trait.php` — column existence check (1)

## Remaining Silent Catches (intentional — no fix needed)

- `Logging/Traits/LoggerWriteTrait.php` line 106 — logger recursion guard, correctly silent
- `plugins-onboard/includes/class-logger.php` line 137 — same pattern

## Final Audit Summary

| Plugin | Total Catches | Logging | Silent (intentional) |
|---|---|---|---|
| QUpload | 7 | 7 ✅ | 0 |
| Plugins Onboard | ~20 | 19 ✅ | 1 (logger) |
| Riseup Asia | ~700+ | 700+ ✅ | 1 (logger) |

## Safe Paths (already compliant — no changes needed)

These patterns inherently log stack traces via `logException()`:
- `ErrorResponse::logAndReturn*($logger, $e, ...)` ✅
- `$this->errorResponse(msg, status, $e)` ✅
- `$this->safeExecute(callback, context)` ✅
- `$this->fileLogger->logException($e, context)` ✅

## Prevention

Coding standard `.lovable/memory/coding-standards/php-exception-handling.md` mandates that **every catch block with `$e` must include `$e->getTraceAsString()`**. This is non-negotiable.
