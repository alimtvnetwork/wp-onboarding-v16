<?php
/**
 * DateHelper — Centralized date formatting and timestamp generation.
 *
 * All internal storage uses UTC. Display methods convert to the WordPress-configured
 * timezone (Settings > General > Timezone) for human-readable output.
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

    /** Log display format: 15-Jan-24 9:30 AM */
    public const LOG_DISPLAY = 'd-M-y g:i A';

    /** Cached timezone instance to avoid repeated WP option lookups. */
    private static ?\DateTimeZone $cachedTimezone = null;

    /**
     * Resolve the WordPress-configured timezone as a DateTimeZone.
     *
     * Reads timezone_string first (e.g., 'Asia/Kuala_Lumpur'),
     * then falls back to gmt_offset (e.g., 8 => '+08:00').
     * Returns UTC if neither is configured.
     */
    public static function getWpTimezone(): \DateTimeZone
    {
        if (self::$cachedTimezone !== null) {
            return self::$cachedTimezone;
        }

        $timezoneString = get_option('timezone_string', '');
        $hasTimezoneString = ($timezoneString !== '' && $timezoneString !== false);

        if ($hasTimezoneString) {
            try {
                self::$cachedTimezone = new \DateTimeZone($timezoneString);

                return self::$cachedTimezone;
            } catch (\Exception $e) {
                // Fall through to gmt_offset
            }
        }

        $gmtOffset = (float) get_option('gmt_offset', 0);
        $isZeroOffset = ($gmtOffset === 0.0);

        if ($isZeroOffset) {
            self::$cachedTimezone = new \DateTimeZone('UTC');

            return self::$cachedTimezone;
        }

        $offsetSeconds = (int) ($gmtOffset * 3600);
        $sign = ($offsetSeconds >= 0) ? '+' : '-';
        $absSeconds = abs($offsetSeconds);
        $hours = intdiv($absSeconds, 3600);
        $minutes = intdiv($absSeconds % 3600, 60);
        $offsetString = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

        try {
            self::$cachedTimezone = new \DateTimeZone($offsetString);
        } catch (\Exception $e) {
            self::$cachedTimezone = new \DateTimeZone('UTC');
        }

        return self::$cachedTimezone;
    }

    /**
     * Get the timezone abbreviation label for display (e.g., 'SGT', 'UTC+8', 'UTC').
     */
    public static function getTimezoneLabel(): string
    {
        $tz = self::getWpTimezone();
        $now = new \DateTimeImmutable('now', $tz);
        $abbr = $now->format('T');

        // PHP returns numeric offsets like '+08:00' when no named timezone is set
        $isNumericOffset = (strpos($abbr, '+') === 0 || strpos($abbr, '-') === 0);

        if ($isNumericOffset) {
            return 'UTC' . $abbr;
        }

        return $abbr;
    }

    /**
     * Convert a UTC timestamp to the WP-configured timezone for display.
     *
     * This is THE single function that all log display timestamps flow through.
     * It creates a DateTime in UTC, converts to the WP timezone, then formats.
     */
    public static function formatInWpTimezone(string $format, ?int $utcTimestamp = null): string
    {
        $utcTz = new \DateTimeZone('UTC');
        $wpTz = self::getWpTimezone();

        $hasTimestamp = ($utcTimestamp !== null);

        if ($hasTimestamp) {
            $dt = (new \DateTimeImmutable('@' . $utcTimestamp))->setTimezone($utcTz);
        } else {
            $dt = new \DateTimeImmutable('now', $utcTz);
        }

        $localDt = $dt->setTimezone($wpTz);

        return $localDt->format($format);
    }

    /**
     * Clear the cached timezone (useful after settings change).
     */
    public static function clearTimezoneCache(): void
    {
        self::$cachedTimezone = null;
    }

    // ─── UTC methods (for storage / API responses) ──────────────────────

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

    // ─── Display methods (timezone-aware) ───────────────────────────────

    /**
     * Current timestamp in log display format, converted to WP timezone.
     *
     * Output: "15-Jan-24 9:30 AM" (in the site's configured timezone).
     */
    public static function nowLogDisplay(): string {
        return self::formatInWpTimezone(self::LOG_DISPLAY);
    }

    // ─── Format from Unix timestamp ─────────────────────────────────────

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

    /**
     * Format a Unix timestamp in log display format, converted to WP timezone.
     */
    public static function formatLogDisplay(int $timestamp): string {
        return self::formatInWpTimezone(self::LOG_DISPLAY, $timestamp);
    }

    /**
     * Determine if a timestamp is today or yesterday.
     *
     * @return string|null 'today', 'yesterday', or null for older dates.
     */
    public static function relativeDayKey(int $timestamp): ?string {
        $date = self::formatDateOnly($timestamp);

        if ($date === self::nowDateOnly()) {
            return 'today';
        }

        if ($date === self::formatDateOnly(strtotime('-1 day'))) {
            return 'yesterday';
        }

        return null;
    }
}
