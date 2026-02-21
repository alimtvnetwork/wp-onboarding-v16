<?php
/**
 * DatabaseMigrationsV6V8Trait — Schema migrations v6 through v8.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait DatabaseMigrationsV6V8Trait {

    private function migrateV6RemotePluginsCache(int $current): void {
        if ($current >= 6) {
            return;
        }

        $this->fileLogger->info('Applying migration v6: remote plugins cache');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::RemotePluginsCache->value . " (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            SiteId INTEGER NOT NULL,
            DataJson TEXT NOT NULL,
            FetchedAt TEXT NOT NULL,
            ExpiresAt TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxRemotePluginsCache_SiteId ON " . TableType::RemotePluginsCache->value . "(SiteId)");

        $this->recordMigration(6);
    }

    private function migrateV7FileHashCache(int $current): void {
        if ($current >= 7) {
            return;
        }

        $this->fileLogger->info('Applying migration v7: file hash cache table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::FileCache->value . " (
            PluginSlug TEXT NOT NULL,
            RelativePath TEXT NOT NULL,
            Md5Hash TEXT NOT NULL,
            ModifiedAt TEXT NOT NULL,
            FileSize INTEGER NOT NULL DEFAULT 0,
            CachedAt TEXT NOT NULL,
            PRIMARY KEY (PluginSlug, RelativePath)
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxFileCache_PluginSlug ON " . TableType::FileCache->value . "(PluginSlug)");

        $this->recordMigration(7);
    }

    private function migrateV8SnapshotSettings(int $current): void {
        if ($current >= 8) {
            return;
        }

        $this->fileLogger->info('Applying migration v8: snapshot settings table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotSettings->value . " (
            Key TEXT PRIMARY KEY,
            Value TEXT NOT NULL,
            Type TEXT NOT NULL DEFAULT 'string',
            UpdatedAt TEXT NOT NULL
        )");

        // Migration defaults use literal values matching their corresponding enums:
        // 'PerTable' = SnapshotWorkerModeType::PerTable, 'Incremental' = SnapshotModeType::Incremental,
        // 'All' = PluginSelectionType::All, 'Auto' = SnapshotProviderType::Auto,
        // 'WordPress' = SnapshotScopeType::WordPress, 'Manual' = SnapshotFrequencyType::Manual.
        // Literals are required here because enum ->value access is not permitted in array declarations.
        $defaults = array(
            array('snapshot.mode',             'PerTable',     'string'),
            array('snapshot.backup_type',      'Incremental',  'string'),
            array('snapshot.worker_count',     '10',           'int'),
            array('snapshot.storage_path',     'snapshots/',   'string'),
            array('snapshot.include_plugins',  '1',            'bool'),
            array('snapshot.plugin_selection', 'All',          'string'),
            array('snapshot.retention_days',   '30',           'int'),
            array('snapshot.retention_count',  '10',           'int'),
            array('snapshot.compression',      '1',            'bool'),
            array('snapshot.batch_size',       '1000',         'int'),
            array('snapshot.provider',         'Auto',         'string'),
            array('snapshot.scope',            'WordPress',    'string'),
            array('snapshot.frequency',        'Manual',       'string'),
            array('snapshot.schedule_time',    '03:00',        'string'),
            array('snapshot.pre_restore_backup', '1',          'bool'),
        );

        $now  = DateHelper::nowUtc();
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO " . TableType::SnapshotSettings->value . " (Key, Value, Type, UpdatedAt) VALUES (?, ?, ?, ?)");

        foreach ($defaults as $row) {
            $stmt->execute(array($row[0], $row[1], $row[2], $now));
        }

        $this->recordMigration(8);
    }
}
