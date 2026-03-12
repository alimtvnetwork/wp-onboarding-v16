# Memory: coding-standards/php-exception-handling
Updated: 2026-03-12

## Critical Rule: Throwable-First Error Logging

Every error logging call **MUST** accept the `Throwable` object as the primary input — not a string message. The message is secondary; the **stack trace is the most important part**.

## Critical Rule: Throw From the Helper, Not the Catch Block

In **boot, autoloader, file-loading, route registration, migration, and infrastructure** catch blocks, the exception **MUST be re-thrown** after logging. However, the `throw` happens **inside the logging helper** — not at each call site. This eliminates the error-prone pattern of manually adding `throw $e;` after every log call.

### Throwing Helpers (boot/infrastructure contexts):
```php
// Static helpers — throw internally
InitHelpers::errorLogAndThrow($e, 'Context:');      // Riseup Asia
ErrorLogHelper::logAndThrow($e, 'Context:');         // QUpload
OnboardErrorLog::logAndThrow($e, 'Context:');        // Plugins Onboard

// FileLogger — throw internally
$this->fileLogger->logCriticalException($e, 'Context');  // Both packages
```

### Non-Throwing Helpers (handler boundaries):
```php
// Static helpers — log only, no throw
InitHelpers::errorLog($e, 'Context:');               // Riseup Asia
ErrorLogHelper::log($e, 'Context:');                  // QUpload
OnboardErrorLog::log($e, 'Context:');                 // Plugins Onboard

// FileLogger — log only, no throw
$this->fileLogger->logException($e, 'Context');       // Both packages
$this->fileLogger->logDebugException($e, 'Context');  // Riseup Asia (recoverable)
```

### Re-Throw Required (boot/load contexts) — use throwing helpers:
```php
// Boot initialization
} catch (Throwable $e) {
    BootErrorCollector::getInstance()->addError('context', $e->getMessage() . "\n" . $e->getTraceAsString());
    InitHelpers::errorLogAndThrow($e, 'Plugin init failed:');
}

// Route registration
} catch (Throwable $e) {
    $this->fileLogger->logCriticalException($e, 'Failed to register route');
}

// Enum/file priming (require_once)
} catch (Throwable $e) {
    $this->fileLogger->logCriticalException($e, 'Failed to preload dependency');
}

// Database migrations
} catch (Throwable $e) {
    $this->pdo->rollBack();
    $this->fileLogger->logCriticalException($e, 'Migration vN failed — rolled back');
}
```

### Re-Throw Prohibited (handler boundaries) — use non-throwing helpers:
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

### Pattern 1: FileLogger — `logException()` / `logCriticalException()`

When `$this->fileLogger` is available (Plugin classes, managers, services):

```php
// Non-throwing (handler boundaries, recoverable)
$this->fileLogger->logException($e, 'Context message');

// Throwing (boot, routes, migrations, infrastructure)
$this->fileLogger->logCriticalException($e, 'Context message');
```

### Pattern 2: ErrorLog helper — `log()` / `logAndThrow()` and `errorLog()` / `errorLogAndThrow()`

When FileLogger is not available but namespaced helpers are loaded:

```php
// Riseup Asia — non-throwing
InitHelpers::errorLog($e, 'Context:');
// Riseup Asia — throwing
InitHelpers::errorLogAndThrow($e, 'Context:');

// QUpload — non-throwing
ErrorLogHelper::log($e, 'Context:');
// QUpload — throwing
ErrorLogHelper::logAndThrow($e, 'Context:');

// Plugins Onboard — non-throwing
OnboardErrorLog::log($e, 'Context:');
// Plugins Onboard — throwing
OnboardErrorLog::logAndThrow($e, 'Context:');
```

### Pattern 3: Autoloaders — raw `error_log()`

**Only** in autoloader files (loaded before any helper class exists):

```php
} catch (Throwable $e) {
    error_log('Prefix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e; // Only place where manual throw is acceptable
}
```

This is the **only** context where manual `getMessage()` + `getTraceAsString()` concatenation and manual `throw $e` are acceptable.

### Pattern 4: Snapshot trait logError/logWarn

When inside Snapshot traits/classes that have a `log()` method:

```php
} catch (Throwable $e) {
    $this->logError($e, 'Context message');
}
```

### Pattern 5: safeExecute / errorResponse (Handler Boundaries)

Top-level REST handler catch blocks use `safeExecute()` which handles everything internally. No action needed at the call site:

```php
return $this->safeExecute(
    fn () => $this->executePipeline($request),
    'handleUpload',
    ['endpoint' => 'upload'],
);
```

### Pattern 6: Transaction rollback + throw

When a catch block must perform cleanup (rollback) before re-throwing, use `logCriticalException()` which handles the throw internally:

```php
} catch (Throwable $e) {
    $this->pdo->rollBack();
    $this->fileLogger->logCriticalException($e, 'Migration failed — rolled back');
}
```

## Rules

1. **Throwable is the primary input** — every error logging method must accept `Throwable` as its first parameter
2. **Stack trace is the most important output** — must always be logged, never omitted
3. **Throw from the helper, not the catch block** — use `logCriticalException()` / `logAndThrow()` / `errorLogAndThrow()` instead of manual `throw $e;`
4. **Never** manually write `error_log('msg: ' . $e->getMessage() . "\n" . $e->getTraceAsString())` — use the appropriate helper (except autoloaders)
5. **Never** log `$e->getMessage()` alone without the trace
6. **Always emit to PHP error_log()** in handler boundaries so errors surface in PHP debug
7. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
8. No exceptions to this rule — even in bootstrap, deactivation hooks
9. Only permitted raw `error_log()` with manual `throw $e`: autoloader files (2 total)
10. Only permitted silent catch: logger recursion guards (to prevent infinite loops)
11. Reducing or omitting stack traces is treated as a critical defect

## Helper Locations

| Plugin | Helper | Non-Throwing Method | Throwing Method |
|---|---|---|---|
| Riseup Asia | `RiseupAsia\Helpers\InitHelpers` | `::errorLog($e, $ctx)` | `::errorLogAndThrow($e, $ctx)` |
| Riseup Asia | `RiseupAsia\Logging\FileLogger` | `->logException($e, $ctx)` | `->logCriticalException($e, $ctx)` |
| QUpload | `QUpload\Helpers\ErrorLogHelper` | `::log($e, $ctx)` | `::logAndThrow($e, $ctx)` |
| QUpload | `QUpload\Logging\FileLogger` | `->logException($e, $ctx)` | `->logCriticalException($e, $ctx)` |
| Plugins Onboard | `OnboardErrorLog` (global) | `::log($e, $ctx)` | `::logAndThrow($e, $ctx)` |
