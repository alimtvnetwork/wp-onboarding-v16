<?php
/**
 * Admin AJAX Trait
 *
 * AJAX handlers for update connection, cache, snapshots, and storage.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;

trait AdminAjaxTrait {

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

    /**
     * AJAX handler: Save snapshot settings.
     */
    public function ajax_save_snapshot_settings() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $settings = $this->parseSnapshotSettingsFromPost();
        $this->applySnapshotSettings($settings);
    }

    /**
     * Parse snapshot settings from $_POST data.
     *
     * @return array Parsed settings.
     */
    private function parseSnapshotSettingsFromPost(): array {
        $settings = array();
        $text_fields = array(
            'preferred_provider', 'schedule_frequency', 'schedule_time',
            'default_scope', 'retention_type',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        $int_fields = array('schedule_day', 'retention_days', 'retention_count', 'max_snapshot_size_mb', 'batch_size');
        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = intval($_POST[$field]);
            }
        }

        $bool_fields = array('schedule_enabled', 'pre_restore_backup');
        foreach ($bool_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = ($_POST[$field] === '1');
            }
        }

        if (isset($_POST['worker_pool_size'])) {
            $settings['worker_pool_size'] = max(
                SNAPSHOT_WORKER_POOL_MIN,
                min(SNAPSHOT_WORKER_POOL_MAX, intval($_POST['worker_pool_size']))
            );
        }

        if (isset($_POST['storage_mode'])) {
            $mode = sanitize_text_field($_POST['storage_mode']);
            if (in_array($mode, array('single', 'per-table'))) {
                $settings['storage_mode'] = $mode;
            }
        }

        return $settings;
    }

    /**
     * Apply parsed snapshot settings and sync cron.
     *
     * @param array $settings Parsed settings.
     */
    private function applySnapshotSettings(array $settings) {
        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotFactory.php';
        $detector = RiseupSnapshotFactory::detector();
        $result = $detector->updateSettings($settings);

        if (isset($settings['schedule_enabled']) || isset($settings['schedule_frequency'])) {
            $scheduler = RiseupSnapshotFactory::scheduler();
            $scheduler->syncScheduleWithSettings();
        }

        if ($result) {
            wp_send_json_success(array('message' => 'Snapshot settings saved'));
        } else {
            wp_send_json_success(array('message' => 'Settings unchanged'));
        }
    }

    /**
     * AJAX handler: Run manual snapshot cleanup.
     */
    public function ajax_run_snapshot_cleanup() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotFactory.php';
        $scheduler = RiseupSnapshotFactory::scheduler();
        $result = $scheduler->runManualCleanup();

        wp_send_json_success(array(
            'message' => sprintf(
                'Cleanup complete: %d by policy, %d orphans, %d failed removed. Freed %s.',
                $result['deleted_by_policy'],
                $result['deleted_orphans'],
                $result['deleted_failed'],
                RiseupPathUtils::format_bytes($result['space_freed_bytes'])
            ),
            'result' => $result,
        ));
    }

    /**
     * AJAX handler: Get snapshot storage stats.
     */
    public function ajax_get_snapshot_storage_stats() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotFactory.php';
        $scheduler = RiseupSnapshotFactory::scheduler();
        $stats = $scheduler->getStorageStats();

        wp_send_json_success($stats);
    }
}
