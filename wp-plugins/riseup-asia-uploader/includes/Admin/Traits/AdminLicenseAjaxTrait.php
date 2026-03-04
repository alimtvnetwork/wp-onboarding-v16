<?php
/**
 * AdminLicenseAjaxTrait — AJAX handlers for license management.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.8.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\NonceType;
use RiseupAsia\Licensing\LicenseManager;

trait AdminLicenseAjaxTrait {

    /** Save a new license key and validate it. */
    public function ajaxLicenseSave(): void {
        check_ajax_referer(NonceType::License->value, '_nonce');

        $key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (empty($key)) {
            wp_send_json_error(['message' => 'License key is required.']);
        }

        $manager = LicenseManager::getInstance();
        $result  = $manager->setLicenseKey($key);

        if ($result === null) {
            wp_send_json_error(['message' => 'Failed to validate license key.']);
        }

        wp_send_json_success([
            'message' => 'License key saved and validated.',
            'result'  => $result,
        ]);
    }

    /** Activate the stored license on this domain. */
    public function ajaxLicenseActivate(): void {
        check_ajax_referer(NonceType::License->value, '_nonce');

        $manager = LicenseManager::getInstance();
        $result  = $manager->activateLicense();

        if ($result === null) {
            wp_send_json_error(['message' => 'Activation failed. Check your license key.']);
        }

        wp_send_json_success([
            'message' => 'License activated on this site.',
            'result'  => $result,
        ]);
    }

    /** Deactivate the stored license from this domain. */
    public function ajaxLicenseDeactivate(): void {
        check_ajax_referer(NonceType::License->value, '_nonce');

        $manager = LicenseManager::getInstance();
        $result  = $manager->deactivateLicense();

        if ($result === null) {
            wp_send_json_error(['message' => 'Deactivation failed.']);
        }

        wp_send_json_success([
            'message' => 'License deactivated from this site.',
            'result'  => $result,
        ]);
    }

    /** Remove the license key entirely. */
    public function ajaxLicenseRemove(): void {
        check_ajax_referer(NonceType::License->value, '_nonce');

        $manager = LicenseManager::getInstance();
        $manager->removeLicenseKey();

        wp_send_json_success(['message' => 'License key removed.']);
    }

    /** Refresh validation status from the API. */
    public function ajaxLicenseRefresh(): void {
        check_ajax_referer(NonceType::License->value, '_nonce');

        $manager = LicenseManager::getInstance();
        $result  = $manager->validateLicense();

        if ($result === null) {
            wp_send_json_error(['message' => 'Validation failed. No license key stored.']);
        }

        wp_send_json_success([
            'message' => 'License status refreshed.',
            'result'  => $result,
        ]);
    }
}
