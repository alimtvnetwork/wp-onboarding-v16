<?php
/**
 * DatabaseMigrationsV22Trait — Add ContentHash column to SnapshotExports
 * for hash-based cache invalidation when source files change.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.18.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV22Trait {

    private function migrateV22SnapshotExportsContentHash(int $current): void
    {
        if ($current >= 22) {
            return;
        }

        $this->fileLogger->info('Applying migration v22: SnapshotExports ContentHash column');

        $table = TableType::SnapshotExports->value;

        $contentHashSql = sprintf(
            "ALTER TABLE %s ADD COLUMN ContentHash TEXT NOT NULL DEFAULT ''",
            $table,
        );

        $this->execIfColumnMissing($table, 'ContentHash', $contentHashSql);

        $this->recordMigration(22);
    }
}
