<?php
/**
 * HttpMethodType — HTTP method constants for REST route registration.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HttpMethodType: string
{
    case Get    = 'GET';
    case Post   = 'POST';
    case Put    = 'PUT';
    case Delete = 'DELETE';
}
