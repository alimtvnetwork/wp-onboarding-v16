<?php
/**
 * SettingsKeyType — Typed, PascalCase settings keys for snapshot configuration.
 *
 * Replaces legacy snake_case keys stored in WordPress options and SQLite.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SettingsKeyType: string
{
    case PreferredProvider     = 'PreferredProvider';
    case ScheduleEnabled       = 'ScheduleEnabled';
    case ScheduleFrequency     = 'ScheduleFrequency';
    case ScheduleTime          = 'ScheduleTime';
    case ScheduleDay           = 'ScheduleDay';
    case DefaultScope          = 'DefaultScope';
    case CustomTables          = 'CustomTables';
    case RetentionType         = 'RetentionType';
    case RetentionDays         = 'RetentionDays';
    case RetentionCount        = 'RetentionCount';
    case PreRestoreBackup      = 'PreRestoreBackup';
    case RequireRestoreConfirm = 'RequireRestoreConfirm';
    case MaxSnapshotSizeMb     = 'MaxSnapshotSizeMb';
    case BatchSize             = 'BatchSize';
    case WorkerPoolSize        = 'WorkerPoolSize';
    case StorageMode           = 'StorageMode';
    case Mode                  = 'Mode';
    case BackupType            = 'BackupType';
    case WorkerCount           = 'WorkerCount';
    case StoragePath           = 'StoragePath';
    case IncludePlugins        = 'IncludePlugins';
    case PluginSelection       = 'PluginSelection';
    case Compression           = 'Compression';
    case Provider              = 'Provider';
    case Scope                 = 'Scope';
    case Frequency             = 'Frequency';

    /**
     * Map from legacy snake_case keys to PascalCase enum cases.
     *
     * @return array<string, self>
     */
    public static function legacyMap(): array {
        return array(
            'preferred_provider'     => self::PreferredProvider,
            'schedule_enabled'       => self::ScheduleEnabled,
            'schedule_frequency'     => self::ScheduleFrequency,
            'schedule_time'          => self::ScheduleTime,
            'schedule_day'           => self::ScheduleDay,
            'default_scope'          => self::DefaultScope,
            'custom_tables'          => self::CustomTables,
            'retention_type'         => self::RetentionType,
            'retention_days'         => self::RetentionDays,
            'retention_count'        => self::RetentionCount,
            'pre_restore_backup'     => self::PreRestoreBackup,
            'require_restore_confirm' => self::RequireRestoreConfirm,
            'max_snapshot_size_mb'   => self::MaxSnapshotSizeMb,
            'batch_size'             => self::BatchSize,
            'worker_pool_size'       => self::WorkerPoolSize,
            'storage_mode'           => self::StorageMode,
            'mode'                   => self::Mode,
            'backup_type'            => self::BackupType,
            'worker_count'           => self::WorkerCount,
            'storage_path'           => self::StoragePath,
            'include_plugins'        => self::IncludePlugins,
            'plugin_selection'       => self::PluginSelection,
            'compression'            => self::Compression,
            'provider'               => self::Provider,
            'scope'                  => self::Scope,
            'frequency'              => self::Frequency,
        );
    }

    /**
     * Migrate an array from legacy snake_case keys to PascalCase.
     *
     * @param array $data Array with potentially legacy keys.
     * @return array Array with PascalCase keys.
     */
    public static function migrateArray(array $data): array {
        $map = self::legacyMap();
        $migrated = array();

        foreach ($data as $key => $value) {
            $enumCase = $map[$key] ?? self::tryFrom($key);

            if ($enumCase instanceof self) {
                $migrated[$enumCase->value] = $value;
            } else {
                $migrated[$key] = $value;
            }
        }

        return $migrated;
    }

    /**
     * Check whether a given key is a legacy snake_case key.
     */
    public static function isLegacyKey(string $key): bool {
        return isset(self::legacyMap()[$key]);
    }

    public function isEqual(self $other): bool { return $this === $other; }
}
