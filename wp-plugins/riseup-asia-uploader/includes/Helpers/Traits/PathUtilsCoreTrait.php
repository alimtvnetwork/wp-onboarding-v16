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
use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathDatabaseType;

trait PathUtilsCoreTrait {

    /** @var RiseupFileLogger|null */
    private static $logger = null;

    /** @var bool */
    private static $isBootstrapping = false;

    /**
     * Get logger instance (null-safe, re-entrancy guard).
     *
     * @return RiseupFileLogger|null
     */
    private static function getLogger() {
        if (self::$isBootstrapping) {
            return null;
        }

        if (self::$logger !== null) {
            return self::$logger;
        }

        if (RiseupBooleanHelpers::isClassNotLoaded('RiseupFileLogger')) {
            return null;
        }

        return self::initializeLogger();
    }

    /** Initialize the logger with re-entrancy guard. */
    private static function initializeLogger(): ?RiseupFileLogger {
        self::$isBootstrapping = true;
        try {
            self::$logger = RiseupFileLogger::getInstance();
        } catch (Throwable $e) {
            error_log('[Riseup Asia] [ERROR] Logger init failed: ' . $e->getMessage());
            self::$logger = null;
        }

        self::$isBootstrapping = false;

        return self::$logger;
    }

    /**
     * Log a message safely — falls back to error_log() when logger is unavailable.
     *
     * @param string $level   Log level (e.g. LogLevelType::Info->value).
     * @param string $message Log message.
     * @param array  $context Optional context.
     */
    private static function safeLog($level, $message, $context = array()) {
        $upper = strtoupper($level);
        $method = strtolower($level);

        if (self::$isBootstrapping) {
            error_log('[Riseup Asia] [' . $upper . '] ' . $message);
            return;
        }

        $logger = self::getLogger();
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
    public static function getBaseDir() {
        $uploadDir = wp_upload_dir();
        return self::join($uploadDir['basedir'], UPLOADS_SUBDIR);
    }

    /** @return string Full path to logs directory. */
    public static function getLogsDir() {
        return self::join(self::getBaseDir(), PathSubdirType::Logs->value);
    }

    /** @return string Full path to snapshots directory. */
    public static function getSnapshotsDir() {
        return self::join(self::getBaseDir(), PathSubdirType::Snapshots->value);
    }

    /** @return string Full path to temp directory. */
    public static function getTempDir() {
        return self::join(self::getBaseDir(), PathSubdirType::Temp->value);
    }

    /** @return string Full path to SQLite database file. */
    public static function getDbPath() {
        return self::join(self::getBaseDir(), PathDatabaseType::Plugin->value);
    }
}
