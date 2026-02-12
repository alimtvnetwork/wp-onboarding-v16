<?php
/**
 * UploadSource — Upload Origin Enum
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
enum UploadSource: string
{
    case Script  = 'upload_script';
    case RestApi = 'rest_api';
    case AdminUi = 'admin_ui';
    case WpCli   = 'wp_cli';

    /**
     * All valid source values as a flat array.
     *
     * @return string[]
     */
    public static function valid_values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a raw string is a valid upload source.
     *
     * @param string $source Source to validate.
     * @return bool
     */
    public static function is_valid(string $source): bool
    {
        return self::tryFrom($source) !== null;
    }
}
