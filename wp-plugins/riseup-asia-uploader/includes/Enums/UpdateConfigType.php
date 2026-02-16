<?php
/**
 * UpdateConfigType — Numeric defaults for auto-update and redirect resolution.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum UpdateConfigType: int {
    case CacheDaysDefault = 7;
    case MaxRedirects     = 5;
}