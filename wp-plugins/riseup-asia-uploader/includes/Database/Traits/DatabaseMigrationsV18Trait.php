<?php
/**
 * DatabaseMigrationsV18Trait — Create CloudStorageSettings table with default seeds.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.15.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV18Trait {

    private function migrateV18CloudStorageSettings(int $current): void {
        if ($current >= 18) {
            return;
        }

        $this->fileLogger->info('Applying migration v18: CloudStorageSettings table');
        $table = TableType::CloudStorageSettings->value;

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                Id                INTEGER PRIMARY KEY AUTOINCREMENT,
                Provider          TEXT    NOT NULL UNIQUE,
                IsEnabled         INTEGER NOT NULL DEFAULT 0,
                AutoBackupEnabled INTEGER NOT NULL DEFAULT 0,
                DefaultAccountId  INTEGER DEFAULT NULL,
                RetentionCount    INTEGER NOT NULL DEFAULT 10,
                RotationEnabled   INTEGER NOT NULL DEFAULT 1,
                BackupPrefix      TEXT    NOT NULL DEFAULT 'wp-backup',
                CreatedAt         TEXT    NOT NULL DEFAULT (datetime('now')),
                UpdatedAt         TEXT    NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (DefaultAccountId) REFERENCES CloudStorageAccounts(Id) ON DELETE SET NULL
            )
        SQL;

        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_css_provider ON {$table}(Provider)");

        $seedSql = <<<SQL
            INSERT OR IGNORE INTO {$table} (Provider, IsEnabled, RetentionCount, BackupPrefix)
            VALUES
                ('GitHub', 0, 10, 'wp-backup'),
                ('GitLab', 0, 10, 'wp-backup'),
                ('GoogleDrive', 0, 10, 'wp-backup')
        SQL;

        $this->pdo->exec($seedSql);

        $this->recordMigration(18);
    }
}
