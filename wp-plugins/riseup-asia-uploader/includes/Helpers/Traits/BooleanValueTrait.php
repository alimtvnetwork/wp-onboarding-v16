<?php
/**
 * BooleanValueTrait — value, array, string, and deprecated boolean helpers.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait BooleanValueTrait {

    // VALUE CHECKS (deprecated)

    /** @deprecated 1.19.0 Use native empty($value) instead. */
    public static function is_empty($value) {
        return empty($value);
    }

    /** @deprecated 1.19.0 Use native !empty($value) instead. */
    public static function has_content($value) {
        return !empty($value);
    }

    /** @deprecated 1.19.0 Use native $value === null instead. */
    public static function is_null($value) {
        return $value === null;
    }

    /** @deprecated 1.19.0 Use native $value !== null instead. */
    public static function is_set($value) {
        return $value !== null;
    }

    // ARRAY CHECKS

    public static function is_array($value) {
        return is_array($value);
    }

    public static function has_key($array, $key) {
        return is_array($array) && array_key_exists($key, $array);
    }

    public static function is_key_missing($array, $key) {
        return !is_array($array) || !array_key_exists($key, $array);
    }

    // STRING CHECKS

    public static function starts_with($haystack, $prefix) {
        return strpos($haystack, $prefix) === 0;
    }

    public static function contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }

    // BOOLEAN LOGIC (deprecated)

    /** @deprecated 1.19.0 Use native (bool) $value instead. */
    public static function is_truthy($value) {
        return (bool) $value;
    }

    /** @deprecated 1.19.0 Use native !$value instead. */
    public static function is_falsy($value) {
        return !(bool) $value;
    }
}
