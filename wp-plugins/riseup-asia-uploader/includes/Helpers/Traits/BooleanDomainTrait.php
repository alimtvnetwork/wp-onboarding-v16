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
    public static function isResultFailed(array $result): bool { return empty($result['success']); }
    public static function isKeyMissing(array $data, string|int $key): bool { return !isset($data[$key]); }
    public static function isKeySet(array $data, string|int $key): bool { return isset($data[$key]); }
}
