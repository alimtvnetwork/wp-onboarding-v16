<?php
/**
 * DateHelper — Centralized date formatting and timestamp generation.
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

    public static function nowUtc(): string {
        return gmdate(self::ISO_8601_UTC);
    }

    public static function nowIso(): string {
        return gmdate(self::ISO_8601);
    }

    /**
     * Current timestamp in log display format (d-M-y g:i A).
     */
    public static function nowLogDisplay(): string {
        return gmdate(self::LOG_DISPLAY);
    }

    /**
     * Format a Unix timestamp in log display format (d-M-y g:i A).
     */
    public static function formatLogDisplay(int $timestamp): string {
        return gmdate(self::LOG_DISPLAY, $timestamp);
    }
}
