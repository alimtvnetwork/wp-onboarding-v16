# Memory: coding-standards/php-exception-handling
Updated: 2026-03-12

## Critical Rule: Throwable-First Error Logging

Every error logging call **MUST** accept the `Throwable` object as the primary input — not a string message. The message is secondary; the **stack trace is the most important part**.

## Critical Rule: Re-Throw After Log in Boot/Load Contexts

In **boot, autoloader, file-loading, and route registration** catch blocks, the exception **MUST be re-thrown** after logging. These are infrastructure-level operations where silent failure causes cascading breakage that is impossible to diagnose. The only place exceptions are caught without re-throwing is at **handler boundaries** (`safeExecute`, `performActivation`) where a structured error response must be returned to the client.

### Re-Throw Required (boot/load contexts):
```php
// Autoloader
} catch (Throwable $e) {
    error_log($message);
    self::writeDiagnostic($message);
    throw $e; // MUST re-throw — broken class = broken plugin
}

// Route registration
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Failed to register route');
    throw $e; // MUST re-throw — broken route = silent 404
}

// Enum/file priming (require_once)
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Failed to preload dependency');
    throw $e; // MUST re-throw — missing dependency = runtime crash
}
```

### Re-Throw Prohibited (handler boundaries):
```php
// safeExecute — top-level REST handler boundary
} catch (Throwable $e) {
    error_log(...); // emit to PHP debug log
    $this->fileLogger->logException($e, ...);
    return $this->errorResponse(..., $e); // return structured envelope
}

// Auth permission callbacks — must return WP_Error, not throw
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Authentication error');
    return new WP_Error(...);
}
```

## Critical Rule: PHP error_log Emission

All `safeExecute` and `errorResponse` catch blocks **MUST emit to PHP's native `error_log()`** with the full message + trace string so errors surface in `wp-content/debug.log` and server `php-error.log`. This is in addition to the FileLogger output.

```php
@error_log(sprintf(
    '[QUpload] %s: %s in %s:%d%s%s',
    $context,
    $e->getMessage(),
    $e->getFile(),
    $e->getLine(),
    PHP_EOL,
    $e->getTraceAsString(),
));
```

## Mandatory Patterns (by context)

### Pattern 1: FileLogger — `logException()`

When `$this->fileLogger` is available (Plugin classes, managers, services):

```php
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Context message');
}
```

`logException()` internally extracts `$e->getMessage()`, `$e->getTraceAsString()`, file, and line. One call does everything.

### Pattern 2: ErrorLog helper — `log($e, context)` or `errorLog($e, context)`

When FileLogger is not available but namespaced helpers are loaded. Method is named `log()` when the class name already implies error logging, `errorLog()` when it doesn't:

```php
// Riseup Asia (InitHelpers is a general helper — keep "errorLog")
} catch (Throwable $e) {
    InitHelpers::errorLog($e, 'ClassName::method() failed:');
}

// QUpload (ErrorLogHelper — class name implies error logging)
} catch (Throwable $e) {
    ErrorLogHelper::log($e, 'ClassName::method() failed:');
}

// Plugins Onboard (OnboardErrorLog — class name implies error logging)
} catch (Exception $e) {
    OnboardErrorLog::log($e, 'Context message:');
}
```

Internally calls `error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString())`.

### Pattern 3: Autoloaders — raw `error_log()`

**Only** in autoloader files (loaded before any helper class exists):

```php
} catch (Throwable $e) {
    error_log('Prefix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e; // MUST re-throw after logging
}
```

This is the **only** context where manual `getMessage()` + `getTraceAsString()` concatenation is acceptable.

### Pattern 4: Snapshot trait logError/logWarn

When inside Snapshot traits/classes that have a `log()` method (via `OrchestratorHelpersTrait`, `CleanerHelperTrait`, `ManagerCoreTrait`, or `SnapshotImport`):

```php
} catch (Throwable $e) {
    $this->logError($e, 'Context message');
    // or with extra context:
    $this->logWarn($e, 'Context message', array('table' => $tableName));
}
```

`logError()`/`logWarn()` auto-inject `'error' => $e->getMessage()` and `'trace' => $e->getTraceAsString()` into the context array before delegating to `$this->log()`. Never manually build these context arrays.

### Pattern 5: safeExecute / errorResponse (Handler Boundaries)

Top-level REST handler catch blocks use `safeExecute()` which:
1. Emits to PHP `error_log()` with full trace (visible in PHP debug)
2. Logs via `fileLogger->logException()` (visible in plugin logs)
3. Logs detailed context via `fileLogger->error()` (exception class, file, line)
4. Returns a structured error envelope via `errorResponse()`

`errorResponse()` additionally calls `logErrorWithBacktrace()` which captures a 15-frame `debug_backtrace()` when no Throwable is available, ensuring non-exception error paths also have full call-site visibility.

No action needed at the call site — just wrap in `safeExecute()`:
```php
return $this->safeExecute(
    fn () => $this->executePipeline($request),
    'handleUpload',
    ['endpoint' => 'upload'],
);
```

## Rules

1. **Throwable is the primary input** — every error logging method must accept `Throwable` as its first parameter
2. **Stack trace is the most important output** — must always be logged, never omitted
3. **Never** manually write `error_log('msg: ' . $e->getMessage() . "\n" . $e->getTraceAsString())` — use `errorLog($e, 'msg:')` instead (except autoloaders)
4. **Never** log `$e->getMessage()` alone without the trace
5. **Always re-throw** in boot/load/infrastructure catch blocks after logging
6. **Never re-throw** in handler boundary catch blocks (return error envelope instead)
7. **Always emit to PHP error_log()** in handler boundaries so errors surface in PHP debug
8. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
9. No exceptions to this rule — even in bootstrap, deactivation hooks
10. Only permitted raw `error_log()` with exception: autoloader files (2 total)
11. Only permitted silent catch: logger recursion guards (to prevent infinite loops)
12. Reducing or omitting stack traces is treated as a critical defect

## Helper Locations

| Plugin | Helper | Method |
|---|---|---|
| Riseup Asia | `RiseupAsia\Helpers\InitHelpers` | `::errorLog($e, $context)` |
| QUpload | `QUpload\Helpers\ErrorLogHelper` | `::log($e, $context)` |
| Plugins Onboard | `OnboardErrorLog` (global) | `::log($e, $context)` |
