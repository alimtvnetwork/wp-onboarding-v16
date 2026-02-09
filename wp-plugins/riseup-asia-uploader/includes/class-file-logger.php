<?php
/**
 * Riseup Asia Uploader - File Logger
 *
 * Logs all operations to file with file path and line numbers.
 * Logs are stored in wp-content/uploads/riseup-asia-uploader/logs/
 *
 * Implements MD5-based deduplication: identical log entries (same level+message+file+line)
 * are written once and subsequent occurrences are silently suppressed until the hash map
 * is cleared (on next request lifecycle or explicit reset).
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Riseup_File_Logger
 *
 * Provides file-based logging with detailed context and MD5 deduplication.
 */
class Riseup_File_Logger {

    /**
     * Singleton instance.
     *
     * @var Riseup_File_Logger|null
     */
    private static $instance = null;

    /**
     * Base directory for plugin data.
     *
     * @var string|null
     */
    private $base_dir = null;

    /**
     * Logs directory.
     *
     * @var string|null
     */
    private $logs_dir = null;

    /**
     * Path to general log file.
     *
     * @var string|null
     */
    private $log_file = null;

    /**
     * Path to error log file.
     *
     * @var string|null
     */
    private $error_file = null;

    /**
     * Path to stack trace file.
     *
     * @var string|null
     */
    private $stacktrace_file = null;

    /**
     * Whether the logger is initialized.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * MD5 deduplication map.
     * Keys are MD5 hex hashes of (level + message + file + line).
     * Values are true. Entries logged once are suppressed on repeat.
     *
     * @var array<string, bool>
     */
    private $dedup_hashes = array();

