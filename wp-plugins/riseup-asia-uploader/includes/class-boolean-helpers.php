<?php
/**
 * Boolean Helper Functions
 *
 * Provides positive boolean functions with is_/has_ prefixes for better readability.
 * Always use positive checks instead of negations.
 * Ported from OnboardBooleanHelpers for consistency across plugins.
 *
 * @package RiseupAsiaUploader
 * @since   1.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupBooleanHelpers
 *
 * Centralized boolean check functions that return positive values.
 */
class RiseupBooleanHelpers {

    // =========================================================================
    // FUNCTION CHECKS
    // =========================================================================

    /**
     * Check if a PHP function exists.
     *
     * @param string $function_name Function name.
     * @return bool True if function exists, false otherwise.
     */
    public static function is_func_exists($function_name) {
        return function_exists($function_name);
    }

    /**
     * Check if a PHP function is missing.
     *
     * @param string $function_name Function name.
     * @return bool True if function is missing, false otherwise.
     */
    public static function is_func_missing($function_name) {
        return !function_exists($function_name);
    }

    // =========================================================================
    // CLASS CHECKS
    // =========================================================================

    /**
     * Check if a PHP class exists.
     *
     * @param string $class_name Class name.
     * @return bool True if class exists, false otherwise.
     */
    public static function is_class_exists($class_name) {
        return class_exists($class_name);
    }

    /**
     * Check if a PHP class is missing.
     *
     * @param string $class_name Class name.
     * @return bool True if class is missing, false otherwise.
     */
    public static function is_class_missing($class_name) {
        return !class_exists($class_name);
    }

    // =========================================================================
    // EXTENSION CHECKS
    // =========================================================================

    /**
     * Check if a PHP extension is loaded.
     *
     * @param string $extension_name Extension name.
     * @return bool True if extension is loaded, false otherwise.
     */
    public static function is_extension_loaded($extension_name) {
        return extension_loaded($extension_name);
    }

    /**
     * Check if a PHP extension is missing.
     *
     * @param string $extension_name Extension name.
     * @return bool True if extension is missing, false otherwise.
     */
    public static function is_extension_missing($extension_name) {
        return !extension_loaded($extension_name);
    }

    // =========================================================================
    // DIRECTORY CHECKS
    // =========================================================================

    /**
     * Check if a directory exists.
     *
     * @param string $dir_path Directory path.
     * @return bool True if directory exists, false otherwise.
     */
    public static function is_dir_exists($dir_path) {
        return !empty($dir_path) && is_dir($dir_path);
    }

    /**
     * Check if a directory is missing.
     *
     * @param string $dir_path Directory path.
     * @return bool True if directory is missing, false otherwise.
     */
    public static function is_dir_missing($dir_path) {
        return empty($dir_path) || !is_dir($dir_path);
    }

    /**
     * Check if a directory is writable.
     *
     * @param string $dir_path Directory path.
     * @return bool True if directory is writable, false otherwise.
     */
    public static function is_dir_writable($dir_path) {
        return !empty($dir_path) && is_dir($dir_path) && is_writable($dir_path);
    }

    /**
     * Check if a directory is read-only.
     *
     * @param string $dir_path Directory path.
     * @return bool True if directory is read-only, false otherwise.
     */
    public static function is_dir_readonly($dir_path) {
        return empty($dir_path) || !is_dir($dir_path) || !is_writable($dir_path);
    }

    // =========================================================================
    // FILE CHECKS
    // =========================================================================

    /**
     * Check if a file exists.
     *
     * @param string $file_path File path.
     * @return bool True if file exists, false otherwise.
     */
    public static function is_file_exists($file_path) {
        return !empty($file_path) && file_exists($file_path);
    }

    /**
     * Check if a file is missing.
     *
     * @param string $file_path File path.
     * @return bool True if file is missing, false otherwise.
     */
    public static function is_file_missing($file_path) {
        return empty($file_path) || !file_exists($file_path);
    }

    // =========================================================================
    // VALUE CHECKS
    // =========================================================================

    /**
     * Check if a value is empty.
     *
     * @param mixed $value Value to check.
     * @return bool True if value is empty, false otherwise.
     */
    public static function is_empty($value) {
        return empty($value);
    }

    /**
     * Check if a value has content.
     *
     * @param mixed $value Value to check.
     * @return bool True if value has content, false otherwise.
     */
    public static function has_content($value) {
        return !empty($value);
    }

    /**
     * Check if a value is null.
     *
     * @param mixed $value Value to check.
     * @return bool True if value is null, false otherwise.
     */
    public static function is_null($value) {
        return $value === null;
    }

    /**
     * Check if a value is set (not null).
     *
     * @param mixed $value Value to check.
     * @return bool True if value is set, false otherwise.
     */
    public static function is_set($value) {
        return $value !== null;
    }

    // =========================================================================
    // DATABASE CHECKS
    // =========================================================================

    /**
     * Check if database is connected.
     *
     * @param object|null $db Database instance (must have is_connected method).
     * @return bool True if connected, false otherwise.
     */
    public static function is_db_connected($db) {
        return $db !== null && method_exists($db, 'is_connected') && $db->is_connected();
    }

    /**
     * Check if database is disconnected.
     *
     * @param object|null $db Database instance.
     * @return bool True if disconnected, false otherwise.
     */
    public static function is_db_disconnected($db) {
        return $db === null || !method_exists($db, 'is_connected') || !$db->is_connected();
    }

    // =========================================================================
    // ARRAY CHECKS
    // =========================================================================

    /**
     * Check if a value is an array.
     *
     * @param mixed $value Value to check.
     * @return bool True if array, false otherwise.
     */
    public static function is_array($value) {
        return is_array($value);
    }

    /**
     * Check if array has a key.
     *
     * @param array  $array The array.
     * @param string $key   The key to check.
     * @return bool True if key exists, false otherwise.
     */
    public static function has_key($array, $key) {
        return is_array($array) && array_key_exists($key, $array);
    }

    /**
     * Check if array is missing a key.
     *
     * @param array  $array The array.
     * @param string $key   The key to check.
     * @return bool True if key is missing, false otherwise.
     */
    public static function is_key_missing($array, $key) {
        return !is_array($array) || !array_key_exists($key, $array);
    }

    // =========================================================================
    // STRING CHECKS
    // =========================================================================

    /**
     * Check if a string starts with a prefix.
     *
     * @param string $haystack The string to check.
     * @param string $prefix   The prefix.
     * @return bool True if starts with prefix.
     */
    public static function starts_with($haystack, $prefix) {
        return strpos($haystack, $prefix) === 0;
    }

    /**
     * Check if a string contains a substring.
     *
     * @param string $haystack The string to search in.
     * @param string $needle   The substring to find.
     * @return bool True if contains substring.
     */
    public static function contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }

    // =========================================================================
    // BOOLEAN LOGIC HELPERS
    // =========================================================================

    /**
     * Check if a value is truthy.
     *
     * @param mixed $value Value to check.
     * @return bool True if truthy.
     */
    public static function is_truthy($value) {
        return (bool) $value;
    }

    /**
     * Check if a value is falsy.
     *
     * @param mixed $value Value to check.
     * @return bool True if falsy.
     */
    public static function is_falsy($value) {
        return !(bool) $value;
    }
}
