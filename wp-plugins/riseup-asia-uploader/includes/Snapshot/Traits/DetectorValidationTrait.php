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
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Helpers\BooleanHelpers;

trait DetectorValidationTrait {
    private function validateSettings(array $settings): array {
        $settings = SettingsKeyType::migrateArray($settings);
        $this->validateEnumFields($settings);
        $this->clampNumericFields($settings);
        $this->castBooleanFields($settings);
        $this->validateMiscFields($settings);

        return $settings;
    }

    private function validateEnumFields(array &$settings): void {
        $rules = array(
            SettingsKeyType::PreferredProvider->value => array(
                SnapshotProviderType::Auto->value,
                SnapshotProviderType::WpReset->value,
                SnapshotProviderType::Updraft->value,
                SnapshotProviderType::Native->value,
            ),
            SettingsKeyType::ScheduleFrequency->value => array(
                SnapshotFrequencyType::Manual->value,
                SnapshotFrequencyType::Hourly->value,
                SnapshotFrequencyType::Daily->value,
                SnapshotFrequencyType::Weekly->value,
                SnapshotFrequencyType::Monthly->value,
            ),
            SettingsKeyType::DefaultScope->value => array(
                SnapshotScopeType::All->value,
                SnapshotScopeType::WordPress->value,
                SnapshotScopeType::Content->value,
                SnapshotScopeType::Custom->value,
            ),
            SettingsKeyType::RetentionType->value => array(
                RetentionType::Days->value,
                RetentionType::Count->value,
                RetentionType::None->value,
            ),
        );
        $defaults = array(
            SettingsKeyType::PreferredProvider->value  => SnapshotProviderType::Auto->value,
            SettingsKeyType::ScheduleFrequency->value  => SnapshotFrequencyType::Daily->value,
            SettingsKeyType::DefaultScope->value       => SnapshotScopeType::WordPress->value,
            SettingsKeyType::RetentionType->value      => RetentionType::Days->value,
        );

        foreach ($rules as $key => $valid) {
            if (BooleanHelpers::isAbsentFromList($settings[$key], $valid)) {
                $settings[$key] = $defaults[$key];
            }
        }
    }

    private function clampNumericFields(array &$settings): void {
        $settings[SettingsKeyType::RetentionDays->value]      = max(1, min(365, intval($settings[SettingsKeyType::RetentionDays->value])));
        $settings[SettingsKeyType::RetentionCount->value]     = max(1, min(100, intval($settings[SettingsKeyType::RetentionCount->value])));
        $settings[SettingsKeyType::ScheduleDay->value]        = max(1, min(28, intval($settings[SettingsKeyType::ScheduleDay->value])));
        $settings[SettingsKeyType::MaxSnapshotSizeMb->value]  = max(50, min(2000, intval($settings[SettingsKeyType::MaxSnapshotSizeMb->value])));
        $settings[SettingsKeyType::BatchSize->value]          = max(100, min(10000, intval($settings[SettingsKeyType::BatchSize->value])));
        $settings[SettingsKeyType::WorkerPoolSize->value]     = max(SnapshotConfigType::WorkerPoolMin->value, min(SnapshotConfigType::workerPoolMax(), intval($settings[SettingsKeyType::WorkerPoolSize->value] ?? SnapshotConfigType::WorkerPoolDefault->value)));
    }

    private function castBooleanFields(array &$settings): void {
        $settings[SettingsKeyType::ScheduleEnabled->value]       = (bool) $settings[SettingsKeyType::ScheduleEnabled->value];
        $settings[SettingsKeyType::PreRestoreBackup->value]      = (bool) $settings[SettingsKeyType::PreRestoreBackup->value];
        $settings[SettingsKeyType::RequireRestoreConfirm->value] = (bool) $settings[SettingsKeyType::RequireRestoreConfirm->value];
    }

    private function validateMiscFields(array &$settings): void {
        $validStorage = StorageModeType::validValues();

        if (BooleanHelpers::isAbsentFromList($settings[SettingsKeyType::StorageMode->value] ?? StorageModeType::PerTable->value, $validStorage)) {
            $settings[SettingsKeyType::StorageMode->value] = StorageModeType::PerTable->value;
        }

        $isInvalidTime = (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings[SettingsKeyType::ScheduleTime->value]) === 0);

        if ($isInvalidTime) {
            $settings[SettingsKeyType::ScheduleTime->value] = '03:00';
        }

        $isCustomTablesInvalid = (is_array($settings[SettingsKeyType::CustomTables->value]) === false);

        if ($isCustomTablesInvalid) {
            $settings[SettingsKeyType::CustomTables->value] = array();
        }
    }
}
