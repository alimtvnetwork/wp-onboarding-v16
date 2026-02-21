<?php
/**
 * AdminPageType — WordPress admin page slugs.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AdminPageType: string
{
    /** Main plugin page (Activity Logs). Uses PluginConfigType::Slug. */
    case Logs      = 'riseup-asia-uploader';
    case Settings  = 'riseup-asia-settings';
    case Agents    = 'riseup-asia-agents';
    case Snapshots = 'riseup-asia-snapshots';
    case Errors    = 'riseup-asia-errors';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function adminUrl(): string
    {
        return admin_url('admin.php?page=' . $this->value);
    }
}
