<?php
/**
 * HttpHeaderType — HTTP header name constants.
 *
 * Every wp_remote_retrieve_header() call MUST reference a case from
 * this enum instead of using hardcoded strings.
 *
 * @package RiseupAsia\Enums
 * @since   2.6.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTTP header name identifiers (lowercase per WordPress convention).
 */
enum HttpHeaderType: string
{
    case Location    = 'location';
    case ContentType = 'content-type';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
