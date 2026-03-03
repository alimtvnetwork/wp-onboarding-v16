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

### Pattern 2: Snapshot Traits — `logError()` (NEW)

When using the `$this->log()` pattern (Snapshot orchestrators, workers, cleaners):

```php
} catch (Throwable $e) {
    $this->logError($e, 'Context message', ['extra_key' => $value]);
}
```

`logError()` internally calls `$this->log()` and auto-injects `'error' => $e->getMessage()` and `'trace' => $e->getTraceAsString()` into the context array. The third parameter is optional extra context that gets merged in.

**NEVER do this:**
```php
// ❌ WRONG — manual getMessage/getTraceAsString is verbose and error-prone
$this->log(LogLevelType::Error->value, 'Failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
```

### Pattern 3: Bootstrap/Autoloader — `error_log()`

When no class infrastructure is available (autoloaders, bootstrap files, activation hooks):

```php
} catch (Throwable $e) {
    error_log('Prefix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}
```

This is the only context where manual `getMessage()` + `getTraceAsString()` concatenation is acceptable.

### Pattern 4: safeExecute / errorResponse

These already capture `$e` internally and include `stackTraceFrames` — no action needed at the call site.

## Rules

1. **Throwable is the primary input** — every error logging method must accept `Throwable` as its first parameter
2. **Stack trace is the most important output** — must always be logged, never omitted
3. **Never** manually add `'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()` in context arrays — use `logError($e, msg)` instead
4. **Never** log `$e->getMessage()` alone without the trace
5. The `logError()` method handles level (Error), message extraction, and trace inclusion automatically
6. For warn-level exceptions, use `logWarn($e, msg, context)` (same pattern, Warn level)
7. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
8. No exceptions to this rule — even in autoloaders, bootstrap, deactivation hooks
9. Only permitted silent catch: logger recursion guards (to prevent infinite loops)
10. Reducing or omitting stack traces is treated as a critical defect
