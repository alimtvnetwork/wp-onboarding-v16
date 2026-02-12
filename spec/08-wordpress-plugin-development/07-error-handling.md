# WordPress Plugin Error Handling

## Core Principle

> **Every operation that can fail MUST be wrapped in try-catch with logging.**

Errors should never cause silent failures or unexplained crashes. Every error must be:
1. Caught
2. Logged with context
3. Handled gracefully

## Try-Catch Pattern

### Basic Structure

```php
public function some_operation() {
    $this->file_logger->log('Starting operation', __FILE__, __LINE__);
    
    try {
        // Risky operation
        $result = $this->do_something_risky();
        
        $this->file_logger->log('Operation completed successfully', __FILE__, __LINE__);
        return $result;
        
    } catch (Exception $e) {
        $this->file_logger->error(
            sprintf('Operation failed: %s', $e->getMessage()),
            __FILE__,
            __LINE__
        );
        
        // Return safe default or re-throw
        return null;
    }
}
```

### With Specific Exception Types

```php
public function database_operation() {
    try {
        $pdo = $this->get_pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        // Database-specific error
        $this->file_logger->error(
            sprintf('Database error [%s]: %s', $e->getCode(), $e->getMessage()),
            __FILE__,
            __LINE__
        );
        return [];
        
    } catch (Exception $e) {
        // Catch-all for unexpected errors
        $this->file_logger->error(
            sprintf('Unexpected error: %s', $e->getMessage()),
            __FILE__,
            __LINE__
        );
        throw $e;  // Re-throw unexpected errors
    }
}
```

## Operations That MUST Have Error Handling

### 1. Database Operations

```php
public function insert_record($data) {
    $this->file_logger->log(
        sprintf('Inserting record into %s', RISEUP_TABLE_TRANSACTIONS),
        __FILE__,
        __LINE__
    );
    
    try {
        $pdo = $this->db->get_pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        $id = $pdo->lastInsertId();
        $this->file_logger->log(
            sprintf('Inserted record ID: %d', $id),
            __FILE__,
            __LINE__
        );
        
        return $id;
        
    } catch (PDOException $e) {
        $this->file_logger->error(
            sprintf('Insert failed: %s | SQL: %s', $e->getMessage(), $sql),
            __FILE__,
            __LINE__
        );
        return false;
    }
}
```

### 2. File Operations

```php
public function save_file($path, $content) {
    $this->file_logger->log(
        sprintf('Saving file: %s', $path),
        __FILE__,
        __LINE__
    );
    
    try {
        $dir = dirname($path);
        
        // Ensure directory exists
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create directory: {$dir}");
            }
        }
        
        // Write file
        $bytes = @file_put_contents($path, $content, LOCK_EX);
        
        if ($bytes === false) {
            throw new Exception("Failed to write file: {$path}");
        }
        
        $this->file_logger->log(
            sprintf('Saved %d bytes to %s', $bytes, $path),
            __FILE__,
            __LINE__
        );
        
        return true;
        
    } catch (Exception $e) {
        $this->file_logger->error($e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}
```

### 3. External API Calls

