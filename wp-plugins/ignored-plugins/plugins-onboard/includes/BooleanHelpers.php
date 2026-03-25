<?php
/**
 * Boolean Helper Functions
 *
 * Provides positive boolean functions with is_/has_ prefixes for better readability.
 * Always use positive checks instead of negations.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardBooleanHelpers
 *
 * Centralized boolean check functions that return positive values.
 */
class OnboardBooleanHelpers {

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

    /**
     * Check if a value is empty.
     *
     * @deprecated 1.19.0 Use native empty($value) instead.
     * @param mixed $value Value to check.
     * @return bool True if value is empty, false otherwise.
     */
    public static function is_empty($value) {
        return empty($value);
    }

    /**
     * Check if a value has content.
     *
     * @deprecated 1.19.0 Use native !empty($value) instead.
     * @param mixed $value Value to check.
     * @return bool True if value has content, false otherwise.
     */
    public static function has_content($value) {
        return !empty($value);
    }

    /**
     * Check if a value is null.
     *
     * @deprecated 1.19.0 Use native $value === null instead.
     * @param mixed $value Value to check.
     * @return bool True if value is null, false otherwise.
     */
    public static function is_null($value) {
        return $value === null;
    }

    /**
     * Check if a value is set (not null).
     *
     * @deprecated 1.19.0 Use native $value !== null instead.
     * @param mixed $value Value to check.
     * @return bool True if value is set, false otherwise.
     */
    public static function is_set($value) {
        return $value !== null;
    }

    /**
     * Check if database is connected.
     *
     * @param OnboardDatabase|null $db Database instance.
     * @return bool True if connected, false otherwise.
     */
    public static function is_db_connected($db) {
        return $db !== null && $db->is_connected();
    }

    /**
     * Check if database is disconnected.
     *
     * @param OnboardDatabase|null $db Database instance.
     * @return bool True if disconnected, false otherwise.
     */
    public static function is_db_disconnected($db) {
        return $db === null || !$db->is_connected();
    }

    // ── String Inspection ──

    /**
     * Check if a string contains a substring.
     *
     * @param string $haystack String to search in.
     * @param string $needle   Substring to find.
     * @return bool True if haystack contains needle.
     */
    public static function has_substring($haystack, $needle) {
        return str_contains($haystack, $needle);
    }

    /**
     * Check if a string does NOT contain a substring.
     *
     * @param string $haystack String to search in.
     * @param string $needle   Substring to find.
     * @return bool True if haystack lacks needle.
     */
    public static function lacks_substring($haystack, $needle) {
        return !str_contains($haystack, $needle);
    }

    /**
     * Check if a string starts with a prefix.
     *
     * @param string $haystack String to check.
     * @param string $prefix   Expected prefix.
     * @return bool True if haystack starts with prefix.
     */
    public static function has_prefix($haystack, $prefix) {
        return str_starts_with($haystack, $prefix);
    }

    /**
     * Check if a string does NOT start with a prefix.
     *
     * @param string $haystack String to check.
     * @param string $prefix   Expected prefix.
     * @return bool True if haystack lacks prefix.
     */
    public static function lacks_prefix($haystack, $prefix) {
        return !str_starts_with($haystack, $prefix);
    }

    /**
     * Check if a string ends with a suffix.
     *
     * @param string $haystack String to check.
     * @param string $suffix   Expected suffix.
     * @return bool True if haystack ends with suffix.
     */
    public static function has_suffix($haystack, $suffix) {
        return str_ends_with($haystack, $suffix);
    }

    /**
     * Check if a string does NOT end with a suffix.
     *
     * @param string $haystack String to check.
     * @param string $suffix   Expected suffix.
     * @return bool True if haystack lacks suffix.
     */
    public static function lacks_suffix($haystack, $suffix) {
        return !str_ends_with($haystack, $suffix);
    }

    /**
     * Check if a string is non-empty.
     *
     * @param string $value String to check.
     * @return bool True if string has content.
     */
    public static function is_string_populated($value) {
        return $value !== '';
    }

    /**
     * Check if a string is empty.
     *
     * @param string $value String to check.
     * @return bool True if string is empty.
     */
    public static function is_string_empty($value) {
        return $value === '';
    }

    /**
     * Check if a value evaluates to true.
     *
     * @param mixed $value Value to check.
     * @return bool True if truthy.
     */
    public static function isTruthy($value) {
        return !empty($value);
    }

    /**
     * Check if a value evaluates to false.
     *
     * @param mixed $value Value to check.
     * @return bool True if falsy.
     */
    public static function isFalsy($value) {
        return empty($value);
    }
}
