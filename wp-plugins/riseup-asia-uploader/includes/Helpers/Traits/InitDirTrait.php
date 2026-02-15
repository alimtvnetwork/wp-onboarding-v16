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

use RiseupAsia\Enums\PluginConfigType;

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
    public static function ensureDir(string $path, bool $secure = false): bool {
        $normalized = str_replace('\\', '/', $path);

        if (isset(self::$ensured_dirs[$normalized])) {
            return self::$ensured_dirs[$normalized];
        }

        if (RiseupBooleanHelpers::isClassExists('RiseupPathUtils')) {
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
    public static function ensureDirNative(string $path, bool $secure = false): bool {
        if (empty($path)) {
            return false;
        }

        if (RiseupBooleanHelpers::isDirMissing($path)) {
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
    public static function addSecurityFiles(string $path): bool {
        $success = true;

        $htaccess = $path . '/.htaccess';
        if (RiseupBooleanHelpers::isFileMissing($htaccess)) {
            if (@file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n") === false) {
                $success = false;
            }
        }

        $index = $path . '/index.php';
        if (RiseupBooleanHelpers::isFileMissing($index)) {
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
    public static function ensureSubDir(string $baseDir, string $subDir, bool $secure = false): string|false {
        if (!self::ensureDir($baseDir, $secure)) {
            return false;
        }

        $fullPath = rtrim($baseDir, '/') . '/' . ltrim($subDir, '/');
        if (!self::ensureDir($fullPath, false)) {
            return false;
        }

        return $fullPath;
    }

    /**
     * Resolve the plugin's base uploads directory.
     *
     * @return string Base directory path.
     */
    public static function resolveBaseDir(): string {
        if (RiseupBooleanHelpers::isFuncMissing('wp_upload_dir')) {
            return dirname(__DIR__) . '/data';
        }

        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            return dirname(__DIR__) . '/data';
        }

        return $upload_dir['basedir'] . '/' . PluginConfigType::UploadsSubdir->value;
    }
}
