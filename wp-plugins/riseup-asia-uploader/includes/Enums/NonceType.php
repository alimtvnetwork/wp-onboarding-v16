<?php
/**
 * NonceType — WordPress nonce action strings.
 *
 * Centralizes all nonce action identifiers used in AJAX handlers,
 * REST endpoints, and admin templates.
 *
 * @package RiseupAsia\Enums
 * @since   2.6.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nonce action identifiers.
 */
enum NonceType: string
{
    // ── Admin AJAX ──────────────────────────────────────────────
    case Admin            = 'riseup_admin_nonce';

    // ── Snapshot Download ───────────────────────────────────────
    case SnapshotDownload = 'riseup_snapshot_download_';

    // ── WordPress REST API ──────────────────────────────────────
    case WpRest           = 'wp_rest';

    /**
     * Build a dynamic nonce action with a suffix (e.g., export ID).
     */
    public function withSuffix(int|string $suffix): string
    {
        return $this->value . $suffix;
    }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
