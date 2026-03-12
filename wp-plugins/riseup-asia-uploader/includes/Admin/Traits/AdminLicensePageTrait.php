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

use RiseupAsia\Enums\LicenseOptionType;
use RiseupAsia\Licensing\LicenseManager;

trait AdminLicensePageTrait {

    /** Render the license settings page. */
    public function renderLicensePage(): void {
        $manager = LicenseManager::getInstance();
        $status  = $manager->getLicenseStatus();
        $validation = $manager->validateLicense();

        $licenseKey    = get_option(LicenseOptionType::LicenseKey->value, '');
        $licenseStatus = get_option(LicenseOptionType::LicenseStatus->value, '');
        $checkedAt     = get_option(LicenseOptionType::LicenseCheckedAt->value, '');

        include dirname(__FILE__, 4) . '/templates/admin-license.php';
    }
}
