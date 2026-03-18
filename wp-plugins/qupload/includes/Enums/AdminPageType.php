<?php
/**
 * AdminPageType — Admin page slug constants.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AdminPageType: string
{
    case Dashboard = 'qupload';
    case Errors    = 'qupload-errors';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    /** Build the full admin URL for this page. */
    public function adminUrl(): string
    {
        return admin_url('admin.php?page=' . $this->value);
    }
}
