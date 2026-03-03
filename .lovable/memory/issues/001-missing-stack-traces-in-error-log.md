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

### Riseup Asia Uploader — Final sweep: trace-incomplete `$this->log()` and `fileLogger->error()` calls (6)
- `Snapshot/Traits/CleanerStorageTrait.php` — `getStorageStats()` + `estimateCleanup()`: added `'trace'` to context (2)
- `Snapshot/Traits/OrchestratorPluginTrait.php` — `openRootDb()`: added `'trace'` to context (1)
- `Snapshot/Traits/ManagerImportTrait.php` — `importSnapshot()`: added `'trace'` to context (1)
- `Traits/Route/RouteRegistrationTrait.php` — route registration: switched `fileLogger->error()` → `logException($e)` (1)
- `Traits/Plugin/PluginRouteRegistrationTrait.php` — agent route registration: switched `fileLogger->error()` → `logException($e)` (1)

### Final verification sweep (5 blocks)

- `Snapshot/Traits/ManagerSettingsTrait.php` — `readSnapshotSettings()` + `updateSnapshotSettings()`: added `'trace'` to `$this->log()` context (2)
- `Helpers/InitHelpers.php` — SQLite connection failure: added `'trace'` to context array (1)
- `Helpers/Traits/InitStartupTrait.php` — startup init: added missing `error_log()` with trace (1)
- `ErrorHandling/BootErrorCollector.php` — boot error reporter: appended trace to `errorLogWithPrefix()` call (1)

## Phase 7 ✅: `errorLog($e, context)` helper — eliminate raw error_log boilerplate

**Problem:** All catch blocks manually wrote `error_log('msg: ' . $e->getMessage() . "\n" . $e->getTraceAsString())` — verbose, repetitive, error-prone.

**Solution:** Created `errorLog(Throwable $e, string $context)` static helpers in each plugin:
- `RiseupAsia\Helpers\InitHelpers::errorLog($e, 'context:')` — added to existing class
- `QUpload\Helpers\ErrorLogHelper::errorLog($e, 'context:')` — new file
- `OnboardErrorLog::errorLog($e, 'context:')` — new file (`class-error-log.php`)

All internally call `error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString())`.

**Refactored call sites:**
- Riseup Asia: 15 calls across 11 files → `InitHelpers::errorLog($e, msg)`
- Plugins Onboard: 17 calls across 4 files → `OnboardErrorLog::errorLog($e, msg)`
- QUpload: 2 calls in `qupload.php` → `ErrorLogHelper::errorLog($e, msg)`

**Remaining raw `error_log()` (intentional — autoloaders only):**
- `qupload/includes/Autoloader.php` — loaded before autoloader, can't use helpers
- `riseup-asia-uploader/includes/Autoloader.php` — same reason

## Phase 8 ✅: `logError($e, msg)` / `logWarn($e, msg)` — eliminate manual context arrays

**Problem:** Snapshot traits manually injected `'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()` into `$this->log()` context arrays — verbose, repetitive, error-prone.

**Solution:** Created `logError(Throwable $e, string $msg, array $context = [])` and `logWarn()` methods in each log provider trait/class that auto-inject error+trace:
- `OrchestratorHelpersTrait` — `logError()` + `logWarn()` (used by orchestrator/worker traits)
- `CleanerHelperTrait` — `logError()` + `logWarn()` (used by cleaner traits)
- `ManagerCoreTrait` — `logError()` + `logWarn()` (used by manager traits)
- `SnapshotImport` — `logError()` (used by import class)
- `SnapshotScheduler` — switched to `$this->logger->logException($e, msg)`

**Refactored call sites (22 total):**
- `OrchestratorHelpersTrait` — `buildExceptionResult()` (1)
- `IncrementalDeltaTrait` — `exportTableDelta()` + `getMaxIdFromMasterSqlite()` (2)
- `WorkerJobLifecycleTrait` — `createJob()` + `finalizeJob()` (2)
- `WorkerTableExportTrait` — `exportTableToFile()` (1)
- `WorkerProgressTrait` — `initProgressRecords()` + `updateProgress()` (2)
- `IncrementalRegistrationTrait` — `registerIncrementalSnapshot()` + `invalidateParentZipExport()` (2)
- `OrchestratorRegistrationTrait` — `registerSnapshot()` (1)
- `OrchestratorPluginTrait` — `openRootDbForPlugins()` (1)
- `ManagerImportTrait` — `importSnapshot()` (1)
- `ManagerSettingsTrait` — `readSettingsFromDb()` + `updateSettings()` (2)
- `ImportValidationTrait` — `readRootDbMetadata()` (1)
- `SnapshotCleaner` — retention, orphan, stuck phases (3)
- `CleanerStorageTrait` — `getStorageStats()` + `estimateCleanup()` (2)
- `SnapshotImport` — `cleanupOnFailure()` (1)
- `SnapshotScheduler` — `executeWorkerBatch()` (1)

**Remaining manual pattern (intentional — different class hierarchy):**
- `UpdraftCrudTrait` — uses parent class `log()`, only 1 catch block

## Remaining Silent Catches (intentional — no fix needed)

- `Logging/Traits/LoggerWriteTrait.php` line 106 — logger recursion guard, correctly silent
- `plugins-onboard/includes/class-logger.php` line 137 — same pattern

## Final Audit Summary

| Plugin | Total Catches | Logging | Silent (intentional) | Raw error_log (autoloader) |
|---|---|---|---|---|
| QUpload | 7 | 7 ✅ | 0 | 1 |
| Plugins Onboard | ~20 | 19 ✅ | 1 (logger) | 0 |
| Riseup Asia | ~700+ | 700+ ✅ | 1 (logger) | 1 |

## Safe Paths (already compliant — no changes needed)

- `ErrorResponse::logAndReturn*($logger, $e, ...)` ✅
- `$this->errorResponse(msg, status, $e)` ✅
- `$this->safeExecute(callback, context)` ✅
- `$this->fileLogger->logException($e, context)` ✅
- `InitHelpers::errorLog($e, context)` ✅
- `ErrorLogHelper::errorLog($e, context)` ✅
- `OnboardErrorLog::errorLog($e, context)` ✅
- `$this->logError($e, msg)` / `$this->logWarn($e, msg)` ✅

## Prevention

Coding standard `.lovable/memory/coding-standards/php-exception-handling.md` mandates that **every catch block with `$e` must use one of the authorized patterns**. Manual `error_log()` with `getMessage()` + `getTraceAsString()` is prohibited (except autoloaders).
