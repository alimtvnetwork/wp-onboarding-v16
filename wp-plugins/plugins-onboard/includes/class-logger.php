<?php
/**
 * Logging class for debugging and error tracking.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardLogger
 *
 * Centralized logging system with debug and error logs.
 */
class OnboardLogger {

    /**
     * Log levels.
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Log to debug file.
     *
     * @param string $message Log message.
     * @param string $level Log level.
     * @param array $context Additional context.
     */
    public static function debug($message, $level = self::LEVEL_DEBUG, $context = array()) {
        // Check if debug logging is enabled.
        $enabled = defined('ONBOARD_DEBUG_LOGGING') ? ONBOARD_DEBUG_LOGGING : false;

        if (!$enabled) {
            return;
        }

        self::write_log('debug.log', $message, $level, $context);
    }

    /**
     * Log error to error file.
     *
     * @param string $message Error message.
     * @param Exception|null $exception Exception object.
     * @param array $context Additional context.
     */
    public static function error($message, $exception = null, $context = array()) {
        $level = self::LEVEL_ERROR;

        if ($exception) {
            $context['exception'] = array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            );
        }

        self::write_log('error.log', $message, $level, $context);
    }

    /**
     * Log critical error.
     *
     * @param string $message Error message.
     * @param Exception|null $exception Exception object.
     * @param array $context Additional context.
     */
    public static function critical($message, $exception = null, $context = array()) {
        $level = self::LEVEL_CRITICAL;

        if ($exception) {
            $context['exception'] = array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            );
        }

        self::write_log('error.log', $message, $level, $context);
    }

    /**
     * Write log to file.
     *
     * @param string $filename Log filename.
     * @param string $message Log message.
     * @param string $level Log level.
     * @param array $context Additional context.
     */
    private static function write_log($filename, $message, $level, $context = array()) {
        try {
            $log_dir = self::get_log_directory();

            if (empty($log_dir)) {
                return;
            }

            // Ensure log directory exists.
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }

            $log_file = $log_dir . $filename;

            // Format log entry.
            $timestamp = date('Y-m-d H:i:s');
            $memory = self::format_bytes(memory_get_usage(true));

            $log_entry = sprintf(
                "[%s] [%s] [Memory: %s] %s",
                $timestamp,
                $level,
                $memory,
                $message
            );

            // Add context if present.
            if (!empty($context)) {
                $log_entry .= "\nContext: " . print_r($context, true);
            }

            $log_entry .= "\n" . str_repeat('-', 80) . "\n";

            // Write to file.
            @error_log($log_entry, 3, $log_file);

        } catch (Throwable $e) {
            // Silently fail if logging fails.
        }
    }

    /**
     * Get log directory path.
     *
     * @return string Log directory path.
     */
    private static function get_log_directory() {
        if (class_exists('OnboardPaths')) {
            return OnboardPaths::get(OnboardPaths::DIR_SECURITY_LOGS);
        }

        // Fallback to wp-content/uploads if OnboardPaths is not available yet.
        if (defined('WP_CONTENT_DIR')) {
            return WP_CONTENT_DIR . '/uploads/plugins-onboard/logs/';
        }

        if (defined('ABSPATH')) {
            return ABSPATH . 'wp-content/uploads/plugins-onboard/logs/';
        }

        return '';
    }

    /**
     * Format bytes to human readable.
     *
     * @param int $bytes Bytes.
     * @return string Formatted string.
     */
    private static function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Clear debug log.
     */
    public static function clear_debug_log() {
        $log_dir = self::get_log_directory();
        if (!empty($log_dir)) {
            $debug_log = $log_dir . 'debug.log';
            if (file_exists($debug_log)) {
                @unlink($debug_log);
            }
        }
    }

    /**
     * Clear error log.
     */
    public static function clear_error_log() {
        $log_dir = self::get_log_directory();
        if (!empty($log_dir)) {
            $error_log = $log_dir . 'error.log';
            if (file_exists($error_log)) {
                @unlink($error_log);
            }
        }
    }

    /**
     * Get log file contents.
     *
     * @param string $filename Log filename.
     * @param int $lines Number of lines to read from end.
     * @return string Log contents.
     */
    public static function get_log_contents($filename, $lines = 100) {
        $log_dir = self::get_log_directory();
        if (empty($log_dir)) {
            return '';
        }

        $log_file = $log_dir . $filename;
        if (!file_exists($log_file)) {
            return '';
        }

        $file = @file($log_file);
        if ($file === false) {
            return '';
        }

        $file = array_slice($file, -$lines);
        return implode('', $file);
    }
}
