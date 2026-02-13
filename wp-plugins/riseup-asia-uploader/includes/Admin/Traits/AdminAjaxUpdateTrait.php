<?php
/**
 * AdminAjaxUpdateTrait — AJAX handlers for update connection and cache.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;

trait AdminAjaxUpdateTrait {

    /**
     * AJAX handler: Test update server connection.
     */
    public function ajax_test_update_connection() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $resolver = RiseupUpdateResolver::get_instance();
        $result = $resolver->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler: Clear update URL cache.
     */
    public function ajax_clear_update_cache() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $resolver = RiseupUpdateResolver::get_instance();
        $resolver->clear_cache();

        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }

    /**
     * AJAX handler: Check for updates now.
     */
    public function ajax_check_for_updates() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $resolver = RiseupUpdateResolver::get_instance();
        $result = $resolver->fetch_update_info(true);

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
