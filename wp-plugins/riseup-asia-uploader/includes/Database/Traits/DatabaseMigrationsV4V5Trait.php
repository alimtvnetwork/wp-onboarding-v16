<?php
/**
 * DatabaseMigrationsV4V5Trait — Schema migrations v4 and v5.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDOException;

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV4V5Trait {

    private function migrateV4SourceMachine(int $current): void {
        if ($current >= 4) {
            return;
        }

        $this->fileLogger->info('Applying migration v4: source machine tracking');
        $table = TableType::Transactions->value;

        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN SourceMachine TEXT");
        } catch (PDOException $e) {
            $this->fileLogger->logDebugException($e, 'Column might exist: SourceMachine');
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxTransactions_SourceMachine ON {$table}(SourceMachine)");

        $this->recordMigration(4);
    }

    private function migrateV5SnapshotTables(int $current): void {
        if ($current >= 5) {
            return;
        }

        $this->fileLogger->info('Applying migration v5: snapshot system tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::Snapshots->value . " (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Sequence INTEGER NOT NULL,
            Filename TEXT NOT NULL UNIQUE,
            Filepath TEXT NOT NULL,
            CreatedAt TEXT NOT NULL,
            CompletedAt TEXT,
            Status TEXT DEFAULT 'Pending',
            Provider TEXT NOT NULL,
            Scope TEXT NOT NULL,
            TablesJson TEXT,
            TableCountsJson TEXT,
            TotalRows INTEGER,
            FileSize INTEGER,
            DurationMs INTEGER,
            TriggeredBy TEXT,
            ErrorMessage TEXT,
            MetadataJson TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotProgress->value . " (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            SnapshotId INTEGER NOT NULL,
            TableName TEXT NOT NULL,
            Status TEXT DEFAULT 'Pending',
            RowsTotal INTEGER,
            RowsExported INTEGER DEFAULT 0,
            StartedAt TEXT,
            CompletedAt TEXT,
            ErrorMessage TEXT,
            FOREIGN KEY (SnapshotId) REFERENCES " . TableType::Snapshots->value . "(Id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshots_CreatedAt ON " . TableType::Snapshots->value . "(CreatedAt DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshots_Status ON " . TableType::Snapshots->value . "(Status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshots_Provider ON " . TableType::Snapshots->value . "(Provider)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshotProgress_SnapshotId ON " . TableType::SnapshotProgress->value . "(SnapshotId)");

        $this->recordMigration(5);
    }
}
