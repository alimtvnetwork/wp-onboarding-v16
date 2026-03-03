<?php
/**
 * PathHelper — Centralized path resolution for QUpload.
 *
 * @package QUpload\Helpers
 * @since   1.0.0
 */

namespace QUpload\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\PluginConfigType;

class PathHelper {
    private static ?string $baseDir = null;

    public static function getBaseDir(): string {
        if (self::$baseDir !== null) {
            return self::$baseDir;
        }

        $uploadDir = wp_upload_dir();
        self::$baseDir = rtrim($uploadDir['basedir'], '/') . '/' . PluginConfigType::UploadsSubdir->value;

        return self::$baseDir;
    }

    public static function getLogsDir(): string {
        return self::getBaseDir() . '/logs';
    }

    public static function getTempDir(): string {
        return self::getBaseDir() . '/temp';
    }

    public static function isFileMissing(string $path): bool {
        return !file_exists($path);
    }

    public static function ensureDirectory(string $dir): bool {
        if (is_dir($dir)) {
            return true;
        }

        return wp_mkdir_p($dir);
    }
}
