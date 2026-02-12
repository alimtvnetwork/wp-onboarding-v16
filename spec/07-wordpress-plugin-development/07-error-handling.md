# WordPress Plugin Error Handling

> **Version:** 2.0.0  
> **Updated:** 2026-02-12

## Core Principle

> **Every operation that can fail MUST be wrapped in try-catch with logging.**  
> **Always catch `\Throwable`, not just `Exception`.**

Errors should never cause silent failures or unexplained crashes. Every error must be:
1. Caught (as `\Throwable`)
2. Logged with context
3. Handled gracefully

---

## Try-Catch Pattern

### Rule: Catch `\Throwable`, Not `Exception`

PHP 7+ introduces `Error` and `TypeError` that are **not** subclasses of `Exception`. Catching only `Exception` will miss fatal-class errors like missing classes, type mismatches, and division by zero.

```php
// ❌ FORBIDDEN: Misses PHP 7+ Error types
try {
    $result = $manager->process();
} catch (Exception $e) {
    $this->file_logger->error($e->getMessage(), __FILE__, __LINE__);
}

// ✅ REQUIRED: Catches all throwables
try {
    $result = $manager->process();
} catch (\Throwable $e) {
    $this->file_logger->log_exception($e, 'process_failed');
    wp_send_json_error([
        'message'          => $e->getMessage(),
        'stackTrace'       => $e->getTraceAsString(),
        'stackTraceFrames' => $this->formatStackFrames($e),
    ], 500);
}
```

### With Specific Exception Types

When you need to handle specific error types differently, catch them first, then catch `\Throwable` as the final fallback:

```php
public function database_operation() {
    try {
        $pdo = $this->get_pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        // Database-specific error — known, recoverable
        $this->file_logger->error(
            sprintf('Database error [%s]: %s', $e->getCode(), $e->getMessage()),
            __FILE__, __LINE__
        );
        return [];

    } catch (\Throwable $e) {
        // Catch-all for unexpected errors (TypeError, Error, etc.)
        $this->file_logger->error(
            sprintf('Unexpected error: %s', $e->getMessage()),
            __FILE__, __LINE__
        );
        throw $e; // Re-throw unexpected errors
    }
}
```

---

## Safe Execute Wrapper

All REST endpoint handlers must be wrapped in `safe_execute`:

```php
// ✅ Pattern: safe_execute wrapper
public function handle_upload($request) {
    return $this->safe_execute(function() use ($request) {
        // Business logic here
        return $this->envelope->success($result);
    });
}

private function safe_execute(callable $callback) {
    try {
        return $callback();
    } catch (\Throwable $e) {
        $this->logger->log_exception($e, 'endpoint_error');
        return $this->envelope->error($e->getMessage(), 500);
    }
}
```

---

## Fatal Error Handler — ErrorChecker

### Rule: Centralize Fatal Error Detection

Never write inline `in_array($error['type'], [E_ERROR, ...])` checks. Use `ErrorChecker::is_fatal_error()` which delegates to `ErrorTypeEnum::FATAL_TYPES` (see [PHP Enum Spec](../04-php-standards/enums.md)).

### Why ErrorChecker Exists

| Problem | Solution |
|---------|----------|
| Inline `in_array(...)` is duplicated across shutdown handlers, loggers, health checks | Single `ErrorChecker::is_fatal_error()` call |
| Developers forget which `E_*` constants are "fatal" | `ErrorTypeEnum::FATAL_TYPES` defines the list once |
| AI cannot easily reason about scattered `in_array` checks | A named method (`is_fatal_error`) is self-documenting |
| Adding a new fatal type (e.g., future PHP version) requires hunting all call sites | Update `ErrorTypeEnum::FATAL_TYPES` in one place |

### ErrorChecker Implementation

