<?php
/**
 * AdminAjaxUpdateTrait — AJAX handlers for update connection and cache.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Update\UpdateResolver;

trait AdminAjaxUpdateTrait {

    /** AJAX handler: Test update server connection. */
    public function ajaxTestUpdateConnection() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $result = $resolver->testConnection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /** AJAX handler: Clear update URL cache. */
    public function ajaxClearUpdateCache() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $resolver->clearCache();

        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }

    /** AJAX handler: Check for updates now. */
    public function ajaxCheckForUpdates() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $result = $resolver->fetchUpdateInfo(true);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message'     => 'Update check complete',
                'update_info' => $result,
            ));
        }
    }
}
