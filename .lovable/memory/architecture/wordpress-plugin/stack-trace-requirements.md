# Memory: architecture/wordpress-plugin/stack-trace-requirements
Updated: 2026-02-06

## Critical Requirement

ALL WordPress plugin error responses MUST include full PHP stack traces as a **frames array** (`stackTraceFrames`) to enable structured parsing by the Go backend. This is a non-negotiable requirement for large-scale development.

## Implementation Standards (v1.7.0+)

### 1. Throwable Catching (MANDATORY)

All try-catch blocks MUST catch `Throwable` instead of `Exception` to capture both:
- `Exception` - Standard PHP exceptions
- `Error` - PHP 7+ fatal errors (class not found, type errors, etc.)

```php
try {
    // code
} catch (Throwable $e) {
    return $this->error_response($message, 500, $e);
}
```

### 2. Helper Functions (Global)

Two helper functions convert exceptions/backtraces to structured frames:

```php
function riseup_exception_to_frames($exception) { ... }
function riseup_backtrace_to_frames($backtrace) { ... }
```

### 3. error_response() Method

Returns both string and frames array:

```php
$error_data['error']['details'] = array(
    'stackTrace'       => $exception->getTraceAsString(),
    'stackTraceFrames' => riseup_exception_to_frames($exception),
);
```

### 4. Frame Structure

Each frame contains (external keys from PHP `debug_backtrace()`):
```json
{
  "file": "/full/path/to/file.php",
  "fileBase": "file.php",
  "line": 123,
  "function": "methodName",
  "class": "ClassName"
}
```

### 5. safe_execute() Wrapper

New helper method for wrapping callbacks with comprehensive error handling:

```php
private function safe_execute($callback, $context, $log_context = array()) {
    try {
        return call_user_func($callback);
    } catch (Throwable $e) {
        return $this->error_response("Error in {$context}: " . $e->getMessage(), 500, $e);
    }
}
```

### 6. Enhanced Shutdown Handler

The global shutdown handler (`riseup_fatal_error_handler`) now:
- Logs to a dedicated `fatal-errors.log` before JSON output
- Includes memory usage statistics for OOM detection
- Handles JSON encoding failures gracefully
- Captures backtrace in shutdown context when available

### 7. Go Backend Empty Response Handling

When WordPress returns an empty response body with status 500, the Go backend now:
- Adds diagnostic message explaining possible causes
- Points to relevant WordPress log files
- Parses PHP stack trace frames from error responses when available

## Version History

- v1.7.0: Added Throwable catching, safe_execute(), enhanced shutdown handler, empty response diagnostics
- v1.6.0: Initial stack trace implementation with string-only format
