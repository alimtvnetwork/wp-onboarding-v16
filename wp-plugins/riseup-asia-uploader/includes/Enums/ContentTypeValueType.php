<?php
/**
 * Content-Type Value Enum
 *
 * MIME type values for HTTP Content-Type headers.
 *
 * @package RiseupAsia\Enums
 * @since   1.63.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ContentTypeValueType: string {
    case Json     = 'application/json';
    case JsonUtf8 = 'application/json; charset=utf-8';

    public function isEqual(self $other): bool { return $this === $other; }
}
