<?php
/**
 * PaginationConfigType — Default and maximum pagination limits.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PaginationConfigType: int {
    case DefaultLimit = 50;
    case MaxLimit     = 500;

    /** Log retrieval max lines — same as MaxLimit but semantically distinct. */
    public static function logRetrievalMaxLines(): int
    {
        return self::MaxLimit->value;
    }
}