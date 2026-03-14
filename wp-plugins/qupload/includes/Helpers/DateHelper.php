<?php
/**
 * DateHelper — Centralized date formatting and timestamp generation.
 *
 * All internal storage uses UTC. Display methods convert to the WordPress-configured
 * timezone (Settings > General > Timezone) for human-readable output.
 *
 * @package QUpload\Helpers
 * @since   1.0.0
 */

namespace QUpload\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class DateHelper {
    public const ISO_8601_UTC = 'Y-m-d\TH:i:s\Z';
    public const ISO_8601 = 'c';

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

    public static function nowUtc(): string {
        return gmdate(self::ISO_8601_UTC);
    }

    public static function nowIso(): string {
        return gmdate(self::ISO_8601);
    }

    // ─── Display methods (timezone-aware) ───────────────────────────────

    /**
     * Current timestamp in log display format, converted to WP timezone.
     */
    public static function nowLogDisplay(): string {
        return self::formatInWpTimezone(self::LOG_DISPLAY);
    }

    // ─── Format from Unix timestamp ─────────────────────────────────────

    /**
     * Format a Unix timestamp in log display format, converted to WP timezone.
     */
    public static function formatLogDisplay(int $timestamp): string {
        return self::formatInWpTimezone(self::LOG_DISPLAY, $timestamp);
    }
}
