<?php
/**
 * DetectorValidationTrait — settings validation and sanitization helpers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Helpers\BooleanHelpers;

trait DetectorValidationTrait {

    private function validateSettings(array $settings): array {
        $this->validateEnumFields($settings);
        $this->clampNumericFields($settings);
        $this->castBooleanFields($settings);
        $this->validateMiscFields($settings);

        return $settings;
    }

    private function validateEnumFields(array &$settings): void {
        $rules = array(
            'preferred_provider' => array(SnapshotProviderType::Auto->value, SnapshotProviderType::WpReset->value, SnapshotProviderType::Updraft->value, SnapshotProviderType::Native->value),
            'schedule_frequency' => array(SnapshotFrequencyType::Manual->value, SnapshotFrequencyType::Daily->value, SnapshotFrequencyType::Weekly->value, SnapshotFrequencyType::Monthly->value),
            'default_scope'      => array(SnapshotScopeType::All->value, SnapshotScopeType::WordPress->value, SnapshotScopeType::Content->value, SnapshotScopeType::Custom->value),
            'retention_type'     => array(RetentionType::Days->value, RetentionType::Count->value, RetentionType::None->value),
        );
        $defaults = array('preferred_provider' => SnapshotProviderType::Auto->value, 'schedule_frequency' => SnapshotFrequencyType::Daily->value, 'default_scope' => SnapshotScopeType::WordPress->value, 'retention_type' => RetentionType::Days->value);

        foreach ($rules as $key => $valid) {
            if (BooleanHelpers::isAbsentFromList($settings[$key], $valid)) {
                $settings[$key] = $defaults[$key];
            }
        }
    }

    private function clampNumericFields(array &$settings): void {
        $settings['retention_days']      = max(1, min(365, intval($settings['retention_days'])));
        $settings['retention_count']     = max(1, min(100, intval($settings['retention_count'])));
        $settings['schedule_day']        = max(1, min(28, intval($settings['schedule_day'])));
        $settings['max_snapshot_size_mb'] = max(50, min(2000, intval($settings['max_snapshot_size_mb'])));
        $settings['batch_size']          = max(100, min(10000, intval($settings['batch_size'])));
        $settings['worker_pool_size']    = max(\RiseupAsia\Enums\SnapshotConfigType::WorkerPoolMin->value, min(\RiseupAsia\Enums\SnapshotConfigType::WorkerPoolMax->value, intval($settings['worker_pool_size'] ?? \RiseupAsia\Enums\SnapshotConfigType::WorkerPoolDefault->value)));
    }

    private function castBooleanFields(array &$settings): void {
        $settings['schedule_enabled']        = (bool) $settings['schedule_enabled'];
        $settings['pre_restore_backup']      = (bool) $settings['pre_restore_backup'];
        $settings['require_restore_confirm'] = (bool) $settings['require_restore_confirm'];
    }

    private function validateMiscFields(array &$settings): void {
        $valid_storage = StorageModeType::validValues();
        if (BooleanHelpers::isAbsentFromList($settings['storage_mode'] ?? StorageModeType::PerTable->value, $valid_storage)) {
            $settings['storage_mode'] = StorageModeType::PerTable->value;
        }

        $isInvalidTime = (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['schedule_time']) === 0);
        if ($isInvalidTime) {
            $settings['schedule_time'] = '03:00';
        }

        $isCustomTablesInvalid = (is_array($settings['custom_tables']) === false);
        if ($isCustomTablesInvalid) {
            $settings['custom_tables'] = array();
        }
    }
}
