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

use RiseupAsia\Enums\ColorGroupType;

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
    public static function get(ColorGroupType $group, string $key, string $fallback = self::FALLBACK): string {
        $colors = self::load();
        $g = $group->value;
        $hasGroup = isset($colors[$g]);

        if ($hasGroup) {
            $hasKey = isset($colors[$g][$key]);

            if ($hasKey) {
                return $colors[$g][$key];
            }
        }

        return $fallback;
    }

    /** Get an entire color group as an associative array. */
    public static function getGroup(ColorGroupType $group): array {
        $colors = self::load();
        $g = $group->value;
        $hasGroup = isset($colors[$g]);

        if ($hasGroup) {
            return $colors[$g];
        }

        return array();
    }

    /** Get a log level color by level value. */
    public static function logLevel(string $level): string {
        return self::get(ColorGroupType::LogLevel, $level);
    }

    /** Get a status color (success, error, warning). */
    public static function status(string $status): string {
        return self::get(ColorGroupType::Status, $status);
    }

    /** Get a WP admin theme color. */
    public static function wpAdmin(string $key): string {
        return self::get(ColorGroupType::WpAdmin, $key);
    }

    /** Reset the static cache (for testing). */
    public static function reset(): void {
        self::$colors = null;
    }
}
