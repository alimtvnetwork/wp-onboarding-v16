# Memory: coding-standards/php-exception-handling
Updated: 2026-03-03

## Critical Rule: Throwable-First Error Logging

Every error logging call **MUST** accept the `Throwable` object as the primary input — not a string message. The message is secondary; the **stack trace is the most important part**.

## Mandatory Patterns (by context)

### Pattern 1: FileLogger — `logException()`

When `$this->fileLogger` is available (Plugin classes, managers, services):

```php
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Context message');
}
```

`logException()` internally extracts `$e->getMessage()`, `$e->getTraceAsString()`, file, and line. One call does everything.

### Pattern 2: ErrorLog helper — `errorLog($e, context)`

When FileLogger is not available but namespaced helpers are loaded:

```php
// Riseup Asia
} catch (Throwable $e) {
    InitHelpers::errorLog($e, 'ClassName::method() failed:');
}

// QUpload
} catch (Throwable $e) {
    ErrorLogHelper::errorLog($e, 'ClassName::method() failed:');
}

// Plugins Onboard
} catch (Exception $e) {
    OnboardErrorLog::errorLog($e, 'Context message:');
}
```

`errorLog()` internally calls `error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString())`.

### Pattern 3: Autoloaders — raw `error_log()`

**Only** in autoloader files (loaded before any helper class exists):

```php
} catch (Throwable $e) {
    error_log('Prefix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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

### Pattern 5: safeExecute / errorResponse

These already capture `$e` internally and include `stackTraceFrames` — no action needed at the call site.

## Rules

1. **Throwable is the primary input** — every error logging method must accept `Throwable` as its first parameter
2. **Stack trace is the most important output** — must always be logged, never omitted
3. **Never** manually write `error_log('msg: ' . $e->getMessage() . "\n" . $e->getTraceAsString())` — use `errorLog($e, 'msg:')` instead (except autoloaders)
4. **Never** log `$e->getMessage()` alone without the trace
5. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
6. No exceptions to this rule — even in bootstrap, deactivation hooks
7. Only permitted raw `error_log()` with exception: autoloader files (2 total)
8. Only permitted silent catch: logger recursion guards (to prevent infinite loops)
9. Reducing or omitting stack traces is treated as a critical defect

## Helper Locations

| Plugin | Helper | Method |
|---|---|---|
| Riseup Asia | `RiseupAsia\Helpers\InitHelpers` | `::errorLog($e, $context)` |
| QUpload | `QUpload\Helpers\ErrorLogHelper` | `::errorLog($e, $context)` |
| Plugins Onboard | `OnboardErrorLog` (global) | `::errorLog($e, $context)` |
