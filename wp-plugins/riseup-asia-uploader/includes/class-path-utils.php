<?php
/**
 * Riseup Asia Uploader - Path Utilities
 *
 * Centralized path handling with validation, creation, and security.
 * All path operations in the plugin should go through this class.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Path utility class for safe path operations.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupPathUtils {

    /**
     * Logger instance (lazy loaded).
     *
     * @var Riseup_File_Logger|null
     */
    private static $logger = null;

    /**
     * Re-entrancy guard to prevent circular dependency during logger init.
     * When true, ALL logging in this class is suppressed to avoid the
     * RiseupPathUtils → RiseupInitHelpers → Riseup_File_Logger → RiseupPathUtils loop.
     *
     * @var bool
     */
    private static $bootstrapping = false;

    /**
     * Get logger instance (null-safe).
     *
     * Returns null if:
     *  - We are inside a bootstrapping phase (re-entrancy guard)
     *  - The Riseup_File_Logger class is not yet loaded
     *
     * @return Riseup_File_Logger|null
     */
    private static function getLogger() {
        // CRITICAL: Prevent circular dependency. During bootstrapping,
        // return null so safeLog() falls back to error_log().
        if (self::$bootstrapping) {
            return null;
        }

        if (self::$logger === null) {
            // Use native class_exists — RiseupBooleanHelpers may not be loaded yet
            // during very early initialization.
            if (!class_exists('Riseup_File_Logger', false)) {
                return null;
            }

            self::$bootstrapping = true;
            try {
                self::$logger = Riseup_File_Logger::get_instance();
            } catch (\Throwable $e) {
                // Logger init failed — stay in fallback mode
                error_log('[Riseup Asia] [ERROR] Logger init failed: ' . $e->getMessage());
                self::$logger = null;
            }
            self::$bootstrapping = false;
        }
        return self::$logger;
    }

    /**
     * Log a message safely — falls back to error_log() when logger is unavailable.
     *
     * @param string $level   One of 'debug', 'info', 'warn', 'error'.
     * @param string $message Log message.
     * @param array  $context Optional context.
     * @return void
     */
    private static function safeLog($level, $message, $context = array()) {
        // During bootstrapping, always use native error_log to avoid circular calls
        if (self::$bootstrapping) {
            error_log('[Riseup Asia] [' . strtoupper($level) . '] ' . $message);
            return;
        }

        $logger = self::getLogger();
        if ($logger !== null) {
            $logger->$level($message, $context);
        } else {
            error_log('[Riseup Asia] [' . strtoupper($level) . '] ' . $message);
        }
    }

    /**
     * Join path segments safely.
     *
     * Normalizes separators to forward slashes and removes double slashes.
     *
     * @param string ...$segments Path segments to join.
     * @return string Joined path with forward slashes.
     */
    public static function join(...$segments) {
        $filtered = array_filter($segments, function($seg) {
            return RiseupBooleanHelpers::is_set($seg) && $seg !== '';
        });

        if (empty($filtered)) {
            return '';
        }

        // Join with forward slash
        $path = implode('/', $filtered);

        // Normalize: replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove duplicate slashes (but preserve leading // for network paths)
        $path = preg_replace('#/+#', '/', $path);

        // Restore Windows drive letter format if needed
        $path = preg_replace('#^([a-zA-Z]):#', '$1:', $path);

        return $path;
    }

    /**
     * Get the plugin's base uploads directory.
     *
     * @return string Base path (wp-content/uploads/riseup-asia-uploader).
     */
    public static function getBaseDir() {
        $upload_dir = wp_upload_dir();
        return self::join($upload_dir['basedir'], RISEUP_UPLOADS_SUBDIR);
    }

    /**
     * Get the logs directory path.
     *
     * @return string Full path to logs directory.
     */
    public static function getLogsDir() {
        return self::join(self::getBaseDir(), RISEUP_LOGS_SUBDIR);
    }

    /**
     * Get the snapshots directory path.
     *
     * @return string Full path to snapshots directory.
     */
    public static function getSnapshotsDir() {
        return self::join(self::getBaseDir(), RISEUP_SNAPSHOTS_SUBDIR);
    }

    /**
     * Get the temp directory path.
     *
     * @return string Full path to temp directory.
     */
    public static function getTempDir() {
        return self::join(self::getBaseDir(), RISEUP_TEMP_SUBDIR);
    }

    /**
     * Get the database file path.
     *
     * @return string Full path to SQLite database file.
     */
    public static function getDbPath() {
        return self::join(self::getBaseDir(), RISEUP_DB_FILENAME);
    }

    /**
     * Check if a directory is missing (cannot be ensured).
     *
     * Attempts to create the directory (with optional security files).
     * Returns true when the directory does NOT exist after the attempt,
     * i.e. creation failed. This is the semantic inverse of ensureDir().
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory is MISSING (creation failed).
     */
    public static function isDirMissing($path, $secure = false) {
        return !self::ensureDir($path, $secure);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensureDir($path, $secure = false) {
        // Normalize the path
        $path = self::join($path);

        if (empty($path)) {
            self::safeLog('error', '[PATH] Empty path provided to ensureDir');
            return false;
        }

        // Check if already exists
        if (is_dir($path)) {
            self::safeLog('debug', '[PATH] Directory already exists', array('path' => $path));

            // Add security files if requested and missing
            if ($secure) {
                self::addSecurityFiles($path);
            }

            return true;
        }

        // Attempt to create
        self::safeLog('info', '[PATH] Creating directory', array('path' => $path, 'secure' => $secure));

        // Use wp_mkdir_p for WordPress compatibility
        if (RiseupBooleanHelpers::is_falsy(wp_mkdir_p($path))) {
            $error = error_get_last();
            self::safeLog('error', '[PATH] Directory creation failed', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
                'parent_exists' => is_dir(dirname($path)),
                'parent_writable' => is_writable(dirname($path)),
            ));
            return false;
        }

        self::safeLog('info', '[PATH] Directory created successfully', array('path' => $path));

        // Add security files if requested
        if ($secure) {
            self::addSecurityFiles($path);
        }

        return true;
    }

    /**
     * Add security files to a directory.
     *
     * Creates .htaccess and index.php to prevent direct access.
     *
     * @param string $path Directory path.
     * @return bool True if files were created successfully.
     */
    public static function addSecurityFiles($path) {
        $success = true;

        // .htaccess
        $htaccess_path = self::join($path, '.htaccess');
        if (RiseupBooleanHelpers::is_file_missing($htaccess_path)) {
            $htaccess_content = "# Riseup Asia Uploader - Security\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";

            if (@file_put_contents($htaccess_path, $htaccess_content) === false) {
                self::safeLog('warn', '[PATH] Failed to create .htaccess', array('path' => $htaccess_path));
                $success = false;
            } else {
                self::safeLog('debug', '[PATH] Created .htaccess', array('path' => $htaccess_path));
            }
        }

        // index.php
        $index_path = self::join($path, 'index.php');
        if (RiseupBooleanHelpers::is_file_missing($index_path)) {
            $index_content = "<?php\n// Silence is golden.\n";

            if (@file_put_contents($index_path, $index_content) === false) {
                self::safeLog('warn', '[PATH] Failed to create index.php', array('path' => $index_path));
                $success = false;
            } else {
                self::safeLog('debug', '[PATH] Created index.php', array('path' => $index_path));
            }
        }

        return $success;
    }

    /**
     * Join path segments and ensure the directory exists.
     *
     * Combines join() and ensureDir() in one convenient call.
     *
     * @param bool   $secure    Add security files (.htaccess, index.php).
     * @param string ...$segments Path segments to join.
     * @return string|false Full path if successful, false on failure.
     */
    public static function ensurePath($secure, ...$segments) {
        $path = self::join(...$segments);

        if (empty($path)) {
            self::safeLog('error', '[PATH] Empty path from segments', array(
                'segments' => $segments,
            ));
            return false;
        }

        if (self::isDirMissing($path, $secure)) {
            return false;
        }

        return $path;
    }

    /**
     * Validate that a path is within allowed boundaries.
     *
     * Prevents path traversal attacks by ensuring the resolved path
     * starts with the expected base path.
     *
     * @param string $path      Path to validate.
     * @param string $base_path Allowed base path.
     * @return bool True if path is safe.
     */
    public static function isSafePath($path, $base_path) {
        // Resolve real paths
        $real_base = realpath($base_path);
        if ($real_base === false) {
            self::safeLog('warn', '[PATH] Base path does not exist', array('base' => $base_path));
            return false;
        }

        // For the target path, it might not exist yet
        $real_path = realpath($path);
        if ($real_path === false) {
            // Check the parent directory instead
            $parent = dirname($path);
            $real_parent = realpath($parent);

            if ($real_parent === false) {
                self::safeLog('warn', '[PATH] Neither path nor parent exists', array(
                    'path' => $path,
                    'parent' => $parent,
                ));
                return false;
            }

            // Construct the expected real path
            $real_path = self::join($real_parent, basename($path));
        }

        // Normalize for comparison
        $real_base = str_replace('\\', '/', $real_base);
        $real_path = str_replace('\\', '/', $real_path);

        // Check if path starts with base
        $is_safe = strpos($real_path, $real_base) === 0;

        if (RiseupBooleanHelpers::is_falsy($is_safe)) {
            self::safeLog('error', '[PATH] Path traversal attempt detected', array(
                'path' => $path,
                'resolved' => $real_path,
                'base' => $real_base,
            ));
        }

        return $is_safe;
    }

    /**
     * Validate a path exists and is a file.
     *
     * @param string $path File path.
     * @return bool True if file exists.
     */
    public static function fileExists($path) {
        $path = self::join($path);
        return RiseupBooleanHelpers::has_content($path) && is_file($path);
    }

    /**
     * Validate a path exists and is a directory.
     *
     * @param string $path Directory path.
     * @return bool True if directory exists.
     */
    public static function dirExists($path) {
        $path = self::join($path);
        return RiseupBooleanHelpers::has_content($path) && is_dir($path);
    }

    /**
     * Check if a path is writable.
     *
     * @param string $path Path to check.
     * @return bool True if writable.
     */
    public static function isWritable($path) {
        $path = self::join($path);
        return RiseupBooleanHelpers::has_content($path) && is_writable($path);
    }

    /**
     * Get path relative to the plugin base directory.
     *
     * @param string $full_path Full path.
     * @return string Relative path.
     */
    public static function getRelativePath($full_path) {
        $base = self::getBaseDir();
        $full_path = str_replace('\\', '/', $full_path);
        $base = str_replace('\\', '/', $base);

        if (strpos($full_path, $base) === 0) {
            return ltrim(substr($full_path, strlen($base)), '/');
        }

        return $full_path;
    }

    /**
     * Delete a file safely.
     *
     * @param string $path File path.
     * @return bool True if deleted or didn't exist.
     */
    public static function deleteFile($path) {
        $path = self::join($path);

        if (empty($path)) {
            self::safeLog('warn', '[PATH] Empty path provided to deleteFile');
            return false;
        }

        if (RiseupBooleanHelpers::is_file_missing($path)) {
            self::safeLog('debug', '[PATH] File does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (RiseupBooleanHelpers::is_falsy(is_file($path))) {
            self::safeLog('error', '[PATH] Path is not a file', array('path' => $path));
            return false;
        }

        if (RiseupBooleanHelpers::is_falsy(@unlink($path))) {
            $error = error_get_last();
            self::safeLog('error', '[PATH] Failed to delete file', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        self::safeLog('debug', '[PATH] File deleted', array('path' => $path));
        return true;
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param string $path Directory path.
     * @return bool True if deleted or didn't exist.
     */
    public static function deleteDir($path) {
        $path = self::join($path);

        if (empty($path)) {
            self::safeLog('warn', '[PATH] Empty path provided to deleteDir');
            return false;
        }

        if (RiseupBooleanHelpers::is_file_missing($path)) {
            self::safeLog('debug', '[PATH] Directory does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (RiseupBooleanHelpers::is_falsy(is_dir($path))) {
            self::safeLog('error', '[PATH] Path is not a directory', array('path' => $path));
            return false;
        }

        // Recursive deletion
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ($files as $file) {
            $file_path = self::join($path, $file);
            if (is_dir($file_path)) {
                if (!self::deleteDir($file_path)) {
                    return false;
                }
            } else {
                if (!self::deleteFile($file_path)) {
                    return false;
                }
            }
        }

        if (RiseupBooleanHelpers::is_falsy(@rmdir($path))) {
            $error = error_get_last();
            self::safeLog('error', '[PATH] Failed to delete directory', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        self::safeLog('debug', '[PATH] Directory deleted', array('path' => $path));
        return true;
    }

    /**
     * Get disk free space for the path's partition.
     *
     * @param string $path Path to check.
     * @return int|false Free space in bytes, or false on error.
     */
    public static function getFreeSpace($path) {
        $path = self::join($path);

        // Find existing directory in path
        while (RiseupBooleanHelpers::is_dir_missing($path) && $path !== dirname($path)) {
            $path = dirname($path);
        }

        if (RiseupBooleanHelpers::is_dir_missing($path)) {
            return false;
        }

        return @disk_free_space($path);
    }

    /**
     * Format bytes to human-readable string.
     *
     * @param int $bytes    Bytes value.
     * @param int $decimals Decimal places.
     * @return string Formatted string (e.g., "15.7 MB").
     */
    public static function formatBytes($bytes, $decimals = 1) {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
