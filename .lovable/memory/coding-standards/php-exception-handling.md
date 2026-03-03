# Memory: coding-standards/php-exception-handling
Updated: 2026-03-03

## Critical Rule: Throwable-First Error Logging

Every error logging call **MUST** accept the `Throwable` object as the primary input — not a string message. The message is secondary; the **stack trace is the most important part**.

### Mandatory Pattern: `error_log` with Throwable

```php
} catch (Throwable $e) {
    error_log('Context message: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}
```

The method internally:
1. Logs `$e->getMessage()` first (the human-readable summary)
2. Appends `"\n" . $e->getTraceAsString()` (the full call stack — **this is the critical part**)

### Mandatory Pattern: FileLogger with Throwable

```php
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Context message');
}
```

`logException()` accepts `Throwable $e` as its **first parameter**. Internally it:
1. Logs `$e->getMessage()` (the summary)
2. Logs `$e->getTraceAsString()` in the context array and dedicated stacktrace file
3. Persists to error sessions DB (Riseup Asia)

### Mandatory Pattern: safeExecute / errorResponse

These already capture `$e` internally and include `stackTraceFrames` — no action needed at the call site.

## Rules

1. **Throwable is the primary input** — every error logging method must accept `Throwable` as its first parameter, not a string message
2. **Stack trace is the most important output** — `$e->getTraceAsString()` must always be logged after `$e->getMessage()`
3. **Never** log `$e->getMessage()` alone — always append `"\n" . $e->getTraceAsString()`
4. `$e->getMessage()` returns ONLY the message string, never the trace
5. `$e->getTraceAsString()` returns the full call stack as a formatted string
6. `$e->getTrace()` returns the structured array (used for REST API frame arrays)
7. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
8. No exceptions to this rule — even in autoloaders, bootstrap, deactivation hooks
9. Only permitted silent catch: logger recursion guards (to prevent infinite loops)
10. Reducing or omitting stack traces is treated as a critical defect
