<?php
/**
 * DatabaseMigrationsV9V11Trait — Schema migrations v9 through v11.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsV9V11Trait {

    private function migrate_v9_error_sessions(int $current): void {
        if ($current >= 9) {
            return;
        }

        $this->fileLogger->info('Applying migration v9: error sessions and flash state');

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

    private function migrate_v10_version_tracking(int $current): void {
        if ($current >= 10) {
            return;
        }

        $this->fileLogger->info('Applying migration v10: plugin version and upload source columns');
        $table   = self::TABLE_TRANSACTIONS;
        $columns = array(
            'plugin_version' => 'TEXT',
            'upload_source'  => 'TEXT',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->fileLogger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_plugin_version ON {$table}(plugin_version)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_upload_source ON {$table}(upload_source)");

        $this->record_migration(10);
    }

    private function migrate_v11_snapshot_exports(int $current): void {
        if ($current >= 11) {
            return;
        }

        $this->fileLogger->info('Applying migration v11: snapshot exports table');

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
