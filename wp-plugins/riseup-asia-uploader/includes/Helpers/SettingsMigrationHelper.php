<?php
/**
 * SettingsMigrationHelper — One-time wp_options value normalization.
 *
 * Migrates stored snapshot/plugin settings from legacy lowercase/snake_case
 * values to PascalCase enum values. Also renames legacy snake_case wp_options
 * keys to PascalCase. Idempotent — safe to run multiple times.
 *
 * @package RiseupAsia\Helpers
 * @since   2.6.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\OptionNameType;

class SettingsMigrationHelper {
    /** Option key tracking whether migration has run. */
    private const MIGRATION_FLAG = 'riseup_settings_migrated_v2';

    /**
     * Legacy wp_options key → new PascalCase key mapping.
     *
     * @var array<string,string>
     */
    private const KEY_MAP = array(
        'riseup_snapshot_settings'            => 'RiseupSnapshotSettings',
        'riseup_log_retrieval_settings'       => 'RiseupLogRetrievalSettings',
        'riseup_update_settings'              => 'RiseupUpdateSettings',
        'riseup_asia_settings'                => 'RiseupAsiaSettings',
        'riseup_error_notification_settings'  => 'RiseupErrorNotificationSettings',
    );

    /**
     * Value mappings: old lowercase/snake_case → new PascalCase.
     *
     * @var array<string,string>
     */
    private const VALUE_MAP = array(
        // SnapshotProviderType
        'auto'    => 'Auto',
        'native'  => 'Native',
        'wp_reset' => 'WpReset',
        'updraft' => 'Updraft',
        // SnapshotFrequencyType
        'manual'  => 'Manual',
        'hourly'  => 'Hourly',
        'daily'   => 'Daily',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
        // SnapshotScopeType
        'all'       => 'All',
        'wordpress' => 'WordPress',
        'content'   => 'Content',
        'custom'    => 'Custom',
        // RetentionType
        'none'  => 'None',
        'days'  => 'Days',
        'count' => 'Count',
        // StorageModeType
        'single'    => 'Single',
        'per-table' => 'PerTable',
    );

    /**
     * Fields within snapshot settings that hold enum values.
     *
     * @var array<string>
     */
    private const SNAPSHOT_FIELDS = array(
        'preferred_provider',
        'schedule_frequency',
        'default_scope',
        'retention_type',
        'storage_mode',
    );

    /** Run the migration if not already completed. */
    public static function migrateIfNeeded(): void {
        if (get_option(self::MIGRATION_FLAG)) {
            return;
        }

        self::migrateOptionKeys();
        self::migrateSnapshotSettings();
        update_option(self::MIGRATION_FLAG, true);
    }

    /**
     * Rename legacy snake_case wp_options keys to PascalCase.
     *
     * Copies the value from the old key to the new key, then deletes the old key.
     * Skips if the old key does not exist or the new key already has data.
     */
    private static function migrateOptionKeys(): void {
        foreach (self::KEY_MAP as $oldKey => $newKey) {
            $oldValue = get_option($oldKey, null);
            $isOldKeyMissing = ($oldValue === null);

            if ($isOldKeyMissing) {
                continue;
            }

            $newValue = get_option($newKey, null);
            $isNewKeyPresent = ($newValue !== null);

            if ($isNewKeyPresent) {
                delete_option($oldKey);

                continue;
            }

            update_option($newKey, $oldValue);
            delete_option($oldKey);
        }
    }

    /** Normalize snapshot settings values to PascalCase. */
    private static function migrateSnapshotSettings(): void {
        $settings = get_option(
            OptionNameType::SnapshotSettings->value,
            array(),
        );

        $isSettingsEmpty = empty($settings) || !is_array($settings);

        if ($isSettingsEmpty) {
            return;
        }

        $hasChanges = false;

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $hasField = isset($settings[$field]) && is_string($settings[$field]);
            $isMissingField = !$hasField;

            if ($isMissingField) {
                continue;
            }

            $oldValue = $settings[$field];
            $hasMapping = isset(self::VALUE_MAP[$oldValue]);
            $isMissingMapping = !$hasMapping;

            if ($isMissingMapping) {
                continue;
            }

            $settings[$field] = self::VALUE_MAP[$oldValue];
            $hasChanges = true;
        }

        if ($hasChanges) {
            update_option(
                OptionNameType::SnapshotSettings->value,
                $settings,
            );
        }
    }
}
