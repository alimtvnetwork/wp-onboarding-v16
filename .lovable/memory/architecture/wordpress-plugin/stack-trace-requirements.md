# Memory: architecture/wordpress-plugin/stack-trace-requirements
Updated: 2026-02-05

## Critical Requirement

ALL WordPress plugin error responses MUST include full PHP stack traces to enable debugging from the Go backend. This is a non-negotiable requirement for large-scale development.

## Implementation Standards

### 1. error_response() Method
The `error_response()` method accepts an optional third parameter for exceptions:

```php
private function error_response($message, $status, $exception = null) {
    // If exception provided, include full stack trace
    // If not, generate a stack trace via debug_backtrace()
}
```

### 2. Response Structure
Error responses MUST follow this structure:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable message",
    "details": {
      "exceptionClass": "Exception",
      "file": "filename.php",
      "fileFull": "/full/path/to/filename.php",
      "line": 123,
      "stackTrace": "#0 file.php(123): function()\n#1 ..."
    }
  }
}
```

### 3. Catch Block Pattern
All catch blocks MUST pass the exception to error_response:

```php
} catch (Exception $e) {
    $this->file_logger->log_exception($e, 'Context message');
    return $this->error_response('Error message: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
}
```

### 4. Fatal Error Handler
The global shutdown handler captures fatal errors (E_ERROR, E_PARSE, etc.) and returns:
- Error type (e.g., E_ERROR)
- Error type name (human-readable)
- Error message
- File location (basename and full path)
- Line number
- Pseudo stack trace
- PHP version
- WordPress version

## Version Requirements

This requirement was implemented in Riseup Asia Uploader v1.6.0. All future versions MUST maintain this behavior.

## Backend Logging

The Go backend (`backend/internal/wordpress/client.go`) captures these stack traces in `APIError.StackTrace` and includes them in:
- Session logs
- Error modal details
- Debug bundles

## Files Involved

- `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` - Main plugin with error_response()
- `backend/internal/wordpress/uploader.go` - Client that parses error responses
- `backend/internal/wordpress/client.go` - APIError struct with StackTrace field