```php
public function call_external_api($url, $data) {
    $this->file_logger->log(
        sprintf('API request: POST %s', $url),
        __FILE__,
        __LINE__
    );
    
    try {
        $response = wp_remote_post($url, [
            'body' => wp_json_encode($data),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->file_logger->log(
            sprintf('API response: %d | Body length: %d', $code, strlen($body)),
            __FILE__,
            __LINE__
        );
        
        if ($code >= 400) {
            throw new Exception("API error {$code}: {$body}");
        }
        
        return json_decode($body, true);
        
    } catch (Exception $e) {
        $this->file_logger->error(
            sprintf('API call failed: %s', $e->getMessage()),
            __FILE__,
            __LINE__
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
        // Initialize database
        $this->db = Riseup_Database::get_instance();
        $this->db->init();
        
        // Initialize other components
        $this->logger = new Riseup_Logger($this->file_logger);
        
        $this->file_logger->log('Plugin initialized successfully', __FILE__, __LINE__);
        
    } catch (Exception $e) {
        $this->file_logger->error(
            sprintf('Plugin initialization failed: %s', $e->getMessage()),
            __FILE__,
            __LINE__
        );
        
        // Don't throw - allow WordPress to continue, but plugin is degraded
        add_action('admin_notices', [$this, 'show_init_error_notice']);
    }
}

public function show_init_error_notice() {
    echo '<div class="notice notice-error">';
    echo '<p><strong>' . esc_html(RISEUP_PLUGIN_NAME) . ':</strong> ';
    echo 'Failed to initialize. Please check the error logs.</p>';
    echo '</div>';
}
```

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
    private $db_available = true;
    
    private function get_db() {
        if (!$this->db_available) {
            return null;  // Don't keep trying
        }
        
        if ($this->db === null) {
            try {
                $this->db = Riseup_Database::get_instance();
            } catch (Exception $e) {
                $this->file_logger->error(
                    'Database unavailable, falling back to file-only logging',
                    __FILE__,
                    __LINE__
                );
                $this->db_available = false;
                return null;
            }
        }
        
        return $this->db;
    }
    
    public function log($message, $level = 'INFO') {
        // Always log to file (reliable)
        $this->file_logger->log("[{$level}] {$message}", __FILE__, __LINE__);
        
        // Try database if available
        $db = $this->get_db();
        if ($db && $db->is_ready()) {
            try {
                $db->insert_log($message, $level);
            } catch (Exception $e) {
                // Silently fail - already logged to file
            }
        }
    }
}
```

## REST API Error Responses

### Standard Error Format

```php
public function handle_request($request) {
    try {
        $result = $this->process_request($request);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result,
        ], 200);
        
    } catch (ValidationException $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'validation_error',
                'message' => $e->getMessage(),
                'details' => $e->getErrors(),
            ],
        ], 400);
        
    } catch (AuthenticationException $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'authentication_failed',
                'message' => $e->getMessage(),
            ],
        ], 401);
        
    } catch (Exception $e) {
        $this->file_logger->error(
            sprintf('Unhandled error in API: %s', $e->getMessage()),
            __FILE__,
            __LINE__
        );
        
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'internal_error',
                'message' => 'An unexpected error occurred',
            ],
        ], 500);
    }
}
```

## Logging Stack Traces

For serious errors, include the stack trace:

```php
public function error_with_trace($message, $exception = null) {
    $full_message = $message;
    
    if ($exception instanceof Exception) {
        $full_message .= "\n" . get_class($exception) . ': ' . $exception->getMessage();
        $full_message .= "\nStack trace:\n" . $exception->getTraceAsString();
    }
    
    $this->file_logger->error($full_message, __FILE__, __LINE__);
}
```

## Fatal Error Handler

Register a shutdown function to catch fatal errors:

```php
class Riseup_Asia_Uploader {
    public function __construct() {
        register_shutdown_function([$this, 'handle_shutdown']);
    }
    
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->file_logger->error(
                sprintf(
                    'Fatal error: %s in %s on line %d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                ),
                __FILE__,
                __LINE__
            );
        }
    }
}
```

## Common Error Scenarios

### 1. Missing Directory Permissions

```php
if (!is_writable($dir)) {
    $this->file_logger->error(
        sprintf('Directory not writable: %s', $dir),
        __FILE__,
        __LINE__
    );
    throw new Exception("Cannot write to directory: {$dir}");
}
```

### 2. Database Connection Failed

```php
try {
    $this->pdo = new PDO($dsn);
} catch (PDOException $e) {
    $this->file_logger->error(
        sprintf('Database connection failed: %s | DSN: %s', $e->getMessage(), $dsn),
        __FILE__,
        __LINE__
    );
    throw new Exception('Database connection failed. Check logs for details.');
}
```

### 3. Invalid JSON Data

```php
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $this->file_logger->error(
        sprintf('JSON decode failed: %s | Input: %s', json_last_error_msg(), substr($json, 0, 100)),
        __FILE__,
        __LINE__
    );
    throw new Exception('Invalid JSON data');
}
```
