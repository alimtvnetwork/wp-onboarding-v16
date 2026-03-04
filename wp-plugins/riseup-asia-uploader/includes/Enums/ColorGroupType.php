<?php
/**
 * ColorGroupType — Enum for color group keys in data/colors.json.
 *
 * @package RiseupAsia\Enums
 * @since   1.65.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ColorGroupType: string {
    case LogLevel = 'logLevel';
    case Status   = 'status';
    case WpAdmin  = 'wpAdmin';
}
