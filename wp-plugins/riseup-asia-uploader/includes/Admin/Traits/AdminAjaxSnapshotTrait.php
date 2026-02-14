<?php
/**
 * AdminAjaxSnapshotTrait — AJAX handlers for snapshot settings, cleanup, and storage.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\ResponseMessageType;

trait AdminAjaxSnapshotTrait {

    /**
     * AJAX handler: Save snapshot settings.
     */
    public function ajaxSaveSnapshotSettings() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
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
        $this->parsePostTextFields($settings);
        $this->parsePostIntFields($settings);
        $this->parsePostBoolFields($settings);
        $this->parsePostWorkerPool($settings);
        $this->parsePostStorageMode($settings);

        return $settings;
    }

    /** Parse text fields from $_POST into settings. */
    private function parsePostTextFields(array &$settings) {
        $fields = array('preferred_provider', 'schedule_frequency', 'schedule_time', 'default_scope', 'retention_type');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }
    }

    /** Parse integer fields from $_POST into settings. */
    private function parsePostIntFields(array &$settings) {
        $fields = array('schedule_day', 'retention_days', 'retention_count', 'max_snapshot_size_mb', 'batch_size');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = intval($_POST[$field]);
            }
        }
    }

    /** Parse boolean fields from $_POST into settings. */
    private function parsePostBoolFields(array &$settings) {
        $fields = array('schedule_enabled', 'pre_restore_backup');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = ($_POST[$field] === '1');
            }
        }
    }

    /** Parse worker_pool_size with clamping from $_POST. */
    private function parsePostWorkerPool(array &$settings) {
        if (!isset($_POST['worker_pool_size'])) {
            return;
        }

        $settings['worker_pool_size'] = max(
            SNAPSHOT_WORKER_POOL_MIN,
            min(SNAPSHOT_WORKER_POOL_MAX, intval($_POST['worker_pool_size']))
        );
    }

    /** Parse storage_mode with validation from $_POST. */
    private function parsePostStorageMode(array &$settings) {
        if (!isset($_POST['storage_mode'])) {
            return;
        }

        $mode = sanitize_text_field($_POST['storage_mode']);
        if (in_array($mode, array('single', 'per-table'))) {
            $settings['storage_mode'] = $mode;
        }
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
    public function ajaxRunSnapshotCleanup() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
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
                RiseupPathUtils::formatBytes($result['space_freed_bytes'])
            ),
            'result' => $result,
        ));
    }

    /**
     * AJAX handler: Get snapshot storage stats.
     */
    public function ajaxGetSnapshotStorageStats() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => ResponseMessageType::Unauthorized->value));
        }

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotFactory.php';
        $scheduler = RiseupSnapshotFactory::scheduler();
        $stats = $scheduler->getStorageStats();

        wp_send_json_success($stats);
    }
}
