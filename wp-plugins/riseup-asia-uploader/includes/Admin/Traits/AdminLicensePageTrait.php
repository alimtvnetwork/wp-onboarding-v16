<?php
/**
 * AdminLicensePageTrait — Render method for the License admin page.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.8.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Licensing\LicenseManager;

trait AdminLicensePageTrait {

    /** Render the license settings page. */
    public function renderLicensePage(): void {
        $manager = LicenseManager::getInstance();
        $status  = $manager->getLicenseStatus();
        $validation = $manager->validateLicense();

        $licenseKey    = get_option('riseup_license_key', '');
        $licenseStatus = get_option('riseup_license_status', '');
        $checkedAt     = get_option('riseup_license_checked_at', '');

        include dirname(__FILE__) . '/../../templates/admin-license.php';
    }
}
