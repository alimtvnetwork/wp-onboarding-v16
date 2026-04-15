<?php
/**
 * AdminMenuEnqueueMiscTrait — License, Logs, and Feedback page asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\NonceType;

trait AdminMenuEnqueueMiscTrait {

    /** Enqueue License page assets. */
    private function enqueueLicenseAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-license', plugins_url('assets/css/admin-license.css', $pluginFile), [], $version);
        wp_enqueue_script('riseup-admin-license', plugins_url('assets/js/admin-license.js', $pluginFile), ['jquery'], $version, true);

        wp_localize_script('riseup-admin-license', 'RiseupLicense', [
            'nonce'   => wp_create_nonce(NonceType::License->value),
            'actions' => [
                'save'       => AjaxActionType::LicenseSave->value,
                'activate'   => AjaxActionType::LicenseActivate->value,
                'deactivate' => AjaxActionType::LicenseDeactivate->value,
                'remove'     => AjaxActionType::LicenseRemove->value,
                'refresh'    => AjaxActionType::LicenseRefresh->value,
            ],
            'i18n'    => [
                'enterKey'           => __('Please enter a license key.', $pluginSlug),
                'validationFailed'   => __('Validation failed.', $pluginSlug),
                'requestFailed'      => __('Request failed.', $pluginSlug),
                'activationFailed'   => __('Activation failed.', $pluginSlug),
                'confirmDeactivate'  => __('Are you sure you want to deactivate this license?', $pluginSlug),
                'deactivationFailed' => __('Deactivation failed.', $pluginSlug),
                'confirmRemove'      => __('Remove the license key entirely? This cannot be undone.', $pluginSlug),
                'removalFailed'      => __('Removal failed.', $pluginSlug),
                'refreshFailed'      => __('Refresh failed.', $pluginSlug),
            ],
        ]);
    }

    /** Enqueue Activity Logs page assets. */
    private function enqueueLogsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-logs', plugins_url('assets/css/admin-logs.css', $pluginFile), [], $version);
        wp_enqueue_script('riseup-admin-logs', plugins_url('assets/js/admin-logs.js', $pluginFile), ['jquery'], $version, true);
    }

    /** Enqueue Feedback page assets. */
    private function enqueueFeedbackAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-feedback', plugins_url('assets/css/admin-feedback.css', $pluginFile), [], $version);
        wp_enqueue_script('riseup-admin-feedback', plugins_url('assets/js/admin-feedback.js', $pluginFile), ['jquery'], $version, true);

        wp_localize_script('riseup-admin-feedback', 'RiseupFeedback', [
            'nonce'   => wp_create_nonce(NonceType::Feedback->value),
            'actions' => [
                'send'       => AjaxActionType::SendFeedback->value,
                'checkReady' => AjaxActionType::CheckFeedbackReady->value,
            ],
            'i18n'    => [
                'sent'         => __('Feedback sent successfully!', $pluginSlug),
                'sendFailed'   => __('Failed to send feedback. Please check your email configuration.', $pluginSlug),
                'checkFailed'  => __('Failed to check feedback readiness.', $pluginSlug),
                'tooManyFiles' => __('Maximum 3 files allowed.', $pluginSlug),
                'invalidFile'  => __('Invalid file. Only JPG, PNG, GIF, WebP under 2 MB are allowed.', $pluginSlug),
            ],
        ]);
    }
}
