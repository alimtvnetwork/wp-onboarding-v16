<?php
/**
 * UploadSourceType — Upload Origin Enum
 *
 * Identifies how a plugin upload was initiated.
 * Used in transaction logging and request validation.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upload source identifiers for transaction logging and request validation.
 */
enum UploadSourceType: string
{
    case Script  = 'Script';
    case RestApi = 'RestAPI';
    case AdminUi = 'AdminUI';
    case WpCli   = 'WPCLI';

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

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** @return string[] */
    public static function validValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Check if a raw string is a valid upload source. */
    public static function isValid(string $source): bool
    {
        return self::tryFrom($source) !== null;
    }
}