```php
/**
 * Centralized error-type inspection.
 *
 * Encapsulates raw E_* constant checks so callers never need to
 * remember the specific list. Delegates to ErrorTypeEnum for the
 * actual constant groupings.
 */
class ErrorChecker {

    /**
     * Determine whether the given error array represents a fatal PHP error.
     *
     * Checks: E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR
     * (defined in ErrorTypeEnum::FATAL_TYPES)
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return bool  True when $error is non-null and its type is fatal.
     */
    public static function is_fatal_error(?array $error): bool {
        if ($error === null) {
            return false;
        }
        return in_array($error['type'], ErrorTypeEnum::FATAL_TYPES, true);
    }

    /**
     * Determine whether the given error is a warning-level error.
     *
     * @param array|null $error  Value returned by error_get_last()
     * @return bool
     */
    public static function is_warning(?array $error): bool {
        if ($error === null) {
            return false;
        }
        return in_array($error['type'], ErrorTypeEnum::WARNING_TYPES, true);
    }

    /**
     * Get a human-readable severity label.
     *
     * @param array|null $error
     * @return string 'fatal', 'warning', or 'unknown'
     */
    public static function get_severity_label(?array $error): string {
        if (self::is_fatal_error($error)) return 'fatal';
        if (self::is_warning($error))     return 'warning';
        return 'unknown';
    }
}
```

### ErrorTypeEnum (Backing Constants)

```php
class ErrorTypeEnum {
    /** Error types that terminate PHP execution */
    public const FATAL_TYPES = [
        E_ERROR,         // Fatal run-time error
        E_PARSE,         // Compile-time parse error
        E_CORE_ERROR,    // Fatal error during PHP startup
        E_COMPILE_ERROR, // Fatal compile-time error
    ];

    /** Warning-level error types (non-fatal, logged) */
    public const WARNING_TYPES = [
        E_WARNING,
        E_CORE_WARNING,
        E_NOTICE,
        E_DEPRECATED,
    ];
}
```

### Shutdown Handler Registration

```php
// ❌ FORBIDDEN: Inline error-type checking
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // ...
    }
});

// ✅ REQUIRED: Use ErrorChecker for readable, centralized detection
register_shutdown_function(function() {
    $error = error_get_last();
    if (ErrorChecker::is_fatal_error($error)) {
        // Log to fatal-errors.log with memory usage
        // Send JSON response before process dies
    }
});
```

### Complete Shutdown Handler

```php
class Riseup_Asia_Uploader {
    public function __construct() {
        register_shutdown_function([$this, 'handle_shutdown']);
    }

    /**
     * Catches fatal PHP errors that bypass try-catch.
     *
     * Called automatically by PHP when the process terminates.
     * Uses ErrorChecker::is_fatal_error() to determine if the
     * last error was fatal (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR).
     */
    public function handle_shutdown() {
        $error = error_get_last();

        if (!ErrorChecker::is_fatal_error($error)) {
            return; // Clean shutdown or non-fatal error — nothing to do
        }

        $severity = ErrorChecker::get_severity_label($error);

        // Log to fatal-errors.log via typed accessor
        $log_path = RiseupPathUtils::getFatalErrorLog();
        $timestamp = gmdate('Y-m-d\TH:i:s.') . sprintf('%03d', (microtime(true) * 1000) % 1000) . 'Z';
        $memory_peak = memory_get_peak_usage(true);

        $entry = sprintf(
            "[%s] [%s] %s in %s on line %d | Memory peak: %s\n",
            $timestamp,
            strtoupper($severity),
            $error['message'],
            $error['file'],
            $error['line'],
            size_format($memory_peak)
        );

        @file_put_contents($log_path, $entry, FILE_APPEND | LOCK_EX);

        // If this was a REST request, try to send a JSON error response
        if ($this->is_rest_request()) {
            @header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode([
                'success' => false,
                'error' => [
                    'code'    => 'fatal_error',
                    'message' => 'A fatal error occurred',
                    'file'    => basename($error['file']),
                    'line'    => $error['line'],
                ],
            ]);
        }
    }

    private function is_rest_request(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}
```

---

## Operations That MUST Have Error Handling

### 1. Database Operations

