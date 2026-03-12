<?php
/**
 * NonceType — Nonce action identifiers.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum NonceType: string
{
    case Admin = 'qupload_admin_nonce';
}
