<?php
/**
 * DatabaseMigrationsV19Trait — Add RepoSelectionMode, DefaultBranch to accounts
 * and backup strategy/schedule columns to CloudStorageSettings.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.16.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV19Trait {

    private function migrateV19CloudStorageBackupColumns(int $current): void {
        if ($current >= 19) {
            return;
        }

        $this->fileLogger->info('Applying migration v19: Cloud storage backup columns');

        $accounts = TableType::CloudStorageAccounts->value;
        $settings = TableType::CloudStorageSettings->value;

        // ── Accounts: RepoSelectionMode + DefaultBranch ─────────────
        $repoModeSql = sprintf(
            "ALTER TABLE %s ADD COLUMN RepoSelectionMode TEXT NOT NULL DEFAULT 'create'",
            $accounts,
        );

        $this->execIfColumnMissing($accounts, 'RepoSelectionMode', $repoModeSql);

        $branchSql = sprintf(
            "ALTER TABLE %s ADD COLUMN DefaultBranch TEXT NOT NULL DEFAULT 'main'",
            $accounts,
        );

        $this->execIfColumnMissing($accounts, 'DefaultBranch', $branchSql);

        // ── Settings: Backup strategy + schedule columns ────────────
        $strategySql = sprintf(
            "ALTER TABLE %s ADD COLUMN BackupType TEXT NOT NULL DEFAULT 'FullOnly'",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'BackupType', $strategySql);

        $fullScheduleSql = sprintf(
            "ALTER TABLE %s ADD COLUMN FullBackupSchedule TEXT NOT NULL DEFAULT 'Weekly'",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'FullBackupSchedule', $fullScheduleSql);

        $incrScheduleSql = sprintf(
            "ALTER TABLE %s ADD COLUMN IncrementalBackupSchedule TEXT NOT NULL DEFAULT 'Daily'",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'IncrementalBackupSchedule', $incrScheduleSql);

        $fullDaySql = sprintf(
            "ALTER TABLE %s ADD COLUMN FullBackupDayOfWeek INTEGER NOT NULL DEFAULT 0",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'FullBackupDayOfWeek', $fullDaySql);

        $fullTimeSql = sprintf(
            "ALTER TABLE %s ADD COLUMN FullBackupTimeUtc TEXT NOT NULL DEFAULT '02:00'",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'FullBackupTimeUtc', $fullTimeSql);

        $incrTimeSql = sprintf(
            "ALTER TABLE %s ADD COLUMN IncrementalBackupTimeUtc TEXT NOT NULL DEFAULT '02:00'",
            $settings,
        );

        $this->execIfColumnMissing($settings, 'IncrementalBackupTimeUtc', $incrTimeSql);

        $this->recordMigration(19);
    }
}
