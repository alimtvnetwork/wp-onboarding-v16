<?php
/**
 * DatabaseMigrationsV16Trait — Add PluginVersion column to ErrorSessions.
 *
 * Allows tracking which plugin version generated each error entry,
 * enabling version-based filtering and diagnostics.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.12.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDOException;

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV16Trait {

    private function migrateV16ErrorSessionVersion(int $current): void {
        if ($current >= 16) {
            return;
        }

        $this->fileLogger->info('Applying migration v16: PluginVersion column on ErrorSessions');
        $table = TableType::ErrorSessions->value;

        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN PluginVersion TEXT");
        } catch (PDOException $e) {
            $this->fileLogger->logDebugException($e, 'Column might exist: PluginVersion');
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxErrorSessions_PluginVersion ON {$table}(PluginVersion)");

        $this->recordMigration(16);
    }
}
