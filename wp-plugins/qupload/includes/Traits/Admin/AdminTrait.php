<?php
/**
 * AdminTrait — Registers the QUpload admin page.
 *
 * @package QUpload\Traits\Admin
 * @since   1.1.0
 */

namespace QUpload\Traits\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Admin\AdminPage;

trait AdminTrait
{
    /** Register the admin page in WordPress Tools menu. */
    private function registerAdminPage(): void
    {
        if (!is_admin()) {
            return;
        }

        AdminPage::register();
        $this->fileLogger->debug('Admin page registered');
    }
}
