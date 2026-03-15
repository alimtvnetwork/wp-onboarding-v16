<?php
/**
 * DatabaseMigrationsV17Trait — Create CloudStorageAccounts table.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.15.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV17Trait {

    private function migrateV17CloudStorageAccounts(int $current): void {
        if ($current >= 17) {
            return;
        }

        $this->fileLogger->info('Applying migration v17: CloudStorageAccounts table');
        $table = TableType::CloudStorageAccounts->value;

        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                Id              INTEGER PRIMARY KEY AUTOINCREMENT,
                Provider        TEXT    NOT NULL,
                AccountLabel    TEXT    NOT NULL,
                Username        TEXT    DEFAULT '',
                Email           TEXT    DEFAULT '',
                AccessToken     TEXT    NOT NULL,
                RefreshToken    TEXT    DEFAULT '',
                TokenExpiresAt  TEXT    DEFAULT '',
                BaseUrl         TEXT    DEFAULT '',
                RepoName        TEXT    DEFAULT '',
                RepoOwner       TEXT    DEFAULT '',
                FolderId        TEXT    DEFAULT '',
                FolderName      TEXT    DEFAULT '',
                IsActive        INTEGER NOT NULL DEFAULT 1,
                LastUsedAt      TEXT    DEFAULT '',
                LastError       TEXT    DEFAULT '',
                CreatedAt       TEXT    NOT NULL DEFAULT (datetime('now')),
                UpdatedAt       TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        SQL;

        $this->pdo->exec($sql);

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csa_provider ON {$table}(Provider)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csa_active ON {$table}(IsActive)");

        $this->recordMigration(17);
    }
}
