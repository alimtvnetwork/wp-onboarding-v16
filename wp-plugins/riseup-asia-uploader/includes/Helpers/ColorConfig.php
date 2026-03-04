<?php
/**
 * ColorConfig — JSON-driven color configuration loader with static caching.
 *
 * Loads color definitions from data/colors.json and provides typed lookups
 * by group and key. The JSON is read once and cached in a static variable.
 *
 * @package RiseupAsia\Helpers
 * @since   1.64.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class ColorConfig {

    /** @var array<string, array<string, string>>|null */
    private static ?array $colors = null;

    /** Default fallback color (muted gray). */
    private const FALLBACK = '#6c757d';

    /** Load and cache the colors.json file. */
    private static function load(): array {
        $isLoaded = (self::$colors !== null);

        if ($isLoaded) {
            return self::$colors;
        }

        $path = PathHelper::getColorsJsonPath();
        $isFileMissing = BooleanHelpers::isFileMissing($path);

        if ($isFileMissing) {
            self::$colors = array();

            return self::$colors;
        }

        $json = file_get_contents($path);
        $decoded = json_decode($json, true);
        $isDecodeFailed = ($decoded === null);

        if ($isDecodeFailed) {
            self::$colors = array();

            return self::$colors;
        }

        self::$colors = $decoded;

        return self::$colors;
    }

    /** Get a color by group and key. */
    public static function get(string $group, string $key, string $fallback = self::FALLBACK): string {
        $colors = self::load();
        $hasGroup = isset($colors[$group]);

        if ($hasGroup) {
            $hasKey = isset($colors[$group][$key]);

            if ($hasKey) {
                return $colors[$group][$key];
            }
        }

        return $fallback;
    }

    /** Get an entire color group as an associative array. */
    public static function getGroup(string $group): array {
        $colors = self::load();
        $hasGroup = isset($colors[$group]);

        if ($hasGroup) {
            return $colors[$group];
        }

        return array();
    }

    /** Get a log level color by level value. */
    public static function logLevel(string $level): string {
        return self::get('logLevel', $level);
    }

    /** Get a status color (success, error, warning). */
    public static function status(string $status): string {
        return self::get('status', $status);
    }

    /** Get a WP admin theme color. */
    public static function wpAdmin(string $key): string {
        return self::get('wpAdmin', $key);
    }

    /** Reset the static cache (for testing). */
    public static function reset(): void {
        self::$colors = null;
    }
}
