# Issue #001: Missing Stack Traces in Catch Blocks

**Severity:** Critical  
**Discovered:** 2026-03-03  
**Status:** Fixed (Phase 1 + Phase 2)  

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

## Safe Paths (already compliant — no changes needed)

These patterns inherently log stack traces via `logException()`:
- `ErrorResponse::logAndReturn*($logger, $e, ...)` ✅
- `$this->errorResponse(msg, status, $e)` ✅
- `$this->safeExecute(callback, context)` ✅
- `$this->fileLogger->logException($e, context)` ✅

## Prevention

Coding standard `.lovable/memory/coding-standards/php-exception-handling.md` mandates that **every catch block with `$e` must include `$e->getTraceAsString()`**. This is non-negotiable.
