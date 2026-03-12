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

        self::$baseDir = self::resolveUploadsBaseDir() . '/' . PluginConfigType::UploadsSubdir->value;

        return self::$baseDir;
    }

    public static function getLogsDir(): string {
        return self::getBaseDir() . '/logs';
    }

    public static function getTempDir(): string {
        return self::getBaseDir() . '/temp';
    }

    public static function getStageTraceFile(): string {
        return self::getBaseDir() . '/stage-trace.log';
    }

    public static function isFileMissing(string $path): bool {
        return !file_exists($path);
    }

    /** Ensure parent directories are created before the target directory. */
    public static function ensureDirectory(string $dir): bool {
        $normalized = rtrim(str_replace('\\', '/', $dir), '/');

        if ($normalized === '') {
            return false;
        }

        if (is_dir($normalized)) {
            return true;
        }

        $parent = dirname($normalized);
        $hasParent = $parent !== '' && $parent !== '.' && $parent !== $normalized;

        if ($hasParent && !is_dir($parent)) {
            $isParentReady = self::ensureDirectory($parent);

            if ($isParentReady === false) {
                return false;
            }
        }

        return wp_mkdir_p($normalized);
    }

    /** Ensure all parent directories exist for a file path. */
    public static function ensureFileParentDirectory(string $filePath): bool {
        return self::ensureDirectory(dirname($filePath));
    }

    private static function resolveUploadsBaseDir(): string {
        $uploadDir = wp_upload_dir();
        $basedir = '';

        if (is_array($uploadDir) && isset($uploadDir['basedir']) && is_string($uploadDir['basedir'])) {
            $basedir = trim($uploadDir['basedir']);
        }

        if ($basedir === '') {
            $basedir = WP_CONTENT_DIR . '/uploads';
        }

        return rtrim(str_replace('\\', '/', $basedir), '/');
    }
}

