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

    public static function is_func_exists($function_name) {
        return function_exists($function_name);
    }

    public static function is_func_missing($function_name) {
        return !function_exists($function_name);
    }

    // CLASS CHECKS

    public static function is_class_exists($class_name) {
        return class_exists($class_name);
    }

    public static function is_class_missing($class_name) {
        return !class_exists($class_name);
    }

    // EXTENSION CHECKS

    public static function is_extension_loaded($extension_name) {
        return extension_loaded($extension_name);
    }

    public static function is_extension_missing($extension_name) {
        return !extension_loaded($extension_name);
    }

    // DIRECTORY CHECKS

    public static function is_dir_exists($dir_path) {
        return !empty($dir_path) && is_dir($dir_path);
    }

    public static function is_dir_missing($dir_path) {
        return empty($dir_path) || !is_dir($dir_path);
    }

    public static function is_dir_writable($dir_path) {
        return !empty($dir_path) && is_dir($dir_path) && is_writable($dir_path);
    }

    public static function is_dir_readonly($dir_path) {
        return empty($dir_path) || !is_dir($dir_path) || !is_writable($dir_path);
    }

    // FILE CHECKS

    public static function is_file_exists($file_path) {
        return !empty($file_path) && file_exists($file_path);
    }

    public static function is_file_missing($file_path) {
        return empty($file_path) || !file_exists($file_path);
    }

    // DATABASE CHECKS

    public static function is_db_connected($db) {
        return $db !== null && method_exists($db, 'is_connected') && $db->is_connected();
    }

    public static function is_db_disconnected($db) {
        return $db === null || !method_exists($db, 'is_connected') || !$db->is_connected();
    }
}
