<?php
/**
 * DatabaseMigrationsV21Trait — Add folder_path, chunk_count, total_size to
 * CloudStorageBackupHistory and deprecate branch_name, commit_sha.
 *
 * SQLite < 3.35.0 does not support DROP COLUMN, so the old columns are left
 * in place but ignored by application code going forward.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.17.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV21Trait {

    private function migrateV21CloudStorageBackupHistoryFolderColumns(int $current): void
    {
        if ($current >= 21) {
            return;
        }

        $this->fileLogger->info('Applying migration v21: BackupHistory folder columns');

        $table = TableType::CloudStorageBackupHistory->value;

        // ── Add FolderPath ──────────────────────────────────────────
        $folderPathSql = sprintf(
            "ALTER TABLE %s ADD COLUMN FolderPath TEXT NOT NULL DEFAULT ''",
            $table,
        );

        $this->execIfColumnMissing($table, 'FolderPath', $folderPathSql);

        // ── Add ChunkCount ──────────────────────────────────────────
        $chunkCountSql = sprintf(
            "ALTER TABLE %s ADD COLUMN ChunkCount INTEGER NOT NULL DEFAULT 0",
            $table,
        );

        $this->execIfColumnMissing($table, 'ChunkCount', $chunkCountSql);

        // ── Add TotalSize (bytes across all chunks) ─────────────────
        $totalSizeSql = sprintf(
            "ALTER TABLE %s ADD COLUMN TotalSize INTEGER NOT NULL DEFAULT 0",
            $table,
        );

        $this->execIfColumnMissing($table, 'TotalSize', $totalSizeSql);

        // ── Index on FolderPath for rotation lookups ────────────────
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_csbh_folder ON {$table}(FolderPath)");

        // ── Drop deprecated BranchName index (index only, column stays) ─
        $this->pdo->exec("DROP INDEX IF EXISTS idx_csbh_branch");

        $this->recordMigration(21);
    }
}
