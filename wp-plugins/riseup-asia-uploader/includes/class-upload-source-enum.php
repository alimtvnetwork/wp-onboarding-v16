<?php
/**
 * UploadSourceEnum — Upload Source Constants
 *
 * Centralizes upload source identifiers used in transaction logging
 * and request validation.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upload source constants.
 *
 * Replaces the UPLOAD_SOURCE_* define() constants in constants.php.
 */
class UploadSourceEnum {

    /** Upload via external script */
    public const SCRIPT   = 'upload_script';

    /** Upload via REST API */
    public const REST_API = 'rest_api';

    /** Upload via WordPress admin UI */
    public const ADMIN_UI = 'admin_ui';

    /** Upload via WP-CLI */
    public const WP_CLI   = 'wp_cli';

    /**
     * All valid upload sources for validation.
     */
    public const VALID_SOURCES = [
        self::SCRIPT,
        self::REST_API,
        self::ADMIN_UI,
        self::WP_CLI,
    ];

    /**
     * Check if a source string is valid.
     *
     * @param string $source Source to validate.
     * @return bool True if valid.
     */
    public static function is_valid($source) {
        return in_array($source, self::VALID_SOURCES, true);
    }
}
