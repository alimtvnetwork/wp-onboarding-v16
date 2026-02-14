<?php
/**
 * InitDirTrait — Directory setup and security file helpers.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait InitDirTrait {

    /**
     * Ensure a directory exists with optional security files.
     *
     * Idempotent: calling multiple times with the same path
     * is a no-op after the first successful call within the same request.
     *
     * @param string $path   Directory path to ensure.
     * @param bool   $secure Whether to add .htaccess and index.php security files.
     * @return bool True if directory exists (or was created), false on failure.
     */
    public static function ensureDir($path, $secure = false) {
        $normalized = str_replace('\\', '/', $path);

        if (isset(self::$ensured_dirs[$normalized])) {
            return self::$ensured_dirs[$normalized];
        }

        if (RiseupBooleanHelpers::is_class_exists('RiseupPathUtils')) {
            $result = RiseupPathUtils::ensureDir($path, $secure);
            self::$ensured_dirs[$normalized] = $result;
            return $result;
        }

        $result = self::ensureDirNative($path, $secure);
        self::$ensured_dirs[$normalized] = $result;
        return $result;
    }

    /**
     * Native PHP directory creation fallback.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add security files.
     * @return bool True on success.
     */
    public static function ensureDirNative($path, $secure = false) {
        if (empty($path)) {
            return false;
        }

        if (RiseupBooleanHelpers::is_dir_missing($path)) {
            if (!@mkdir($path, 0755, true)) {
                if (function_exists('wp_mkdir_p') && !wp_mkdir_p($path)) {
                    return false;
                }
            }
        }

        if ($secure) {
            self::addSecurityFiles($path);
        }

        return true;
    }

    /**
     * Add .htaccess and index.php security files to a directory.
     *
     * @param string $path Directory path.
     * @return bool True if all files exist or were created.
     */
    public static function addSecurityFiles($path) {
        $success = true;

        $htaccess = $path . '/.htaccess';
        if (RiseupBooleanHelpers::is_file_missing($htaccess)) {
            if (@file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n") === false) {
                $success = false;
            }
        }

        $index = $path . '/index.php';
        if (RiseupBooleanHelpers::is_file_missing($index)) {
            if (@file_put_contents($index, "<?php\n// Silence is golden.\n") === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Ensure a directory tree (base + subdirectory) exists with security.
     *
     * @param string $base_dir Base directory path.
     * @param string $sub_dir  Subdirectory name/path.
     * @param bool   $secure   Add security files to base directory.
     * @return string|false Full path to subdirectory on success, false on failure.
     */
    public static function ensureSubDir($base_dir, $sub_dir, $secure = false) {
        if (!self::ensureDir($base_dir, $secure)) {
            return false;
        }

        $full_path = rtrim($base_dir, '/') . '/' . ltrim($sub_dir, '/');
        if (!self::ensureDir($full_path, false)) {
            return false;
        }

        return $full_path;
    }

    /**
     * Resolve the plugin's base uploads directory.
     *
     * @return string Base directory path.
     */
    public static function resolveBaseDir() {
        if (RiseupBooleanHelpers::is_func_missing('wp_upload_dir')) {
            return dirname(__DIR__) . '/data';
        }

        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            return dirname(__DIR__) . '/data';
        }

        return $upload_dir['basedir'] . '/' . UPLOADS_SUBDIR;
    }
}
