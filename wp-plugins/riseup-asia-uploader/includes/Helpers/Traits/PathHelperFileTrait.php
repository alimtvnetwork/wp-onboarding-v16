<?php
/**
 * PathHelperFileTrait — file guards and operations.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait PathHelperFileTrait {
    // ── File Guards (moved from BooleanDomainTrait) ──

    public static function isFileExists(string $filePath): bool { return !empty($filePath) && file_exists($filePath); }
    public static function isFileMissing(string $filePath): bool { return empty($filePath) || !file_exists($filePath); }
    public static function isFileUnreadable(string $filePath): bool { return empty($filePath) || !file_exists($filePath) || !is_readable($filePath); }
    public static function isIrregularPath(string $path): bool { return !is_file($path); }
    public static function isCopyFailed(string $source, string $dest): bool { return !copy($source, $dest); }

    // ── File Operations ──

    public static function fileExists(string $path): bool { $path = self::join($path); return !empty($path) && is_file($path); }
    public static function dirExists(string $path): bool { $path = self::join($path); return !empty($path) && is_dir($path); }
    public static function isWritable(string $path): bool { $path = self::join($path); return !empty($path) && is_writable($path); }

    public static function getRelativePath(string $fullPath): string {
        $base = self::getBaseDir();
        $fullPath = str_replace('\\', '/', $fullPath);
        $base = str_replace('\\', '/', $base);

        $isUnderBase = (strpos($fullPath, $base) === 0);

        if ($isUnderBase) {
            return ltrim(substr($fullPath, strlen($base)), '/');
        }

        return $fullPath;
    }

    public static function deleteFile(string $path): bool {
        $path = self::join($path);

        if (empty($path)) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Empty path provided to deleteFile');
            return false;
        }

        clearstatcache(true, $path);

        if (self::isFileMissing($path)) {
            return true;
        }

        $resolvedPath = realpath($path);
        $targetPath = ($resolvedPath === false) ? $path : $resolvedPath;

        if (self::isIrregularPath($targetPath)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path is not a file', array('path' => $targetPath));
            return false;
        }

        $isDeleted = @unlink($targetPath);

        if ($isDeleted === false) {
            @chmod($targetPath, 0644);
            $isDeleted = @unlink($targetPath);
        }

        clearstatcache(true, $targetPath);

        if ($isDeleted === false || file_exists($targetPath)) {
            $error = error_get_last();
            self::safeLog(LogLevelType::Error->value, '[PATH] Failed to delete file', array(
                'path' => $targetPath,
                'error' => $error ? $error['message'] : 'Unknown error',
                'parent_writable' => is_writable(dirname($targetPath)),
            ));
            return false;
        }

        return true;
    }

    public static function deleteDir(string $path): bool {
        $path = self::join($path);

        if (empty($path)) {
            return false;
        }

        if (self::isFileMissing($path)) {
            return true;
        }

        if (self::isDirAbsent($path)) {
            return false;
        }

        $scanResult = scandir($path);

        if ($scanResult === false) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Failed to read directory for deletion', array('path' => $path));
            return false;
        }

        $files = array_diff($scanResult, array('.', '..'));

        foreach ($files as $file) {
            $filePath = self::join($path, $file);

            if (is_dir($filePath)) {
                if (!self::deleteDir($filePath)) {
                    return false;
                }
            } else {
                if (!self::deleteFile($filePath)) {
                    return false;
                }
            }
        }

        if (!@rmdir($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Failed to remove directory', array('path' => $path));
            return false;
        }

        return true;
    }

    public static function getFreeSpace(string $path) {
        $path = self::join($path);
        while (self::isDirAbsent($path) && $path !== dirname($path)) { $path = dirname($path); }
        if (self::isDirAbsent($path)) { return false; }
        return @disk_free_space($path);
    }

    public static function formatBytes(int $bytes, int $decimals = 1): string {
        if ($bytes === 0) { return '0 B'; }
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return sprintf(
            "%.{$decimals}f %s",
            $bytes / pow(1024, $factor),
            $units[$factor],
        );
    }

    /** Alias for deleteDir — removes a directory and all its contents recursively. */
    public static function deleteDirectory(string $path): bool {
        return self::deleteDir($path);
    }
}
