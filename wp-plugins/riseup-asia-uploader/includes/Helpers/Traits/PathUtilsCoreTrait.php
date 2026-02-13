<?php
/**
 * PathUtilsCoreTrait — core path operations: join, logging, directory getters.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait PathUtilsCoreTrait {

    /** @var RiseupFileLogger|null */
    private static $logger = null;

    /** @var bool */
    private static $bootstrapping = false;

    /**
     * Get logger instance (null-safe, re-entrancy guard).
     *
     * @return RiseupFileLogger|null
     */
    private static function get_logger() {
        if (self::$bootstrapping) {
            return null;
        }

        if (self::$logger !== null) {
            return self::$logger;
        }

        if (RiseupBooleanHelpers::is_class_not_loaded('RiseupFileLogger')) {
            return null;
        }

        return self::initializeLogger();
    }

    /** Initialize the logger with re-entrancy guard. */
    private static function initializeLogger(): ?RiseupFileLogger {
        self::$bootstrapping = true;
        try {
            self::$logger = RiseupFileLogger::get_instance();
        } catch (\Throwable $e) {
            error_log('[Riseup Asia] [ERROR] Logger init failed: ' . $e->getMessage());
            self::$logger = null;
        }

        self::$bootstrapping = false;

        return self::$logger;
    }

    /**
     * Log a message safely — falls back to error_log() when logger is unavailable.
     *
     * @param string $level   Log level (e.g. LogLevelType::Info->value).
     * @param string $message Log message.
     * @param array  $context Optional context.
     */
    private static function safe_log($level, $message, $context = array()) {
        $upper = strtoupper($level);
        $method = strtolower($level);

        if (self::$bootstrapping) {
            error_log('[Riseup Asia] [' . $upper . '] ' . $message);
            return;
        }

        $logger = self::get_logger();
        if ($logger !== null) {
            $logger->$method($message, $context);
        } else {
            error_log('[Riseup Asia] [' . $upper . '] ' . $message);
        }
    }

    /**
     * Join path segments safely.
     *
     * @param string ...$segments Path segments to join.
     * @return string Joined path with forward slashes.
     */
    public static function join(...$segments) {
        $filtered = array_filter($segments, function($seg) { return $seg !== null && $seg !== ''; });
        if (empty($filtered)) {
            return '';
        }

        $path = implode('/', $filtered);
        $path = str_replace('\\\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#^([a-zA-Z]):#', '$1:', $path);
        return $path;
    }

    /** @return string Base path (wp-content/uploads/riseup-asia-uploader). */
    public static function get_base_dir() {
        $upload_dir = wp_upload_dir();
        return self::join($upload_dir['basedir'], UPLOADS_SUBDIR);
    }

    /** @return string Full path to logs directory. */
    public static function get_logs_dir() {
        return self::join(self::get_base_dir(), LOGS_SUBDIR);
    }

    /** @return string Full path to snapshots directory. */
    public static function get_snapshots_dir() {
        return self::join(self::get_base_dir(), SNAPSHOTS_SUBDIR);
    }

    /** @return string Full path to temp directory. */
    public static function get_temp_dir() {
        return self::join(self::get_base_dir(), TEMP_SUBDIR);
    }

    /** @return string Full path to SQLite database file. */
    public static function get_db_path() {
        return self::join(self::get_base_dir(), DB_FILENAME);
    }
}
