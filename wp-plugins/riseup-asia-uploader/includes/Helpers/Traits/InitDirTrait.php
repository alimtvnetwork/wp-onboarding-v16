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
use RiseupAsia\Helpers\PathHelper;

trait InitDirTrait {
    public static function makeDirectory(string $path, bool $secure = false): bool {
        $normalized = str_replace('\\', '/', $path);

        if (isset(self::$ensured_dirs[$normalized])) {
            return self::$ensured_dirs[$normalized];
        }

        if (BooleanHelpers::isClassExists(PathHelper::class)) {
            $result = PathHelper::makeDirectory($path, $secure);
            self::$ensured_dirs[$normalized] = $result;
            return $result;
        }

        $result = self::makeDirectoryNative($path, $secure);
        self::$ensured_dirs[$normalized] = $result;
        return $result;
    }

    public static function makeDirectoryNative(string $path, bool $secure = false): bool {
        if (empty($path)) { return false; }

        if (PathHelper::isDirMissing($path)) {
            $isMkdirFailed = (@mkdir($path, 0755, true) === false);

            if ($isMkdirFailed) {
                $isWpFallbackAvailable = BooleanHelpers::isFuncExists('wp_mkdir_p');
                $isWpFallbackFailed = $isWpFallbackAvailable && (wp_mkdir_p($path) === false);

                if ($isWpFallbackFailed) {
                    return false;
                }
            }
        }

        if ($secure) { self::addSecurityFiles($path); }
        return true;
    }

    public static function addSecurityFiles(string $path): bool {
        $isSecured = true;

        $htaccess = $path . '/.htaccess';

        if (PathHelper::isFileMissing($htaccess)) {
            if (@file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n") === false) {
                $isSecured = false;
            }
        }

        $index = $path . '/index.php';

        if (PathHelper::isFileMissing($index)) {
            if (@file_put_contents($index, "<?php\n// Silence is golden.\n") === false) {
                $isSecured = false;
            }
        }

        return $isSecured;
    }

    public static function makeSubDirectory(
        string $baseDir,
        string $subDir,
        bool $secure = false,
    ): string|false {
        $isBaseDirFailed = (self::makeDirectory($baseDir, $secure) === false);

        if ($isBaseDirFailed) { return false; }

        $fullPath = rtrim($baseDir, '/') . '/' . ltrim($subDir, '/');
        $isSubDirFailed = (self::makeDirectory($fullPath, false) === false);

        if ($isSubDirFailed) { return false; }

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
