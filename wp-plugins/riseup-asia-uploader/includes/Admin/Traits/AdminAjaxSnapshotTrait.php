<?php
/**
 * AdminAjaxSnapshotTrait — AJAX handlers for snapshot settings, cleanup, and storage.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Snapshot\SnapshotFactory;

trait AdminAjaxSnapshotTrait {

    /** AJAX handler: Save snapshot settings. */
    public function ajaxSaveSnapshotSettings() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $settings = $this->parseSnapshotSettingsFromPost();
        $this->applySnapshotSettings($settings);
    }

    /** Parse snapshot settings from $_POST data. */
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
        if (BooleanHelpers::isKeyMissing($_POST, 'worker_pool_size')) {
            return;
        }

        $settings['worker_pool_size'] = max(
            \RiseupAsia\Enums\SnapshotConfigType::WorkerPoolMin->value,
            min(\RiseupAsia\Enums\SnapshotConfigType::WorkerPoolMax->value, intval($_POST['worker_pool_size']))
        );
    }

    /** Parse storage_mode with validation from $_POST. */
    private function parsePostStorageMode(array &$settings) {
        if (BooleanHelpers::isKeyMissing($_POST, 'storage_mode')) {
            return;
        }

        $mode = sanitize_text_field($_POST['storage_mode']);
        if (in_array($mode, StorageModeType::validValues())) {
            $settings['storage_mode'] = $mode;
        }
    }

    /** Apply parsed snapshot settings and sync cron. */
    private function applySnapshotSettings(array $settings) {
        $detector = SnapshotFactory::detector();
        $result = $detector->updateSettings($settings);

        if (isset($settings['schedule_enabled']) || isset($settings['schedule_frequency'])) {
            $scheduler = SnapshotFactory::scheduler();
            $scheduler->syncScheduleWithSettings();
        }

        if ($result) {
            wp_send_json_success(array(ResponseKeyType::Message->value => 'Snapshot settings saved'));
        } else {
            wp_send_json_success(array(ResponseKeyType::Message->value => 'Settings unchanged'));
        }
    }

    /** AJAX handler: Run manual snapshot cleanup. */
    public function ajaxRunSnapshotCleanup() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $scheduler = SnapshotFactory::scheduler();
        $result = $scheduler->runManualCleanup();

        wp_send_json_success(array(
            ResponseKeyType::Message->value => sprintf(
                'Cleanup complete: %d by policy, %d orphans, %d failed removed. Freed %s.',
                $result['deleted_by_policy'],
                $result['deleted_orphans'],
                $result['deleted_failed'],
                PathHelper::formatBytes($result['space_freed_bytes'])
            ),
            'result' => $result,
        ));
    }

    /** AJAX handler: Get snapshot storage stats. */
    public function ajaxGetSnapshotStorageStats() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $scheduler = SnapshotFactory::scheduler();
        $stats = $scheduler->getStorageStats();

        wp_send_json_success($stats);
    }
}
