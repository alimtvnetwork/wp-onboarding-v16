<?php
/**
 * DatabaseMigrationsV4V5Trait — Schema migrations v4 and v5.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsV4V5Trait {

    private function migrate_v4_source_machine(int $current): void {
        if ($current >= 4) {
            return;
        }

        $this->fileLogger->info('Applying migration v4: source machine tracking');
        $table = self::TABLE_TRANSACTIONS;

        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN source_machine TEXT");
        } catch (PDOException $e) {
            $this->fileLogger->debug("Column might exist: source_machine", array('error' => $e->getMessage()));
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_machine ON {$table}(source_machine)");

        $this->record_migration(4);
    }

    private function migrate_v5_snapshot_tables(int $current): void {
        if ($current >= 5) {
            return;
        }

        $this->fileLogger->info('Applying migration v5: snapshot system tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOTS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sequence INTEGER NOT NULL,
            filename TEXT NOT NULL UNIQUE,
            filepath TEXT NOT NULL,
            created_at TEXT NOT NULL,
            completed_at TEXT,
            status TEXT DEFAULT 'pending',
            provider TEXT NOT NULL,
            scope TEXT NOT NULL,
            tables_json TEXT,
            table_counts_json TEXT,
            total_rows INTEGER,
            file_size INTEGER,
            duration_ms INTEGER,
            triggered_by TEXT,
            error_message TEXT,
            metadata_json TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOT_PROGRESS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            snapshot_id INTEGER NOT NULL,
            table_name TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            rows_total INTEGER,
            rows_exported INTEGER DEFAULT 0,
            started_at TEXT,
            completed_at TEXT,
            error_message TEXT,
            FOREIGN KEY (snapshot_id) REFERENCES snapshots(id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_created ON " . self::TABLE_SNAPSHOTS . "(created_at DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_status ON " . self::TABLE_SNAPSHOTS . "(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_provider ON " . self::TABLE_SNAPSHOTS . "(provider)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_progress_snapshot ON " . self::TABLE_SNAPSHOT_PROGRESS . "(snapshot_id)");

        $this->record_migration(5);
    }
}
