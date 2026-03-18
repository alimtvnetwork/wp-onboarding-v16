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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
