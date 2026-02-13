<?php
/**
 * Database Migrations Late Trait
 *
 * Schema migrations v6 through v11 (cache, settings, errors, exports).
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsLateTrait {

    /**
     * Migration v6: Remote plugins cache table.
     */
    private function migrate_v6_remote_plugins_cache($current) {
        if ($current >= 6) {
            return;
        }

        $this->file_logger->info('Applying migration v6: remote plugins cache');

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

    /**
     * Migration v7: File hash cache table.
     */
    private function migrate_v7_file_hash_cache($current) {
        if ($current >= 7) {
            return;
        }

        $this->file_logger->info('Applying migration v7: file hash cache table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_FILE_CACHE . " (
            plugin_slug TEXT NOT NULL,
            relative_path TEXT NOT NULL,
            md5_hash TEXT NOT NULL,
            modified_at TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            cached_at TEXT NOT NULL,
            PRIMARY KEY (plugin_slug, relative_path)
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_file_cache_slug ON " . self::TABLE_FILE_CACHE . "(plugin_slug)");

        $this->record_migration(7);
    }

    /**
     * Migration v8: Snapshot settings key-value store.
     */
    private function migrate_v8_snapshot_settings($current) {
        if ($current >= 8) {
            return;
        }

        $this->file_logger->info('Applying migration v8: snapshot settings table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS snapshot_settings (
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
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO snapshot_settings (key, value, type, updated_at) VALUES (?, ?, ?, ?)");

        foreach ($defaults as $row) {
            $stmt->execute(array($row[0], $row[1], $row[2], $now));
        }

        $this->record_migration(8);
    }

    /**
     * Migration v9: Error sessions table + flash state.
     */
    private function migrate_v9_error_sessions($current) {
        if ($current >= 9) {
            return;
        }

        $this->file_logger->info('Applying migration v9: error sessions and flash state');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS error_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            level TEXT NOT NULL,
            message TEXT NOT NULL,
            file TEXT,
            line INTEGER,
            context_json TEXT,
            stack_trace TEXT,
            created_at TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_error_sessions_level ON error_sessions(level)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_error_sessions_created ON error_sessions(created_at DESC)");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS flash_state (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $this->pdo->exec("INSERT OR IGNORE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $this->pdo->exec("INSERT OR IGNORE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        $this->record_migration(9);
    }

    /**
     * Migration v10: Plugin version and upload source tracking.
     */
    private function migrate_v10_version_tracking($current) {
        if ($current >= 10) {
            return;
        }

        $this->file_logger->info('Applying migration v10: plugin version and upload source columns');
        $table   = self::TABLE_TRANSACTIONS;
        $columns = array(
            'plugin_version' => 'TEXT',
            'upload_source'  => 'TEXT',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->file_logger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_plugin_version ON {$table}(plugin_version)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_upload_source ON {$table}(upload_source)");

        $this->record_migration(10);
    }

    /**
     * Migration v11: Snapshot ZIP export cache table.
     */
    private function migrate_v11_snapshot_exports($current) {
        if ($current >= 11) {
            return;
        }

        $this->file_logger->info('Applying migration v11: snapshot exports table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOT_EXPORTS . " (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            snapshot_id     INTEGER NOT NULL,
            zip_filename    TEXT NOT NULL,
            zip_path        TEXT NOT NULL,
            zip_size        INTEGER NOT NULL DEFAULT 0,
            included_ids    TEXT NOT NULL,
            incremental_count INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT NOT NULL DEFAULT (datetime('now')),
            expires_at      TEXT,
            status          TEXT NOT NULL DEFAULT '" . self::SNAPSHOT_EXPORT_STATUS_VALID . "',
            UNIQUE(snapshot_id)
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_exports_snapshot ON " . self::TABLE_SNAPSHOT_EXPORTS . "(snapshot_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_exports_status ON " . self::TABLE_SNAPSHOT_EXPORTS . "(status)");

        $this->record_migration(11);
    }
}
