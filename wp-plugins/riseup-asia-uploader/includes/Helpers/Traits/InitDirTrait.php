<?php
/**
 * InitDirTrait — Directory setup and security file helpers.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathUtils;

trait InitDirTrait {

    public static function ensureDir(string $path, bool $secure = false): bool {
        $normalized = str_replace('\\', '/', $path);

        if (isset(self::$ensured_dirs[$normalized])) {
            return self::$ensured_dirs[$normalized];
        }

        if (BooleanHelpers::isClassExists(PathUtils::class)) {
            $result = PathUtils::ensureDir($path, $secure);
            self::$ensured_dirs[$normalized] = $result;
            return $result;
        }

        $result = self::ensureDirNative($path, $secure);
        self::$ensured_dirs[$normalized] = $result;
        return $result;
    }

    public static function ensureDirNative(string $path, bool $secure = false): bool {
        if (empty($path)) { return false; }

        if (BooleanHelpers::isDirMissing($path)) {
            if (!@mkdir($path, 0755, true)) {
                if (function_exists('wp_mkdir_p') && !wp_mkdir_p($path)) {
                    return false;
                }
            }
        }

        if ($secure) { self::addSecurityFiles($path); }
        return true;
    }

    public static function addSecurityFiles(string $path): bool {
        $success = true;

        $htaccess = $path . '/.htaccess';
        if (BooleanHelpers::isFileMissing($htaccess)) {
            if (@file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n") === false) {
                $success = false;
            }
        }

        $index = $path . '/index.php';
        if (BooleanHelpers::isFileMissing($index)) {
            if (@file_put_contents($index, "<?php\n// Silence is golden.\n") === false) {
                $success = false;
            }
        }

        return $success;
    }

    public static function ensureSubDir(string $baseDir, string $subDir, bool $secure = false): string|false {
        if (!self::ensureDir($baseDir, $secure)) { return false; }

        $fullPath = rtrim($baseDir, '/') . '/' . ltrim($subDir, '/');
        if (!self::ensureDir($fullPath, false)) { return false; }

        return $fullPath;
    }

    public static function resolveBaseDir(): string {
        if (BooleanHelpers::isFuncMissing('wp_upload_dir')) {
            return dirname(__DIR__) . '/data';
        }

        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            return dirname(__DIR__) . '/data';
        }

        return $upload_dir['basedir'] . '/' . PluginConfigType::UploadsSubdir->value;
    }
}