```php
public function insert_record($data) {
    $this->file_logger->log(
        sprintf('Inserting record into %s', RISEUP_TABLE_TRANSACTIONS),
        __FILE__, __LINE__
    );

    try {
        $pdo = $this->db->get_pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $id = $pdo->lastInsertId();
        $this->file_logger->log(sprintf('Inserted record ID: %d', $id), __FILE__, __LINE__);
        return $id;

    } catch (PDOException $e) {
        $this->file_logger->error(
            sprintf('Insert failed: %s | SQL: %s', $e->getMessage(), $sql),
            __FILE__, __LINE__
        );
        return false;

    } catch (\Throwable $e) {
        $this->file_logger->error(
            sprintf('Unexpected insert error: %s', $e->getMessage()),
            __FILE__, __LINE__
        );
        return false;
    }
}
```

### 2. File Operations

```php
public function save_file($path, $content) {
    $this->file_logger->log(sprintf('Saving file: %s', $path), __FILE__, __LINE__);

    try {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }

        $bytes = @file_put_contents($path, $content, LOCK_EX);

        if ($bytes === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }

        $this->file_logger->log(sprintf('Saved %d bytes to %s', $bytes, $path), __FILE__, __LINE__);
        return true;

    } catch (\Throwable $e) {
        $this->file_logger->error($e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}
```

### 3. External API Calls

```php
public function call_external_api($url, $data) {
    $this->file_logger->log(sprintf('API request: POST %s', $url), __FILE__, __LINE__);

    try {
        $response = wp_remote_post($url, [
            'body' => wp_json_encode($data),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->file_logger->log(
            sprintf('API response: %d | Body length: %d', $code, strlen($body)),
            __FILE__, __LINE__
        );

        if ($code >= 400) {
            throw new \RuntimeException("API error {$code}: {$body}");
        }

        return json_decode($body, true);

    } catch (\Throwable $e) {
        $this->file_logger->error(
            sprintf('API call failed: %s', $e->getMessage()),
            __FILE__, __LINE__
        );
        return null;
    }
}
```

### 4. Plugin Initialization

```php
public function init() {
    $this->file_logger->log('Plugin initialization starting', __FILE__, __LINE__);

    try {
        $this->db = Riseup_Database::get_instance();
        $this->db->init();

        $this->logger = new Riseup_Logger($this->file_logger);

        $this->file_logger->log('Plugin initialized successfully', __FILE__, __LINE__);

    } catch (\Throwable $e) {
        $this->file_logger->error(
            sprintf('Plugin initialization failed: %s', $e->getMessage()),
            __FILE__, __LINE__
        );

        // Don't throw — allow WordPress to continue, but plugin is degraded
        add_action(HookEnum::ADMIN_NOTICES, [$this, 'show_init_error_notice']);
    }
}

public function show_init_error_notice() {
    echo '<div class="notice notice-error">';
    echo '<p><strong>' . esc_html(RISEUP_PLUGIN_NAME) . ':</strong> ';
    echo 'Failed to initialize. Please check the error logs.</p>';
    echo '</div>';
}
```

---

## Graceful Degradation

When errors occur, the plugin should:
1. Log the error with full context
2. Return a safe default value
3. Continue operating in a degraded mode if possible
4. Notify the admin of issues

### Example: Database Unavailable

```php
class Riseup_Logger {
    private $db = null;
    private $is_db_available = true;

    private function get_db() {
        if (!$this->is_db_available) {
            return null; // Don't keep retrying
        }

        if ($this->db === null) {
            try {
                $this->db = Riseup_Database::get_instance();
            } catch (\Throwable $e) {
                $this->file_logger->error(
                    'Database unavailable, falling back to file-only logging',
                    __FILE__, __LINE__
                );
                $this->is_db_available = false;
                return null;
            }
        }

        return $this->db;
    }

    public function log($message, $level = 'INFO') {
        // Always log to file first (reliable)
        $this->file_logger->log("[{$level}] {$message}", __FILE__, __LINE__);

        // Try database if available
        $db = $this->get_db();
        if ($db && $db->is_ready()) {
            try {
                $db->insert_log($message, $level);
            } catch (\Throwable $e) {
                // Silently fail — already logged to file
            }
        }
    }
}
```

---

## REST API Error Responses

### Standard Error Format

```php
public function handle_request($request) {
    return $this->safe_execute(function() use ($request) {
        $result = $this->process_request($request);

        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);
    });
}
```

