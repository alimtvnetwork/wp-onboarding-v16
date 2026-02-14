<?php
/**
 * PathUtilsFileTrait — file operations: exists, delete, relative paths, free space, format.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait PathUtilsFileTrait {

    /** @return bool True if file exists. */
    public static function fileExists(string $path): bool {
        $path = self::join($path);
        return !empty($path) && is_file($path);
    }

    /** @return bool True if directory exists. */
    public static function dirExists(string $path): bool {
        $path = self::join($path);
        return !empty($path) && is_dir($path);
    }

    /** @return bool True if writable. */
    public static function isWritable(string $path): bool {
        $path = self::join($path);
        return !empty($path) && is_writable($path);
    }

    /**
     * Get path relative to the plugin base directory.
     */
    public static function getRelativePath(string $fullPath): string {
        $base = self::getBaseDir();
        $fullPath = str_replace('\\', '/', $fullPath);
        $base = str_replace('\\', '/', $base);

        if (strpos($fullPath, $base) === 0) {
            return ltrim(substr($fullPath, strlen($base)), '/');
        }
        return $fullPath;
    }

    /**
     * Delete a file safely.
     *
     * @return bool True if deleted or didn't exist.
     */
    public static function deleteFile(string $path): bool {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Empty path provided to deleteFile');
            return false;
        }

        if (RiseupBooleanHelpers::is_file_missing($path)) {
            self::safeLog(LogLevelType::Debug->value, '[PATH] File does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (RiseupBooleanHelpers::is_not_regular_file($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path is not a file', array('path' => $path));
            return false;
        }

        if (!@unlink($path)) {
            $error = error_get_last();
            self::safeLog(LogLevelType::Error->value, '[PATH] Failed to delete file', array('path' => $path, 'error' => $error ? $error['message'] : 'Unknown error'));
            return false;
        }

        self::safeLog(LogLevelType::Debug->value, '[PATH] File deleted', array('path' => $path));
        return true;
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @return bool True if deleted or didn't exist.
     */
    public static function deleteDir(string $path): bool {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Empty path provided to deleteDir');
            return false;
        }

        if (RiseupBooleanHelpers::is_file_missing($path)) {
            self::safeLog(LogLevelType::Debug->value, '[PATH] Directory does not exist, nothing to delete', array('path' => $path));
            return true;
        }

        if (RiseupBooleanHelpers::is_not_directory($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path is not a directory', array('path' => $path));
            return false;
        }

        $files = array_diff(scandir($path), array('.', '..'));
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
            $error = error_get_last();
            self::safeLog(LogLevelType::Error->value, '[PATH] Failed to delete directory', array('path' => $path, 'error' => $error ? $error['message'] : 'Unknown error'));
            return false;
        }

        self::safeLog(LogLevelType::Debug->value, '[PATH] Directory deleted', array('path' => $path));
        return true;
    }

    /**
     * Get disk free space for the path's partition.
     *
     * @return int|false Free space in bytes, or false on error.
     */
    public static function getFreeSpace(string $path) {
        $path = self::join($path);
        while (RiseupBooleanHelpers::is_not_directory($path) && $path !== dirname($path)) {
            $path = dirname($path);
        }
        if (RiseupBooleanHelpers::is_not_directory($path)) {
            return false;
        }
        return @disk_free_space($path);
    }

    /**
     * Format bytes to human-readable string.
     */
    public static function formatBytes(int $bytes, int $decimals = 1): string {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
