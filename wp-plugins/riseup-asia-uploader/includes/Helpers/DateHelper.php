<?php
/**
 * DateHelper — Centralized date formatting and timestamp generation.
 *
 * Eliminates scattered gmdate() calls with magic format strings.
 * All methods produce UTC timestamps.
 *
 * @package RiseupAsia\Helpers
 * @since   2.3.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class DateHelper {

    /** ISO 8601 UTC with explicit Z suffix: 2024-01-15T09:30:00Z */
    public const ISO_8601_UTC = 'Y-m-d\TH:i:s\Z';

    /** PHP's built-in ISO 8601 format (includes timezone offset): 2024-01-15T09:30:00+00:00 */
    public const ISO_8601 = 'c';

    /** Compact format for filenames/backups: 20240115-093000 */
    public const COMPACT = 'Ymd-His';

    /**
     * Current UTC timestamp in ISO 8601 format with Z suffix.
     */
    public static function nowUtc(): string {
        return gmdate(self::ISO_8601_UTC);
    }

    /**
     * Current timestamp in PHP's ISO 8601 format (with timezone offset).
     */
    public static function nowIso(): string {
        return gmdate(self::ISO_8601);
    }

    /**
     * Current timestamp in compact format (for filenames/backups).
     */
    public static function nowCompact(): string {
        return gmdate(self::COMPACT);
    }

    /**
     * Format a Unix timestamp as ISO 8601 with timezone offset.
     */
    public static function formatIso(int $timestamp): string {
        return gmdate(self::ISO_8601, $timestamp);
    }

    /**
     * Format a Unix timestamp as ISO 8601 UTC with Z suffix.
     */
    public static function formatUtc(int $timestamp): string {
        return gmdate(self::ISO_8601_UTC, $timestamp);
    }

    /**
     * Format a Unix timestamp using a specific format string.
     */
    public static function format(int $timestamp, string $format): string {
        return gmdate($format, $timestamp);
    }
}
