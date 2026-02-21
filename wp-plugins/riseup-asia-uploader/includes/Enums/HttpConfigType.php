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
    case TimeoutDefault = 30;
    case TimeoutShort = 15;

    public static function headRedirectOptions(): array {
        return array('timeout' => self::TimeoutShort->value, 'redirection' => 0, 'sslverify' => true);
    }

    public static function defaultGetOptions(): array {
        return array('timeout' => self::TimeoutDefault->value, 'sslverify' => true);
    }

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
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
