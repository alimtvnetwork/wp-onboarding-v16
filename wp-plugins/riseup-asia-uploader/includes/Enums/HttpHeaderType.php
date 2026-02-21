<?php
/**
 * HttpHeaderType — HTTP header name constants (lowercase per WordPress convention).
 *
 * @package RiseupAsia\Enums
 * @since   2.6.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HttpHeaderType: string
{
    case Location    = 'location';
    case ContentType = 'content-type';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