    /**
     * Cached request metadata for the current lifecycle.
     *
     * @var array|null
     */
    private $request_metadata_cache = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_File_Logger
     */
    public static function get_instance() {
        if (RiseupBooleanHelpers::is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     * NOTE: We do NOT call wp_upload_dir() here to avoid early WordPress function calls.
     */
    private function __construct() {
        // Paths will be initialized lazily on first use
    }

    /**
     * Initialize log file paths (lazy initialization).
     * This is called on first log attempt to ensure WordPress is fully loaded.
     *
     * @return bool True if successful.
     */
    private function initialize_paths() {
        if ($this->initialized) {
            return true;
        }

        // Resolve base directory via centralized helper
        $this->base_dir = RiseupInitHelpers::resolveBaseDir();
        
        $this->logs_dir        = RiseupPathUtils::getLogsDir();
        $this->log_file        = $this->logs_dir . '/' . RISEUP_LOG_FILENAME;
        $this->error_file      = $this->logs_dir . '/' . RISEUP_ERROR_LOG_FILENAME;
        $this->stacktrace_file = $this->logs_dir . '/' . RISEUP_STACKTRACE_FILENAME;
        
        // Create directories via idempotent helper
        return $this->ensure_directories();
    }

    /**
     * Ensure log directories exist.
     *
     * @return bool True if successful.
     */
    private function ensure_directories() {
        // CRITICAL: Use native PHP directory creation to avoid circular dependency.
        // RiseupInitHelpers::ensureDir() delegates to RiseupPathUtils::ensureDir()
        // which calls getLogger() which tries to create *this* logger instance → infinite loop.
        // ensureDirNative() uses only raw PHP (mkdir / wp_mkdir_p), no logger involved.
        if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDirNative($this->base_dir, true))) {
            error_log('[Riseup Asia] Failed to create base directory: ' . $this->base_dir);
            return false;
        }

        if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDirNative($this->logs_dir, false))) {
            error_log('[Riseup Asia] Failed to create logs directory: ' . $this->logs_dir);
            return false;
        }
        
        $this->initialized = true;
        return true;
    }

    /**
     * Check if a log entry is a duplicate using MD5 hashing.
     *
     * Generates an MD5 hash from (level + message + file + line) and checks if it
     * has already been logged in this request lifecycle. If so, returns true (duplicate).
     * Otherwise, registers the hash and returns false (new entry).
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line number.
     *
     * @return bool True if this is a duplicate entry that should be skipped.
     */
    private function is_duplicate($level, $message, $file, $line) {
        $hash_input = $level . '|' . $message . '|' . basename($file) . '|' . $line;
        $hash = md5($hash_input);

        if (isset($this->dedup_hashes[$hash])) {
            return true;
        }

        $this->dedup_hashes[$hash] = true;
        return false;
    }

    /**
     * Gather HTTP request metadata (method, endpoint, user-agent, IP).
     * Cached per request lifecycle to avoid repeated $_SERVER reads.
     *
     * @return array Associative array with request metadata keys.
     */
    private function get_request_metadata() {
        if ($this->request_metadata_cache !== null) {
            return $this->request_metadata_cache;
        }

        $meta = array();

        if (php_sapi_name() === 'cli') {
            $meta['_request'] = array(
                'method'  => 'CLI',
                'script'  => isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : 'unknown',
            );
            $this->request_metadata_cache = $meta;
            return $meta;
        }

        $method    = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
        $uri       = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
        $query     = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
        $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip        = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        $meta['_request'] = array(
            'method'    => $method,
            'endpoint'  => $uri . $query,
            'userAgent' => strlen($useragent) > 200 ? substr($useragent, 0, 200) . '…' : $useragent,
            'ip'        => $ip,
        );

        $this->request_metadata_cache = $meta;
        return $meta;
    }

    /**
     * Merge request metadata into a context array (non-destructive).
     *
     * @param array $context Existing context.
     * @return array Context enriched with request metadata.
     */
    private function enrich_context_with_request($context) {
        $meta = $this->get_request_metadata();
        // Only add if not already present (caller can override)
        if (!isset($context['_request'])) {
            $context = array_merge($meta, $context);
        }
        return $context;
    }


     * Clear the deduplication hash map.
     * After calling this, previously suppressed entries will be logged again.
     *
     * @return int Number of hashes that were cleared.
     */
    public function clear_dedup_hashes() {
        $count = count($this->dedup_hashes);
        $this->dedup_hashes = array();
        return $count;
    }

    /**
     * Get the base directory path.
     *
     * @return string
     */
    public function get_base_dir() {
        if (RiseupBooleanHelpers::is_null($this->base_dir)) {
            $this->initialize_paths();
        }
        return $this->base_dir;
    }

    /**
     * Get the logs directory path.
     *
     * @return string
     */
    public function get_logs_dir() {
        if (RiseupBooleanHelpers::is_null($this->logs_dir)) {
            $this->initialize_paths();
        }
        return $this->logs_dir;
    }

    /**
     * Get the path to the general log file.
     *
     * @return string
     */
    public function get_log_file() {
        if (RiseupBooleanHelpers::is_null($this->log_file)) {
            $this->initialize_paths();
        }
        return $this->log_file;
    }

    /**
     * Get the path to the error log file.
     *
     * @return string
     */
    public function get_error_file() {
        if (RiseupBooleanHelpers::is_null($this->error_file)) {
            $this->initialize_paths();
        }
        return $this->error_file;
    }

    /**
     * Get the path to the stack trace file.
     *
     * @return string
     */
    public function get_stacktrace_file() {
        if (RiseupBooleanHelpers::is_null($this->stacktrace_file)) {
            $this->initialize_paths();
        }
        return $this->stacktrace_file;
    }

    /**
     * Format a log entry.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line number.
     * @param array  $context Additional context.
     *
     * @return string Formatted log entry.
     */
    private function format_entry($level, $message, $file, $line, $context = array()) {
        // Use date() instead of gmdate() with .v for PHP 7.4 compatibility
        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
        $basename  = basename($file);
        
        $entry = sprintf(
            "[%s] [%s] %s (%s:%d)",
            $timestamp,
            $level,
            $message,
            $basename,
            $line
        );
        
        if (RiseupBooleanHelpers::has_content($context)) {
            $json_flags = defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0;
            $entry .= ' ' . json_encode($context, $json_flags);
        }
        
        return $entry . PHP_EOL;
    }

    /**
     * Write to log file.
     *
     * @param string $entry      Log entry.
     * @param bool   $is_error   Whether this is an error.
     *
     * @return bool True on success.
     */
    private function write($entry, $is_error = false) {
        // Initialize paths on first write
        if (RiseupBooleanHelpers::is_falsy($this->initialized)) {
            if (!$this->initialize_paths()) {
                // Fallback to error_log if we can't write to file
                error_log('[Riseup Asia] ' . trim($entry));
                return false;
            }
        }
        
        // Always write to main log
        $result = @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
        
        // Also write errors to error log
        if ($is_error) {
            @file_put_contents($this->error_file, $entry, FILE_APPEND | LOCK_EX);
        }
        
        return $result !== false;
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return bool
     */
    public function debug($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->is_duplicate(RISEUP_LOG_LEVEL_DEBUG, $message, $file, $line)) {
            return true; // Silently skip duplicate
        }
        
        $entry = $this->format_entry(RISEUP_LOG_LEVEL_DEBUG, $message, $file, $line, $context);
        return $this->write($entry, false);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return bool
     */
    public function info($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->is_duplicate(RISEUP_LOG_LEVEL_INFO, $message, $file, $line)) {
            return true;
        }
        
        $entry = $this->format_entry(RISEUP_LOG_LEVEL_INFO, $message, $file, $line, $context);
        return $this->write($entry, false);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return bool
     */
    public function warn($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->is_duplicate(RISEUP_LOG_LEVEL_WARN, $message, $file, $line)) {
            return true;
        }

        // Auto-inject request metadata
        $context = $this->enrich_context_with_request($context);

        // Enrich context with invocation chain for diagnostics
        if (!isset($context['_invocation_chain'])) {
            $chain = array();
            foreach ($trace as $i => $frame) {
                if ($i === 0) continue; // skip self
                $entry = array();
                if (isset($frame['class'])) {
                    $entry['class'] = $frame['class'];
                }
                if (isset($frame['function'])) {
                    $entry['function'] = $frame['function'];
                }
                if (isset($frame['file'])) {
                    $entry['file'] = basename($frame['file']);
                    $entry['line'] = isset($frame['line']) ? $frame['line'] : 0;
                }
                if (!empty($entry)) {
                    $chain[] = $entry;
                }
            }
            if (!empty($chain)) {
                $context['_invocation_chain'] = $chain;
            }
        }

        // Capture abbreviated stack trace for warn-level too
        $formatted_trace = $this->format_backtrace($trace);

        $entry = $this->format_entry(RISEUP_LOG_LEVEL_WARN, $message, $file, $line, $context);
        $this->persist_to_error_sessions(RISEUP_LOG_LEVEL_WARN, $message, $file, $line, $context, $formatted_trace);
        return $this->write($entry, false);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return bool
     */
    public function error($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0); // 0 = unlimited depth
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->is_duplicate(RISEUP_LOG_LEVEL_ERROR, $message, $file, $line)) {
            return true;
        }

        // Auto-inject request metadata
        $context = $this->enrich_context_with_request($context);
        
        $entry = $this->format_entry(RISEUP_LOG_LEVEL_ERROR, $message, $file, $line, $context);
        $formatted_trace = $this->format_backtrace($trace);
        $this->persist_to_error_sessions(RISEUP_LOG_LEVEL_ERROR, $message, $file, $line, $context, $formatted_trace);
        $this->write_stacktrace($message, $file, $line, $formatted_trace);
        return $this->write($entry, true);
    }

    /**
     * Log with explicit file and line.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line.
     * @param array  $context Additional context.
     *
     * @return bool
     */
    public function log_at($level, $message, $file, $line, $context = array()) {
        if ($this->is_duplicate($level, $message, $file, $line)) {
            return true;
        }

        $is_error = ($level === RISEUP_LOG_LEVEL_ERROR);
        $entry = $this->format_entry($level, $message, $file, $line, $context);
        return $this->write($entry, $is_error);
    }

    /**
     * Log an exception.
     *
     * @param Exception|Throwable $e       Exception to log.
     * @param string              $context Additional context message.
     *
     * @return bool
     */
    public function log_exception($e, $context = '') {
        if ($this->is_duplicate(RISEUP_LOG_LEVEL_ERROR, $e->getMessage(), $e->getFile(), $e->getLine())) {
            return true;
        }

        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $ctx = $this->enrich_context_with_request(array('trace' => $e->getTraceAsString()));
        $entry = $this->format_entry(
            RISEUP_LOG_LEVEL_ERROR,
            $message,
            $e->getFile(),
            $e->getLine(),
            $ctx
        );
        $this->persist_to_error_sessions(RISEUP_LOG_LEVEL_ERROR, $message, $e->getFile(), $e->getLine(), array(), $e->getTraceAsString());
        $this->write_stacktrace($message, $e->getFile(), $e->getLine(), $e->getTraceAsString());
        return $this->write($entry, true);
    }

    /**
     * Persist an error/warn entry to the error_sessions SQLite table
     * and mark flash state as having unseen errors.
     *
     * Uses defensive coding: if the DB is unavailable (e.g., during early
     * bootstrap), the error is silently skipped (already written to file).
     *
     * @param string $level       Log level (ERROR or WARN).
     * @param string $message     Error message.
     * @param string $file        Source file path.
     * @param int    $line        Source line number.
     * @param array  $context     Additional context data.
     * @param string $stack_trace Optional stack trace string.
     */
    private function persist_to_error_sessions($level, $message, $file, $line, $context = array(), $stack_trace = '') {
        try {
            // Guard: Riseup_Database may not be loaded yet during early bootstrap
            if (!class_exists('Riseup_Database', false)) {
                return;
            }

            $db = Riseup_Database::get_instance();
            $pdo = $db->get_pdo();
            if (!$pdo) {
                return;
            }

            // Check if error_sessions table exists (migration may not have run yet)
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
            if (!$check->fetchColumn()) {
                return;
            }

            $now = gmdate('Y-m-d\TH:i:s\Z');
            $context_json = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;

            $stmt = $pdo->prepare(
                'INSERT INTO error_sessions (level, message, file, line, context_json, stack_trace, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array($level, $message, $file, $line, $context_json, $stack_trace ?: null, $now));

            // Update flash state: mark as having unseen errors
            $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '1', '{$now}')");
        } catch (Throwable $e) {
            // Silently ignore - we're in the logger, can't recurse
            // The error is already written to the file log
        }
    }

    /**
     * Write a stack trace entry to the dedicated stacktrace.txt file.
     *
     * @param string $message     Error message.
     * @param string $file        Source file.
     * @param int    $line        Source line number.
     * @param string $stack_trace Stack trace string.
     */
    private function write_stacktrace($message, $file, $line, $stack_trace) {
        if (empty($stack_trace)) {
            return;
        }

        if (RiseupBooleanHelpers::is_falsy($this->initialized)) {
            if (!$this->initialize_paths()) {
                return;
            }
        }

        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
        $separator = str_repeat('=', 80);
        $entry  = $separator . PHP_EOL;
        $entry .= sprintf("[%s] %s (%s:%d)", $timestamp, $message, basename($file), $line) . PHP_EOL;
        $entry .= str_repeat('-', 80) . PHP_EOL;
        $entry .= $stack_trace . PHP_EOL;
        $entry .= $separator . PHP_EOL . PHP_EOL;

        @file_put_contents($this->stacktrace_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format a debug_backtrace array into a readable string.
     *
     * @param array $trace debug_backtrace result.
     * @return string Formatted stack trace.
     */
    private function format_backtrace($trace) {
        $lines = array();
        foreach ($trace as $i => $frame) {
            $file = isset($frame['file']) ? basename($frame['file']) : '<internal>';
            $line = isset($frame['line']) ? $frame['line'] : 0;
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $func = isset($frame['function']) ? $frame['function'] : '<unknown>';
            $lines[] = sprintf('#%d %s(%d): %s%s()', $i, $file, $line, $class, $func);
        }
        return implode(PHP_EOL, $lines);
    }
}
