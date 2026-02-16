<?php
/**
 * PaginationConfigType — Default and maximum pagination limits.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PaginationConfigType: int {
    case DefaultLimit         = 50;
    case MaxLimit             = 500;
    case LogRetrievalMaxLines = 500;
}