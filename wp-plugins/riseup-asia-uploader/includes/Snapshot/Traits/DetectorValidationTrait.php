<?php
/**
 * DetectorValidationTrait — settings validation and sanitization helpers.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DetectorValidationTrait {

    /**
     * Validate and sanitize settings.
     *
     * @param array $settings Settings to validate.
     * @return array Validated settings.
     */
    private function validateSettings($settings) {
        $this->validateEnumFields($settings);
        $this->clampNumericFields($settings);
        $this->castBooleanFields($settings);
        $this->validateMiscFields($settings);

        return $settings;
    }

    /** Validate enum-style fields against allowed values. */
    private function validateEnumFields(array &$settings) {
        $rules = array(
            'preferred_provider' => array(SNAPSHOT_PROVIDER_AUTO, SNAPSHOT_PROVIDER_WP_RESET, SNAPSHOT_PROVIDER_UPDRAFT, SNAPSHOT_PROVIDER_NATIVE),
            'schedule_frequency' => array(SNAPSHOT_FREQ_MANUAL, SNAPSHOT_FREQ_DAILY, SNAPSHOT_FREQ_WEEKLY, SNAPSHOT_FREQ_MONTHLY),
            'default_scope'      => array(SNAPSHOT_SCOPE_ALL, SNAPSHOT_SCOPE_WORDPRESS, SNAPSHOT_SCOPE_CONTENT, SNAPSHOT_SCOPE_CUSTOM),
            'retention_type'     => array('days', 'count', 'none'),
        );
        $defaults = array('preferred_provider' => SNAPSHOT_PROVIDER_AUTO, 'schedule_frequency' => SNAPSHOT_FREQ_DAILY, 'default_scope' => SNAPSHOT_SCOPE_WORDPRESS, 'retention_type' => 'days');

        foreach ($rules as $key => $valid) {
            if (RiseupBooleanHelpers::isNotInList($settings[$key], $valid)) {
                $settings[$key] = $defaults[$key];
            }
        }
    }

    /** Clamp numeric fields to valid ranges. */
    private function clampNumericFields(array &$settings) {
        $settings['retention_days']      = max(1, min(365, intval($settings['retention_days'])));
        $settings['retention_count']     = max(1, min(100, intval($settings['retention_count'])));
        $settings['schedule_day']        = max(1, min(28, intval($settings['schedule_day'])));
        $settings['max_snapshot_size_mb'] = max(50, min(2000, intval($settings['max_snapshot_size_mb'])));
        $settings['batch_size']          = max(100, min(10000, intval($settings['batch_size'])));
        $settings['worker_pool_size']    = max(SNAPSHOT_WORKER_POOL_MIN, min(SNAPSHOT_WORKER_POOL_MAX, intval($settings['worker_pool_size'] ?? SNAPSHOT_WORKER_POOL_DEFAULT)));
    }

    /** Cast boolean fields. */
    private function castBooleanFields(array &$settings) {
        $settings['schedule_enabled']        = (bool) $settings['schedule_enabled'];
        $settings['pre_restore_backup']      = (bool) $settings['pre_restore_backup'];
        $settings['require_restore_confirm'] = (bool) $settings['require_restore_confirm'];
    }

    /** Validate storage_mode, schedule_time, and custom_tables. */
    private function validateMiscFields(array &$settings) {
        $valid_storage = array('single', 'per-table');
        if (RiseupBooleanHelpers::isNotInList($settings['storage_mode'] ?? 'per-table', $valid_storage)) {
            $settings['storage_mode'] = 'per-table';
        }

        $isInvalidTime = !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['schedule_time']);
        if ($isInvalidTime) {
            $settings['schedule_time'] = '03:00';
        }

        if (!is_array($settings['custom_tables'])) {
            $settings['custom_tables'] = array();
        }
    }
}
