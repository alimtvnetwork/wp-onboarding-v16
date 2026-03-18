<?php
/**
 * HttpMethodType — HTTP method constants for REST route registration.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HttpMethodType: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Delete = 'DELETE';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
