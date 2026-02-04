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
     * @var string
     */
    private $base_dir;

    /**
     * Logs directory.
     *
     * @var string
     */
    private $logs_dir;

    /**
     * Path to general log file.
     *
     * @var string
     */
    private $log_file;

    /**
     * Path to error log file.
     *
     * @var string
     */
    private $error_file;

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
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->initialize_paths();
    }

    /**
     * Initialize log file paths.
     *
     * @return void
     */
    private function initialize_paths() {
        // Get WordPress uploads directory
        $upload_dir = wp_upload_dir();
        
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            // Fallback to plugin directory if uploads not available
            $this->base_dir = dirname(__DIR__) . '/data';
        } else {
            $this->base_dir = $upload_dir['basedir'] . '/' . RISEUP_UPLOADS_SUBDIR;
        }
        
        $this->logs_dir   = $this->base_dir . '/' . RISEUP_LOGS_SUBDIR;
        $this->log_file   = $this->logs_dir . '/' . RISEUP_LOG_FILENAME;
        $this->error_file = $this->logs_dir . '/' . RISEUP_ERROR_LOG_FILENAME;
        
        // Create directories
        $this->ensure_directories();
    }

    /**
     * Ensure log directories exist.
     *
     * @return bool True if successful.
     */
    private function ensure_directories() {
        try {
            if (!file_exists($this->base_dir)) {
                if (!wp_mkdir_p($this->base_dir)) {
                    return false;
                }
                // Protect with .htaccess
                $htaccess = $this->base_dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    @file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
                }
                // Add index.php for extra protection
                $index = $this->base_dir . '/index.php';
                if (!file_exists($index)) {
                    @file_put_contents($index, '<?php // Silence is golden.');
                }
            }
            
            if (!file_exists($this->logs_dir)) {
                if (!wp_mkdir_p($this->logs_dir)) {
                    return false;
                }
            }
            
            $this->initialized = true;
            return true;
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to create log directories: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the base directory path.
     *
     * @return string
     */
    public function get_base_dir() {
        return $this->base_dir;
    }

    /**
     * Get the logs directory path.
     *
     * @return string
     */
    public function get_logs_dir() {
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
        $timestamp = gmdate('Y-m-d\TH:i:s.v\Z');
        $basename  = basename($file);
        
        $entry = sprintf(
            "[%s] [%s] %s (%s:%d)",
            $timestamp,
            $level,
            $message,
            $basename,
            $line
        );
        
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
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
        if (!$this->initialized) {
            $this->ensure_directories();
        }
        
        try {
            // Always write to main log
            @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
            
            // Also write errors to error log
            if ($is_error) {
                @file_put_contents($this->error_file, $entry, FILE_APPEND | LOCK_EX);
            }
            
            return true;
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to write log: ' . $e->getMessage());
            return false;
        }
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
     * @param Exception $e       Exception to log.
     * @param string    $context Additional context message.
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
