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

    /** Date only: 2024-01-15 */
    public const DATE_ONLY = 'Y-m-d';

    /** Standard datetime (no T/Z): 2024-01-15 09:30:00 */
    public const DATETIME = 'Y-m-d H:i:s';

    /** Human-readable date: January 15, 2024 */
    public const DISPLAY_DATE = 'F j, Y';

    /** 12-hour time display: 2:30 PM */
    public const DISPLAY_TIME = 'g:i A';

    /** Short datetime for titles/labels: 2024-01-15 09:30 */
    public const COMPACT_DATETIME = 'Y-m-d H:i';

    /** Filename-safe datetime: 2024-01-15_093000 */
    public const FILENAME_DATETIME = 'Y-m-d_His';

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
     * Current date only (Y-m-d).
     */
    public static function nowDateOnly(): string {
        return gmdate(self::DATE_ONLY);
    }

    /**
     * Current datetime without T/Z (Y-m-d H:i:s).
     */
    public static function nowDatetime(): string {
        return gmdate(self::DATETIME);
    }

    /**
     * Current short datetime for titles/labels (Y-m-d H:i).
     */
    public static function nowCompactDatetime(): string {
        return gmdate(self::COMPACT_DATETIME);
    }

    /**
     * Current filename-safe datetime (Y-m-d_His).
     */
    public static function nowFilenameDatetime(): string {
        return gmdate(self::FILENAME_DATETIME);
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

    /**
     * Format a Unix timestamp as date only (Y-m-d).
     */
    public static function formatDateOnly(int $timestamp): string {
        return gmdate(self::DATE_ONLY, $timestamp);
    }

    /**
     * Format a Unix timestamp as human-readable date (F j, Y).
     */
    public static function formatDisplayDate(int $timestamp): string {
        return gmdate(self::DISPLAY_DATE, $timestamp);
    }

    /**
     * Format a Unix timestamp as 12-hour time (g:i A).
     */
    public static function formatDisplayTime(int $timestamp): string {
        return gmdate(self::DISPLAY_TIME, $timestamp);
    }

    /**
     * Format a Unix timestamp as standard datetime (Y-m-d H:i:s).
     */
    public static function formatDatetime(int $timestamp): string {
        return gmdate(self::DATETIME, $timestamp);
    }
}
