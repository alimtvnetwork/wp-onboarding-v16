<?php
/**
 * BooleanDomainTrait — domain-specific boolean helpers (function, class, extension, dir, file, db).
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait BooleanDomainTrait {

    // FUNCTION CHECKS

    public static function isFuncExists(string $functionName): bool {
        return function_exists($functionName);
    }

    public static function isFuncMissing(string $functionName): bool {
        return !function_exists($functionName);
    }

    // CLASS CHECKS

    public static function isClassExists(string $className): bool {
        return class_exists($className);
    }

    public static function isClassMissing(string $className): bool {
        return !class_exists($className);
    }

    /** Check if a class is already loaded (no autoload trigger). */
    public static function isClassNotLoaded(string $className): bool {
        return !class_exists($className, false);
    }

    // EXTENSION CHECKS

    public static function isExtensionLoaded(string $extensionName): bool {
        return extension_loaded($extensionName);
    }

    public static function isExtensionMissing(string $extensionName): bool {
        return !extension_loaded($extensionName);
    }

    // DIRECTORY CHECKS

    public static function isDirExists(string $dirPath): bool {
        return !empty($dirPath) && is_dir($dirPath);
    }

    public static function isDirMissing(string $dirPath): bool {
        return empty($dirPath) || !is_dir($dirPath);
    }

    public static function isDirWritable(string $dirPath): bool {
        return !empty($dirPath) && is_dir($dirPath) && is_writable($dirPath);
    }

    public static function isDirReadonly(string $dirPath): bool {
        return empty($dirPath) || !is_dir($dirPath) || !is_writable($dirPath);
    }

    // FILE CHECKS

    public static function isFileExists(string $filePath): bool {
        return !empty($filePath) && file_exists($filePath);
    }

    public static function isFileMissing(string $filePath): bool {
        return empty($filePath) || !file_exists($filePath);
    }

    public static function isFileUnreadable(string $filePath): bool {
        return empty($filePath) || !file_exists($filePath) || !is_readable($filePath);
    }

    public static function isNotRegularFile(string $path): bool {
        return !is_file($path);
    }

    public static function isNotDirectory(string $path): bool {
        return !is_dir($path);
    }

    public static function isCopyFailed(string $source, string $dest): bool {
        return !copy($source, $dest);
    }

    public static function isNotInList($needle, array $haystack): bool {
        return !in_array($needle, $haystack);
    }

    // DATABASE CHECKS

    public static function isDbConnected($db): bool {
        return $db !== null && method_exists($db, 'isReady') && $db->isReady();
    }

    public static function isDbDisconnected($db): bool {
        return $db === null || !method_exists($db, 'isReady') || !$db->isReady();
    }
}
