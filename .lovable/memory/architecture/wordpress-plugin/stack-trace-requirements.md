# Memory: architecture/wordpress-plugin/stack-trace-requirements
Updated: 2026-02-05

## Critical Requirement

ALL WordPress plugin error responses MUST include full PHP stack traces as a **frames array** (`stackTraceFrames`) to enable structured parsing by the Go backend. This is a non-negotiable requirement for large-scale development.

## Implementation Standards (v1.7.0+)

### 1. Helper Functions (Global)
Two helper functions convert exceptions/backtraces to structured frames:

```php
function riseup_exception_to_frames($exception) { ... }
function riseup_backtrace_to_frames($backtrace) { ... }
```

### 2. error_response() Method
Returns both string and frames array:

```php
$error_data['error']['details'] = array(
    'stackTrace'       => $exception->getTraceAsString(),
    'stackTraceFrames' => riseup_exception_to_frames($exception),
);
```

### 3. Frame Structure
Each frame contains:
```json
{
  "file": "/full/path/to/file.php",
  "fileBase": "file.php",
  "line": 123,
  "function": "methodName",
  "class": "ClassName"
}
```

### 4. Granular Try-Catch Pattern
All plugin lifecycle operations (enable/disable/delete) use step-by-step try-catch:

```php
// Step 1: Load plugin functions
try { ... } catch (Exception $e) { return $this->error_response(..., $e); }

// Step 2: Find plugin file
try { ... } catch (Exception $e) { return $this->error_response(..., $e); }

// Step 3-N: Each operation wrapped individually
```

### 5. Fatal Error Handler
The global shutdown handler also returns `stackTraceFrames` array for fatal errors.

## New Endpoints (v1.7.0)

- `POST /plugins/{slug}/enable` - Activate plugin
- `POST /plugins/{slug}/disable` - Deactivate plugin  
- `DELETE /plugins/{slug}/delete` - Remove plugin

## Version History

- v1.7.0: Added stackTraceFrames array, granular try-catch, enable/disable/delete endpoints
- v1.6.0: Initial stack trace implementation with string-only format
