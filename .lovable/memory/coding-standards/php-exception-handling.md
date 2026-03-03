# Memory: coding-standards/php-exception-handling
Updated: 2026-03-03

## Critical Rule: Stack Traces Are MANDATORY

Every `catch` block that has access to an exception (`$e`) **MUST** log the full stack trace via `$e->getTraceAsString()`. Logging only `$e->getMessage()` is a **serious violation** — it strips all diagnostic context and makes production debugging impossible.

### Pattern: error_log with exception

```php
} catch (Throwable $e) {
    error_log('Context message: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}
```

### Pattern: FileLogger with exception

```php
} catch (Throwable $e) {
    $this->fileLogger->logException($e, 'Context message');
}
```

### Pattern: safeExecute / error_response

These already capture `$e` internally and include `stackTraceFrames` — no action needed at the call site.

## Rules

1. **Never** log `$e->getMessage()` alone — always append `"\n" . $e->getTraceAsString()`
2. `$e->getMessage()` returns ONLY the message string, never the trace
3. `$e->getTraceAsString()` returns the full call stack as a formatted string
4. `$e->getTrace()` returns the structured array (used for REST API frame arrays)
5. This applies to ALL plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
6. No exceptions to this rule — even in autoloaders, bootstrap, deactivation hooks
7. Reducing or omitting stack traces is treated as a critical defect
