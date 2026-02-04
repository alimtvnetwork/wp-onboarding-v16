# WordPress Plugin Logging Standards

## Overview

Proper logging is critical for debugging WordPress plugins, especially when errors occur during plugin load or in production environments where you can't attach a debugger.

## Log File Locations

All logs MUST be stored within the WordPress uploads directory:

```
wp-content/uploads/{plugin-slug}/
├── logs/
│   ├── log.txt        # All operational logs
│   └── error.txt      # Errors and exceptions only
├── data/
│   └── {plugin-slug}.db   # SQLite database (if used)
└── .htaccess          # Deny direct access
```

### Why wp-content/uploads?

1. **Writable by WordPress** - Always has write permissions
2. **Outside plugin directory** - Survives plugin updates
3. **Configurable** - Respects custom upload directory settings
4. **Excludable from Git** - Won't pollute version control

## Log Format Specification

### Standard Format

```
[{TIMESTAMP}] {MESSAGE} ({FILE}:{LINE})
```

### Components

| Component | Format | Example |
|-----------|--------|---------|
| Timestamp | ISO8601 UTC with milliseconds | `2026-02-04T11:32:15.847Z` |
| Message | Descriptive text | `Database migration v1 complete` |
| File | Basename only | `class-database.php` |
| Line | Integer | `142` |

### Example Log Entries

```
[2026-02-04T11:32:15.123Z] Plugin initialization starting (riseup-asia-uploader.php:45)
[2026-02-04T11:32:15.156Z] File logger initialized (class-file-logger.php:38)
[2026-02-04T11:32:15.189Z] Creating data directory: /var/www/html/wp-content/uploads/riseup-asia-uploader (class-database.php:67)
[2026-02-04T11:32:15.234Z] Database connection established (class-database.php:89)
[2026-02-04T11:32:15.267Z] Running migration v1 (class-database.php:142)
[2026-02-04T11:32:15.345Z] Created table: riseup_transactions (class-database.php:156)
[2026-02-04T11:32:15.378Z] Migration v1 complete (class-database.php:167)
[2026-02-04T11:32:15.412Z] Plugin initialization complete (riseup-asia-uploader.php:78)
```

## File Logger Implementation

The file logger is the foundation - it must work without any WordPress or database dependencies:

```php
<?php
/**
 * File Logger - Writes logs directly to filesystem
 * 
 * CRITICAL: This class must NOT depend on WordPress functions in constructor.
 * All WordPress function calls must be lazy-loaded.
 */
class Riseup_File_Logger {
    /** @var string|null Log file path - lazy initialized */
    private $log_path = null;
    
    /** @var string|null Error file path - lazy initialized */
    private $error_path = null;
    
    /** @var bool Whether paths have been initialized */
    private $initialized = false;
    
    /**
     * Constructor - must not call WordPress functions
     */
    public function __construct() {
        // Empty - all initialization is lazy
    }
    
    /**
     * Ensure log directory and paths are initialized
     * Called lazily on first log write
     */
    private function ensure_paths() {
        if ($this->initialized) {
            return;
        }
        
        // NOW safe to call WordPress functions (called during runtime, not load)
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . RISEUP_PLUGIN_SLUG;
        $logs_dir = $base_dir . '/logs';
        
        // Create directories with native PHP (more reliable)
        if (!is_dir($logs_dir)) {
            if (!@mkdir($logs_dir, 0755, true) && !is_dir($logs_dir)) {
                // Fallback: try wp-content directly
                $logs_dir = WP_CONTENT_DIR . '/' . RISEUP_PLUGIN_SLUG . '/logs';
                @mkdir($logs_dir, 0755, true);
            }
        }
        
        // Protect directory from direct access
        $htaccess = $base_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        
        $index = $logs_dir . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php // Silence is golden\n");
        }
        
        $this->log_path = $logs_dir . '/log.txt';
        $this->error_path = $logs_dir . '/error.txt';
        $this->initialized = true;
    }
    
    /**
     * Get current UTC timestamp with milliseconds
     */
    private function get_timestamp() {
        $now = microtime(true);
        $seconds = floor($now);
        $milliseconds = round(($now - $seconds) * 1000);
        return gmdate('Y-m-d\TH:i:s', $seconds) . sprintf('.%03dZ', $milliseconds);
    }
    
    /**
     * Format a log entry
     */
    private function format_entry($message, $file = '', $line = 0) {
        $timestamp = $this->get_timestamp();
        $context = '';
        
        if ($file) {
            $context = ' (' . basename($file);
            if ($line > 0) {
                $context .= ':' . $line;
            }
            $context .= ')';
        }
        
        return "[{$timestamp}] {$message}{$context}\n";
    }
    
    /**
     * Write a log entry
     */
    public function log($message, $file = '', $line = 0) {
        $this->ensure_paths();
        
        $entry = $this->format_entry($message, $file, $line);
        @file_put_contents($this->log_path, $entry, FILE_APPEND | LOCK_EX);
        
        return true;
    }
    
    /**
     * Write an error entry (to both log.txt and error.txt)
     */
    public function error($message, $file = '', $line = 0) {
        $this->ensure_paths();
        
        $entry = $this->format_entry('[ERROR] ' . $message, $file, $line);
        
        // Write to both files
        @file_put_contents($this->log_path, $entry, FILE_APPEND | LOCK_EX);
        @file_put_contents($this->error_path, $entry, FILE_APPEND | LOCK_EX);
        
        return true;
    }
    
    /**
     * Get the log file path (for external use)
     */
    public function get_log_path() {
        $this->ensure_paths();
        return $this->log_path;
    }
    
    /**
     * Get the error file path (for external use)
     */
    public function get_error_path() {
        $this->ensure_paths();
        return $this->error_path;
    }
}
```