For cases where you need specific HTTP status codes per exception type:

```php
public function handle_request($request) {
    try {
        $result = $this->process_request($request);

        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);

    } catch (ValidationException $e) {
        return $this->envelope->error($e->getMessage(), 400, 'validation_error');

    } catch (AuthenticationException $e) {
        return $this->envelope->error($e->getMessage(), 401, 'authentication_failed');

    } catch (\Throwable $e) {
        $this->file_logger->error(
            sprintf('Unhandled error in API: %s', $e->getMessage()),
            __FILE__, __LINE__
        );
        return $this->envelope->error('An unexpected error occurred', 500);
    }
}
```

---

## Logging Stack Traces

For serious errors, capture dual outputs:

```php
/**
 * Log an exception with both structured frames and raw backtrace.
 *
 * 1. Structured frames → included in JSON error responses
 * 2. Raw backtrace → written to stacktrace.txt for deep debugging
 */
public function log_exception(\Throwable $e, string $context = '') {
    // Structured frames for JSON responses
    $frames = $this->formatStackFrames($e);

    // Raw backtrace to file (unlimited depth)
    $backtrace = debug_backtrace(0, 0);
    $stacktrace_path = RiseupPathUtils::getStacktraceFile();
    @file_put_contents($stacktrace_path, $this->formatBacktrace($backtrace), FILE_APPEND);

    // Error log entry
    $error_path = RiseupPathUtils::getErrorFile();
    $entry = sprintf(
        "[%s] [%s] %s: %s\n  File: %s:%d\n  Trace: %s\n",
        gmdate('c'),
        $context,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($error_path, $entry, FILE_APPEND | LOCK_EX);
}
```

---

## Common Error Scenarios

### 1. Missing Directory Permissions

```php
if (!is_writable($dir)) {
    $this->file_logger->error(sprintf('Directory not writable: %s', $dir), __FILE__, __LINE__);
    throw new \RuntimeException("Cannot write to directory: {$dir}");
}
```

### 2. Database Connection Failed

```php
try {
    $db_path = RiseupPathUtils::getRootDb();
    $this->pdo = new PDO('sqlite:' . $db_path);
} catch (PDOException $e) {
    $this->file_logger->error(
        sprintf('Database connection failed: %s | Path: %s', $e->getMessage(), $db_path),
        __FILE__, __LINE__
    );
    throw new \RuntimeException('Database connection failed. Check logs for details.');
}
```

### 3. Invalid JSON Data

```php
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $this->file_logger->error(
        sprintf('JSON decode failed: %s | Input: %s', json_last_error_msg(), substr($json, 0, 100)),
        __FILE__, __LINE__
    );
    throw new \RuntimeException('Invalid JSON data');
}
```

---

## Forbidden Patterns

| Pattern | Why | Required Alternative |
|---------|-----|---------------------|
| `catch (Exception $e)` | Misses `Error`, `TypeError` | `catch (\Throwable $e)` |
| Inline `in_array($error['type'], [...])` | Duplicated, hard to read | `ErrorChecker::is_fatal_error()` |
| `$db_available` (no prefix) | Ambiguous boolean name | `$is_db_available` |
| `error_log()` | No structure | `$this->file_logger->error()` |
| Magic string paths in error logs | Fragile | `RiseupPathUtils::getFatalErrorLog()` |
| `wp_die()` in REST handlers | Breaks JSON responses | `wp_send_json_error()` or envelope |
| `add_action('admin_notices', ...)` | Magic string hook | `add_action(HookEnum::ADMIN_NOTICES, ...)` |

---

## Cross-References

- [PHP Coding Standards](../04-php-standards/README.md) — ErrorChecker, safe_execute, boolean rules
- [PHP Enum Spec](../04-php-standards/enums.md) — ErrorTypeEnum, HookEnum, PathEnum full listings
- [Error Handling Cross-Stack](../05-error-manage/01-error-handling/README.md) — Three-tier error architecture
- [WordPress Initialization](./01-initialization-patterns.md) — Shutdown handler registration timing

---

*WordPress Error Handling v2.0.0 — 2026-02-12*
