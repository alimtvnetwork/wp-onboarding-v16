<?php
/**
 * HttpMethodType — HTTP Method Enum
 *
 * Every register_rest_route() call MUST use these cases
 * instead of WP_REST_Server constants or string literals.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTTP method constants for REST route registration.
 */
enum HttpMethodType: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Patch  = 'PATCH';
    case Delete = 'DELETE';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /**
     * Editable methods string for WordPress route registration.
     * WordPress accepts comma-separated methods.
     *
     * @return string
     */
    public static function editable(): string
    {
        return 'PUT, PATCH';
    }
}
