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

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV4V5Trait {

    private function migrateV4SourceMachine(int $current): void {
        if ($current >= 4) {
            return;
        }

        $this->fileLogger->info('Applying migration v4: source machine tracking');
        $table = TableType::Transactions->value;

        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN source_machine TEXT");
        } catch (PDOException $e) {
            $this->fileLogger->debug("Column might exist: source_machine", array('error' => $e->getMessage()));
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_machine ON {$table}(source_machine)");

        $this->recordMigration(4);
    }

    private function migrateV5SnapshotTables(int $current): void {
        if ($current >= 5) {
            return;
        }

        $this->fileLogger->info('Applying migration v5: snapshot system tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::Snapshots->value . " (
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

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotProgress->value . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            snapshot_id INTEGER NOT NULL,
            table_name TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            rows_total INTEGER,
            rows_exported INTEGER DEFAULT 0,
            started_at TEXT,
            completed_at TEXT,
            error_message TEXT,
            FOREIGN KEY (snapshot_id) REFERENCES " . TableType::Snapshots->value . "(id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_created ON " . TableType::Snapshots->value . "(created_at DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_status ON " . TableType::Snapshots->value . "(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_provider ON " . TableType::Snapshots->value . "(provider)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_progress_snapshot ON " . TableType::SnapshotProgress->value . "(snapshot_id)");

        $this->recordMigration(5);
    }
}
