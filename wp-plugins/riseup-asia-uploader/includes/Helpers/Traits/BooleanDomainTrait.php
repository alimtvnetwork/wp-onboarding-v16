<?php
/**
 * BooleanDomainTrait — domain-specific boolean helpers.
 *
 * File/directory guards have been moved to PathHelper.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ResponseKeyType;

trait BooleanDomainTrait {
    public static function isFuncExists(string $functionName): bool { return function_exists($functionName); }
    public static function isFuncMissing(string $functionName): bool { return !function_exists($functionName); }
    public static function isClassExists(string $className): bool { return class_exists($className); }
    public static function isClassMissing(string $className): bool { return !class_exists($className); }
    public static function isClassUnregistered(string $className): bool { return !class_exists($className, false); }
    public static function isExtensionLoaded(string $extensionName): bool { return extension_loaded($extensionName); }
    public static function isExtensionMissing(string $extensionName): bool { return !extension_loaded($extensionName); }
    public static function isAbsentFromList($needle, array $haystack): bool { return !in_array($needle, $haystack); }
    public static function isDbConnected($db): bool { return $db !== null && method_exists($db, 'isReady') && $db->isReady(); }
    public static function isDbDisconnected($db): bool { return $db === null || !method_exists($db, 'isReady') || !$db->isReady(); }
    public static function isConstantMissing(string $name): bool { return !defined($name); }
    public static function hasValue(mixed $value): bool { return !empty($value); }
    public static function isValueEmpty(mixed $value): bool { return empty($value); }
    public static function isNull(mixed $value): bool { return $value === null; }
    public static function isResultFailed(array $result): bool { return empty($result[ResponseKeyType::Success->value]); }
    public static function isKeyMissing(array $data, string|int $key): bool { return !isset($data[$key]); }
    public static function isKeySet(array $data, string|int $key): bool { return isset($data[$key]); }
    public static function hasFilterValue(array $data, string $key): bool { return isset($data[$key]) && !empty($data[$key]); }
    public static function isWpScheduleMissing(string $hook): bool { return !wp_next_scheduled($hook); }
    public static function isCapabilityMissing(string $capability): bool { return !current_user_can($capability); }
    public static function isPropertyMissing(object $obj, string $prop): bool { return !isset($obj->$prop); }

    // ── String Inspection ──

    /** True when $haystack contains $needle anywhere. */
    public static function hasSubstring(string $haystack, string $needle): bool { return str_contains($haystack, $needle); }

    /** True when $haystack does NOT contain $needle. */
    public static function lacksSubstring(string $haystack, string $needle): bool { return !str_contains($haystack, $needle); }

    /** True when $haystack starts with $prefix. */
    public static function hasPrefix(string $haystack, string $prefix): bool { return str_starts_with($haystack, $prefix); }

    /** True when $haystack does NOT start with $prefix. */
    public static function lacksPrefix(string $haystack, string $prefix): bool { return !str_starts_with($haystack, $prefix); }

    /** True when $haystack ends with $suffix. */
    public static function hasSuffix(string $haystack, string $suffix): bool { return str_ends_with($haystack, $suffix); }

    /** True when $haystack does NOT end with $suffix. */
    public static function lacksSuffix(string $haystack, string $suffix): bool { return !str_ends_with($haystack, $suffix); }

    /** True when $value is a non-empty string. */
    public static function isStringPopulated(string $value): bool { return $value !== ''; }

    /** True when $value is an empty string. */
    public static function isStringEmpty(string $value): bool { return $value === ''; }
}
