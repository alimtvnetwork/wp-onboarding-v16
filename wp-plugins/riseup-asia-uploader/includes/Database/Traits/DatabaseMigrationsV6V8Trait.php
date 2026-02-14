<?php
/**
 * DatabaseMigrationsV6V8Trait — Schema migrations v6 through v8.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV6V8Trait {

    private function migrate_v6_remote_plugins_cache(int $current): void {
        if ($current >= 6) {
            return;
        }

        $this->fileLogger->info('Applying migration v6: remote plugins cache');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS remote_plugins_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            data_json TEXT NOT NULL,
            fetched_at TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_rpc_site_id ON remote_plugins_cache(site_id)");

        $this->record_migration(6);
    }

    private function migrate_v7_file_hash_cache(int $current): void {
        if ($current >= 7) {
            return;
        }

        $this->fileLogger->info('Applying migration v7: file hash cache table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::FileCache->value . " (
            plugin_slug TEXT NOT NULL,
            relative_path TEXT NOT NULL,
            md5_hash TEXT NOT NULL,
            modified_at TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            cached_at TEXT NOT NULL,
            PRIMARY KEY (plugin_slug, relative_path)
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_file_cache_slug ON " . TableType::FileCache->value . "(plugin_slug)");

        $this->record_migration(7);
    }

    private function migrate_v8_snapshot_settings(int $current): void {
        if ($current >= 8) {
            return;
        }

        $this->fileLogger->info('Applying migration v8: snapshot settings table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotSettings->value . " (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'string',
            updated_at TEXT NOT NULL
        )");

        $defaults = array(
            array('snapshot.mode',             'per_table',    'string'),
            array('snapshot.backup_type',      'incremental',  'string'),
            array('snapshot.worker_count',     '10',           'int'),
            array('snapshot.storage_path',     'snapshots/',   'string'),
            array('snapshot.include_plugins',  '1',            'bool'),
            array('snapshot.plugin_selection', 'all',          'string'),
            array('snapshot.retention_days',   '30',           'int'),
            array('snapshot.retention_count',  '10',           'int'),
            array('snapshot.compression',      '1',            'bool'),
            array('snapshot.batch_size',       '1000',         'int'),
            array('snapshot.provider',         'auto',         'string'),
            array('snapshot.scope',            'wordpress',    'string'),
            array('snapshot.frequency',        'manual',       'string'),
            array('snapshot.schedule_time',    '03:00',        'string'),
            array('snapshot.pre_restore_backup', '1',          'bool'),
        );

        $now  = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO " . TableType::SnapshotSettings->value . " (key, value, type, updated_at) VALUES (?, ?, ?, ?)");

        foreach ($defaults as $row) {
            $stmt->execute(array($row[0], $row[1], $row[2], $now));
        }

        $this->record_migration(8);
    }
}