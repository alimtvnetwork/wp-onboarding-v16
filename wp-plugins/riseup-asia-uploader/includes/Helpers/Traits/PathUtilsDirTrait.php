<?php
/**
 * PathUtilsDirTrait — directory creation, security files, and validation.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PathUtilsDirTrait {

    /**
     * Check if a path is within allowed boundaries (prevents path traversal).
     *
     * @param string $path      Path to validate.
     * @param string $base_path Allowed base path.
     * @return bool True if path is safe.
     */
    public static function is_safe_path($path, $base_path) {
        $real_base = realpath($base_path);
        if ($real_base === false) {
            self::safe_log('warn', '[PATH] Base path does not exist', array('base' => $base_path));
            return false;
        }

        $real_path = realpath($path);
        if ($real_path === false) {
            $parent = dirname($path);
            $real_parent = realpath($parent);
            if ($real_parent === false) {
                self::safe_log('warn', '[PATH] Neither path nor parent exists', array('path' => $path, 'parent' => $parent));
                return false;
            }
            $real_path = self::join($real_parent, basename($path));
        }

        $real_base = str_replace('\\\\', '/', $real_base);
        $real_path = str_replace('\\\\', '/', $real_path);

        $is_safe = strpos($real_path, $real_base) === 0;
        if (!$is_safe) {
            self::safe_log('error', '[PATH] Path traversal attempt detected', array('path' => $path, 'resolved' => $real_path, 'base' => $real_base));
        }
        return $is_safe;
    }

    /**
     * Check if ensuring a directory fails (semantic inverse of ensure_dir).
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add security files.
     * @return bool True if directory is MISSING (creation failed).
     */
    public static function is_dir_missing($path, $secure = false) {
        return !self::ensure_dir($path, $secure);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensure_dir($path, $secure = false) {
        $path = self::join($path);
        if (empty($path)) {
            self::safe_log('error', '[PATH] Empty path provided to ensure_dir');
            return false;
        }

        if (is_dir($path)) {
            self::safe_log('debug', '[PATH] Directory already exists', array('path' => $path));
            if ($secure) {
                self::add_security_files($path);
            }
            return true;
        }

        self::safe_log('info', '[PATH] Creating directory', array('path' => $path, 'secure' => $secure));
        if (!wp_mkdir_p($path)) {
            $error = error_get_last();
            self::safe_log('error', '[PATH] Directory creation failed', array(
                'path' => $path, 'error' => $error ? $error['message'] : 'Unknown error',
                'parent_exists' => is_dir(dirname($path)), 'parent_writable' => is_writable(dirname($path)),
            ));
            return false;
        }

        self::safe_log('info', '[PATH] Directory created successfully', array('path' => $path));
        if ($secure) {
            self::add_security_files($path);
        }
        return true;
    }

    /**
     * Add security files (.htaccess and index.php) to a directory.
     *
     * @param string $path Directory path.
     * @return bool True if files were created successfully.
     */
    public static function add_security_files($path) {
        $success = true;

        $htaccess_path = self::join($path, '.htaccess');
        if (RiseupBooleanHelpers::is_file_missing($htaccess_path)) {
            $content = "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n";
            if (@file_put_contents($htaccess_path, $content) === false) {
                self::safe_log('warn', '[PATH] Failed to create .htaccess', array('path' => $htaccess_path));
                $success = false;
            }
        }

        $index_path = self::join($path, 'index.php');
        if (RiseupBooleanHelpers::is_file_missing($index_path)) {
            if (@file_put_contents($index_path, "<?php\n// Silence is golden.\n") === false) {
                self::safe_log('warn', '[PATH] Failed to create index.php', array('path' => $index_path));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Join path segments and ensure the directory exists.
     *
     * @param bool   $secure     Add security files.
     * @param string ...$segments Path segments to join.
     * @return string|false Full path if successful, false on failure.
     */
    public static function ensure_path($secure, ...$segments) {
        $path = self::join(...$segments);
        if (empty($path)) {
            self::safe_log('error', '[PATH] Empty path from segments', array('segments' => $segments));
            return false;
        }
        if (self::is_dir_missing($path, $secure)) {
            return false;
        }
        return $path;
    }
}
