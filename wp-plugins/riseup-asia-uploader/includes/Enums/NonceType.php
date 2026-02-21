<?php
/**
 * NonceType — WordPress nonce action strings.
 *
 * @package RiseupAsia\Enums
 * @since   2.6.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum NonceType: string
{
    case Admin            = 'riseup_admin_nonce';
    case SnapshotDownload = 'riseup_snapshot_download_';
    case WpRest           = 'wp_rest';

    public function withSuffix(int|string $suffix): string
    {
        return $this->value . $suffix;
    }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
