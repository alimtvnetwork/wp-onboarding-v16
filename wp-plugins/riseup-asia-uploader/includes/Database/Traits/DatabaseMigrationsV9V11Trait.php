<?php
/**
 * DatabaseMigrationsV9V11Trait — Schema migrations v9 through v11.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait DatabaseMigrationsV9V11Trait {

    private function migrateV9ErrorSessions(int $current): void {
        if ($current >= 9) {
            return;
        }

        $this->fileLogger->info('Applying migration v9: error sessions and flash state');

        $errorSessions = TableType::ErrorSessions->value;
        $flashState = TableType::FlashState->value;

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$errorSessions} (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Level TEXT NOT NULL,
            Message TEXT NOT NULL,
            File TEXT,
            Line INTEGER,
            ContextJson TEXT,
            StackTrace TEXT,
            CreatedAt TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxErrorSessions_Level ON {$errorSessions}(Level)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxErrorSessions_CreatedAt ON {$errorSessions}(CreatedAt DESC)");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$flashState} (
            Key TEXT PRIMARY KEY,
            Value TEXT NOT NULL,
            UpdatedAt TEXT NOT NULL
        )");

        $now = DateHelper::nowUtc();
        $this->pdo->exec("INSERT OR IGNORE INTO {$flashState} (Key, Value, UpdatedAt) VALUES ('last_seen_error_id', '0', '{$now}')");
        $this->pdo->exec("INSERT OR IGNORE INTO {$flashState} (Key, Value, UpdatedAt) VALUES ('has_unseen_errors', '0', '{$now}')");

        $this->recordMigration(9);
    }

    private function migrateV10VersionTracking(int $current): void {
        if ($current >= 10) {
            return;
        }

        $this->fileLogger->info('Applying migration v10: plugin version and upload source columns');
        $table   = TableType::Transactions->value;
        $columns = array(
            'PluginVersion' => 'TEXT',
            'UploadSource'  => 'TEXT',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->fileLogger->logDebugException($e, "Column might exist: {$column}");
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxTransactions_PluginVersion ON {$table}(PluginVersion)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxTransactions_UploadSource ON {$table}(UploadSource)");

        $this->recordMigration(10);
    }

    private function migrateV11SnapshotExports(int $current): void {
        if ($current >= 11) {
            return;
        }

        $this->fileLogger->info('Applying migration v11: snapshot exports table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotExports->value . " (
            Id              INTEGER PRIMARY KEY AUTOINCREMENT,
            SnapshotId      INTEGER NOT NULL,
            ZipFilename     TEXT NOT NULL,
            ZipPath         TEXT NOT NULL,
            ZipSize         INTEGER NOT NULL DEFAULT 0,
            IncludedIds     TEXT NOT NULL,
            IncrementalCount INTEGER NOT NULL DEFAULT 0,
            CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
            ExpiresAt       TEXT,
            Status          TEXT NOT NULL DEFAULT '" . SnapshotExportStatusType::Valid->value . "',
            UNIQUE(SnapshotId)
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshotExports_SnapshotId ON " . TableType::SnapshotExports->value . "(SnapshotId)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxSnapshotExports_Status ON " . TableType::SnapshotExports->value . "(Status)");

        $this->recordMigration(11);
    }
}
