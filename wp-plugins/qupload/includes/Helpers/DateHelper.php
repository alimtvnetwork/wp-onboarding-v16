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

    public static function nowUtc(): string {
        return gmdate(self::ISO_8601_UTC);
    }

    public static function nowIso(): string {
        return gmdate(self::ISO_8601);
    }
}
