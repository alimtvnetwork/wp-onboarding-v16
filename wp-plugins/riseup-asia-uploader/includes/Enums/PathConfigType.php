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
}
