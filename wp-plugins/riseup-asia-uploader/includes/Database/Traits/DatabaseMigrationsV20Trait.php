<?php
/**
 * DatabaseMigrationsV20Trait — Create CloudStorageBackupHistory table.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.16.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV20Trait {

    private function migrateV20CloudStorageBackupHistory(int $current): void {
        if ($current >= 20) {
            return;
        }

        $this->fileLogger->info('Applying migration v20: CloudStorageBackupHistory table');

        $table    = TableType::CloudStorageBackupHistory->value;
        $accounts = TableType::CloudStorageAccounts->value;

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                Id               INTEGER PRIMARY KEY AUTOINCREMENT,
                AccountId        INTEGER NOT NULL,
                BackupType       TEXT    NOT NULL,
                FileName         TEXT    NOT NULL,
                RemotePath       TEXT    NOT NULL,
                RemoteUrl        TEXT    DEFAULT '',
                CommitSha        TEXT    DEFAULT '',
                BranchName       TEXT    NOT NULL DEFAULT 'main',
                BaseFullBackupId INTEGER DEFAULT NULL,
                FileSizeBytes    INTEGER NOT NULL DEFAULT 0,
                TablesChanged    TEXT    DEFAULT '',
                RowsChanged      INTEGER NOT NULL DEFAULT 0,
                Duration         REAL    NOT NULL DEFAULT 0,
                Status           TEXT    NOT NULL DEFAULT 'Pending',
                ErrorMessage     TEXT    DEFAULT '',
                CreatedAt        TEXT    NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (AccountId) REFERENCES {$accounts}(Id) ON DELETE CASCADE,
                FOREIGN KEY (BaseFullBackupId) REFERENCES {$table}(Id) ON DELETE SET NULL
            )
        SQL;

        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csbh_account  ON {$table}(AccountId)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csbh_type     ON {$table}(BackupType)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csbh_branch   ON {$table}(BranchName)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csbh_created  ON {$table}(CreatedAt)");

        $this->recordMigration(20);
    }
}
