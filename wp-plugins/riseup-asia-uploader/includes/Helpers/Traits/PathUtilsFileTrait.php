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
    public static function file_exists($path) {
        $path = self::join($path);
        return !empty($path) && is_file($path);
    }

    /** @return bool True if directory exists. */
    public static function dir_exists($path) {
        $path = self::join($path);
        return !empty($path) && is_dir($path);
    }

    /** @return bool True if writable. */
    public static function is_writable($path) {
        $path = self::join($path);
        return !empty($path) && is_writable($path);
    }

    /**
     * Get path relative to the plugin base directory.
     *
     * @param string $full_path Full path.
     * @return string Relative path.
     */
    public static function get_relative_path($full_path) {
        $base = self::get_base_dir();
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
    public static function delete_file($path) {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Empty path provided to delete_file');
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
     * @param string $path Directory path.
     * @return bool True if deleted or didn't exist.
     */
    public static function delete_dir($path) {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Empty path provided to delete_dir');
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
            $file_path = self::join($path, $file);
            if (is_dir($file_path)) {
                if (!self::delete_dir($file_path)) {
                    return false;
                }
            } else {
                if (!self::delete_file($file_path)) {
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
     * @param string $path Path to check.
     * @return int|false Free space in bytes, or false on error.
     */
    public static function get_free_space($path) {
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
     *
     * @param int $bytes    Bytes value.
     * @param int $decimals Decimal places.
     * @return string Formatted string (e.g., "15.7 MB").
     */
    public static function format_bytes($bytes, $decimals = 1) {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
