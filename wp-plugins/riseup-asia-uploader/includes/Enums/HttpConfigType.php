<?php
/**
 * HttpConfigType — HTTP request configuration constants.
 *
 * @package RiseupAsia\Enums
 * @since   2.0.0
 */

namespace RiseupAsia\Enums;

enum HttpConfigType: int
{
    /** Default timeout for standard API requests (seconds). */
    case TimeoutDefault = 30;

    /** Short timeout for lightweight checks like HEAD redirects (seconds). */
    case TimeoutShort = 15;

    /** Options for HEAD-based redirect following (no WP redirection, SSL enforced). */
    public static function headRedirectOptions(): array {
        return array('timeout' => self::TimeoutShort->value, 'redirection' => 0, 'sslverify' => true);
    }

    /** Options for standard GET requests (SSL enforced). */
    public static function defaultGetOptions(): array {
        return array('timeout' => self::TimeoutDefault->value, 'sslverify' => true);
    }

    /** Options for authenticated API requests (SSL enforced, JSON content type). */
    public static function authenticatedOptions(string $method, string $authHeader): array {
        return array(
            'method'    => strtoupper($method),
            'timeout'   => self::TimeoutDefault->value,
            'headers'   => array(
                'Authorization' => $authHeader,
                'Content-Type'  => 'application/json',
            ),
            'sslverify' => true,
        );
    }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
