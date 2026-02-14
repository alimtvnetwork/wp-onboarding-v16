<?php
/**
 * PathConfigType — Configuration File Path Fragments
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration file path fragments.
 */
enum PathConfigType: string
{
    case Detection = '/wp-plugin-detected.json';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }
}