## What to Log

### Always Log

1. **Plugin lifecycle events**
   - Initialization start/complete
   - Hook registrations
   - Dependency loading

2. **Database operations**
   - Migration start/complete
   - Table creation
   - Schema version changes

3. **REST API events**
   - Route registration
   - Request received (with endpoint)
   - Request completed (with status)

4. **File operations**
   - Directory creation
   - File writes
   - Permission changes

5. **External API calls**
   - Request URL and method
   - Response status
   - Error details

### Log Entry Examples

```php
// Plugin lifecycle
$this->file_logger->log('Plugin initialization starting', __FILE__, __LINE__);
$this->file_logger->log('Registering REST routes', __FILE__, __LINE__);

// Database operations
$this->file_logger->log(
    sprintf('Running migration v%d', $version), 
    __FILE__, 
    __LINE__
);
$this->file_logger->log(
    sprintf('Created table: %s', RISEUP_TABLE_TRANSACTIONS), 
    __FILE__, 
    __LINE__
);

// REST API
$this->file_logger->log(
    sprintf('REST request: %s %s', $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']),
    __FILE__,
    __LINE__
);

// Errors
$this->file_logger->error(
    sprintf('Database error: %s', $e->getMessage()),
    __FILE__,
    __LINE__
);
```

## Log Levels

| Level | Usage | Written To |
|-------|-------|------------|
| LOG | Normal operations | log.txt |
| ERROR | Exceptions, failures | log.txt + error.txt |

## Log Rotation

For production plugins, implement basic log rotation:

```php
private function maybe_rotate_logs() {
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $log_path = $this->get_log_path();
    if (file_exists($log_path) && filesize($log_path) > $max_size) {
        $backup = $log_path . '.' . date('Y-m-d-His') . '.bak';
        @rename($log_path, $backup);
        
        // Keep only last 5 backups
        $this->cleanup_old_logs(dirname($log_path), 5);
    }
}

private function cleanup_old_logs($dir, $keep_count) {
    $files = glob($dir . '/log.txt.*.bak');
    if (count($files) > $keep_count) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($files, 0, count($files) - $keep_count);
        foreach ($to_delete as $file) {
            @unlink($file);
        }
    }
}
```

## Integration with WordPress Debug Log

Optionally also write to WordPress debug log:

```php
public function log($message, $file = '', $line = 0) {
    // Write to our log file
    $this->ensure_paths();
    $entry = $this->format_entry($message, $file, $line);
    @file_put_contents($this->log_path, $entry, FILE_APPEND | LOCK_EX);
    
    // Also write to WordPress debug log if enabled
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[' . RISEUP_PLUGIN_SLUG . '] ' . $message);
    }
    
    return true;
}
```

## Debugging Tips

### 1. Enable Verbose Logging During Development

```php
define('RISEUP_DEBUG', true);

public function debug($message, $file = '', $line = 0) {
    if (defined('RISEUP_DEBUG') && RISEUP_DEBUG) {
        $this->log('[DEBUG] ' . $message, $file, $line);
    }
}
```

### 2. Log Stack Traces for Errors

```php
public function error($message, $file = '', $line = 0, $exception = null) {
    $full_message = '[ERROR] ' . $message;
    
    if ($exception instanceof Exception) {
        $full_message .= "\nStack trace:\n" . $exception->getTraceAsString();
    }
    
    $this->ensure_paths();
    $entry = $this->format_entry($full_message, $file, $line);
    
    @file_put_contents($this->log_path, $entry, FILE_APPEND | LOCK_EX);
    @file_put_contents($this->error_path, $entry, FILE_APPEND | LOCK_EX);
}
```

### 3. Log Request Context

```php
public function log_request() {
    $context = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
    ];
    
    $this->log(
        sprintf('Request: %s %s from %s', $context['method'], $context['uri'], $context['ip']),
        __FILE__,
        __LINE__
    );
}
```
