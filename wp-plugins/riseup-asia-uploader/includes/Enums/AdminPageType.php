<?php
/**
 * AdminPageType — WordPress admin page slugs.
 *
 * Every admin page slug used in add_menu_page(), add_submenu_page(),
 * admin_url(), or $_GET['page'] comparisons MUST reference a case from this enum.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page slug identifiers.
 */
enum AdminPageType: string
{
    /** Main plugin page (Activity Logs). Uses PluginConfigType::Slug. */
    case Logs      = 'riseup-asia-uploader';

    /** Settings page. */
    case Settings  = 'riseup-asia-settings';

    /** Agent Sites page. */
    case Agents    = 'riseup-asia-agents';

    /** Snapshots page. */
    case Snapshots = 'riseup-asia-snapshots';

    /** Error Log page. */
    case Errors    = 'riseup-asia-errors';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** Build the full admin URL for this page. */
    public function adminUrl(): string
    {
        return admin_url('admin.php?page=' . $this->value);
    }
}
