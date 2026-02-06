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
     * @var RiseupFileLogger|null
     */
    private static $logger = null;

    /**
     * Get logger instance.
     *
     * @return RiseupFileLogger
     */
    private static function getLogger() {
        if (self::$logger === null) {
            self::$logger = RiseupFileLogger::getInstance();
        }
        return self::$logger;
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
            return $seg !== null && $seg !== '';
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
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensureDir($path, $secure = false) {
        $logger = self::getLogger();

        // Normalize the path
        $path = self::join($path);

        if (empty($path)) {
            $logger->error('[PATH] Empty path provided to ensureDir');
            return false;
        }

        // Check if already exists
        if (is_dir($path)) {
            $logger->debug('[PATH] Directory already exists', array('path' => $path));

            // Add security files if requested and missing
            if ($secure) {
                self::addSecurityFiles($path);
            }

            return true;
        }

        // Attempt to create
        $logger->info('[PATH] Creating directory', array('path' => $path, 'secure' => $secure));

        // Use wp_mkdir_p for WordPress compatibility
        if (!wp_mkdir_p($path)) {
            $error = error_get_last();
            $logger->error('[PATH] Directory creation failed', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
                'parent_exists' => is_dir(dirname($path)),
                'parent_writable' => is_writable(dirname($path)),
            ));
            return false;
        }

        $logger->info('[PATH] Directory created successfully', array('path' => $path));

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
        $logger = self::getLogger();
        $success = true;

        // .htaccess
        $htaccess_path = self::join($path, '.htaccess');
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "# Riseup Asia Uploader - Security\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";

            if (@file_put_contents($htaccess_path, $htaccess_content) === false) {
                $logger->warn('[PATH] Failed to create .htaccess', array('path' => $htaccess_path));
                $success = false;
            } else {
                $logger->debug('[PATH] Created .htaccess', array('path' => $htaccess_path));
            }
        }

        // index.php
        $index_path = self::join($path, 'index.php');
        if (!file_exists($index_path)) {
            $index_content = "<?php\n// Silence is golden.\n";

            if (@file_put_contents($index_path, $index_content) === false) {
                $logger->warn('[PATH] Failed to create index.php', array('path' => $index_path));
                $success = false;
            } else {
                $logger->debug('[PATH] Created index.php', array('path' => $index_path));
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
            self::getLogger()->error('[PATH] Empty path from segments', array(
                'segments' => $segments,
            ));
            return false;
        }

        if (!self::ensureDir($path, $secure)) {
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
        $logger = self::getLogger();

        // Resolve real paths
        $real_base = realpath($base_path);
        if ($real_base === false) {
            $logger->warn('[PATH] Base path does not exist', array('base' => $base_path));
            return false;
        }

        // For the target path, it might not exist yet
        $real_path = realpath($path);
        if ($real_path === false) {
            // Check the parent directory instead
            $parent = dirname($path);
            $real_parent = realpath($parent);

            if ($real_parent === false) {
                $logger->warn('[PATH] Neither path nor parent exists', array(
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

        if (!$is_safe) {
            $logger->error('[PATH] Path traversal attempt detected', array(
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
        return !empty($path) && is_file($path);
    }

    /**
     * Validate a path exists and is a directory.
     *
     * @param string $path Directory path.
     * @return bool True if directory exists.
     */
    public static function dirExists($path) {
        $path = self::join($path);
        return !empty($path) && is_dir($path);
    }

    /**
     * Check if a path is writable.
     *
     * @param string $path Path to check.
     * @return bool True if writable.
     */
    public static function isWritable($path) {
        $path = self::join($path);
        return !empty($path) && is_writable($path);
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
        $logger = self::getLogger();
        $path = self::join($path);

        if (empty($path)) {
            $logger->warn('[PATH] Empty path provided to deleteFile');
            return false;
        }

        if (!file_exists($path)) {
            $logger->debug('[PATH] File does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (!is_file($path)) {
            $logger->error('[PATH] Path is not a file', array('path' => $path));
            return false;
        }

        if (!@unlink($path)) {
            $error = error_get_last();
            $logger->error('[PATH] Failed to delete file', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        $logger->debug('[PATH] File deleted', array('path' => $path));
        return true;
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param string $path Directory path.
     * @return bool True if deleted or didn't exist.
     */
    public static function deleteDir($path) {
        $logger = self::getLogger();
        $path = self::join($path);

        if (empty($path)) {
            $logger->warn('[PATH] Empty path provided to deleteDir');
            return false;
        }

        if (!file_exists($path)) {
            $logger->debug('[PATH] Directory does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (!is_dir($path)) {
            $logger->error('[PATH] Path is not a directory', array('path' => $path));
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

        if (!@rmdir($path)) {
            $error = error_get_last();
            $logger->error('[PATH] Failed to delete directory', array(
                'path' => $path,
                'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        $logger->debug('[PATH] Directory deleted', array('path' => $path));
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
        while (!is_dir($path) && $path !== dirname($path)) {
            $path = dirname($path);
        }

        if (!is_dir($path)) {
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
