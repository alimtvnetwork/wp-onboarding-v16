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
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Update\UpdateResolver;
use RiseupAsia\Helpers\BooleanHelpers;

trait AdminAjaxUpdateTrait {

    /** AJAX handler: Test update server connection. */
    public function ajaxTestUpdateConnection() {
        check_ajax_referer(NonceType::Admin->value, 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $result = $resolver->testConnection();

        if ($result[ResponseKeyType::Success->value]) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /** AJAX handler: Clear update URL cache. */
    public function ajaxClearUpdateCache() {
        check_ajax_referer(NonceType::Admin->value, 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $resolver->clearCache();

        wp_send_json_success(array(ResponseKeyType::Message->value => 'Cache cleared successfully'));
    }

    /** AJAX handler: Check for updates now. */
    public function ajaxCheckForUpdates() {
        check_ajax_referer(NonceType::Admin->value, 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $resolver = UpdateResolver::getInstance();
        $result = $resolver->fetchUpdateInfo(true);

        if (is_wp_error($result)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                ResponseKeyType::Message->value => 'Update check complete',
                'update_info' => $result,
            ));
        }
    }
}
