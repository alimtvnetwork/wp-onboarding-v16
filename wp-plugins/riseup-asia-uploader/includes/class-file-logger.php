<?php
/**
 * Riseup Asia Uploader - File Logger
 *
 * Logs all operations to file with file path and line numbers.
 * Logs are stored in wp-content/uploads/riseup-asia-uploader/logs/
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
 * Provides file-based logging with detailed context.
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
     * Whether the logger is initialized.
     *
     * @var bool
     */
    private $initialized = false;

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
        
        $this->logs_dir   = RiseupPathUtils::getLogsDir();
        $this->log_file   = $this->logs_dir . '/' . RISEUP_LOG_FILENAME;
        $this->error_file = $this->logs_dir . '/' . RISEUP_ERROR_LOG_FILENAME;
        
        // Create directories via idempotent helper
        return $this->ensure_directories();
    }

    /**
     * Ensure log directories exist.
     *
     * @return bool True if successful.
     */
    private function ensure_directories() {
        // Use idempotent init helpers for directory creation + security
        if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDir($this->base_dir, true))) {
            error_log('[Riseup Asia] Failed to create base directory: ' . $this->base_dir);
            return false;
        }

        if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDir($this->logs_dir, false))) {
            error_log('[Riseup Asia] Failed to create logs directory: ' . $this->logs_dir);
            return false;
        }
        
        $this->initialized = true;
        return true;
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;
        
        $entry = $this->format_entry(RISEUP_LOG_LEVEL_WARN, $message, $file, $line, $context);
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;
        
        $entry = $this->format_entry(RISEUP_LOG_LEVEL_ERROR, $message, $file, $line, $context);
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
        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $entry = $this->format_entry(
            RISEUP_LOG_LEVEL_ERROR,
            $message,
            $e->getFile(),
            $e->getLine(),
            array('trace' => $e->getTraceAsString())
        );
        return $this->write($entry, true);
    }
}
