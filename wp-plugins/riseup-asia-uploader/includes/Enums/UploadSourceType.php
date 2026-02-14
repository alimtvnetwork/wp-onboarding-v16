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
    case Script  = 'upload_script';
    case RestApi = 'rest_api';
    case AdminUi = 'admin_ui';
    case WpCli   = 'wp_cli';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
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
