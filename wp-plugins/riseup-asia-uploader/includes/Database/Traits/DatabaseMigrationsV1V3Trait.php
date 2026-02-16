<?php
/**
 * DatabaseMigrationsV1V3Trait — Schema migrations v1 through v3.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV1V3Trait {

    private function migrateV1Transactions(int $current): void {
        if ($current >= 1) {
            return;
        }

        $this->fileLogger->info('Applying migration v1: transactions table');
        $table = TableType::Transactions->value;

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            plugin_slug TEXT,
            post_id INTEGER,
            user_login TEXT NOT NULL,
            user_id INTEGER,
            ip_address TEXT NOT NULL,
            details TEXT,
            status TEXT NOT NULL,
            error_msg TEXT,
            created_at TEXT NOT NULL
        )");

        $this->recordMigration(1);
    }

    private function createTransactionIndexes(): void {
        $table   = TableType::Transactions->value;
        $indexes = array(
            'idx_action'      => 'action',
            'idx_plugin_slug' => 'plugin_slug',
            'idx_user_login'  => 'user_login',
            'idx_status'      => 'status',
            'idx_created_at'  => 'created_at',
        );

        foreach ($indexes as $name => $column) {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$column})");
        }

        $this->fileLogger->info('Transaction indexes ensured');
    }

    private function migrateV2AgentTables(int $current): void {
        if ($current >= 2) {
            return;
        }

        $this->fileLogger->info('Applying migration v2: agent sites tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::AgentSites->value . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            username TEXT NOT NULL,
            app_password_encrypted TEXT NOT NULL,
            redirect_url TEXT,
            redirect_resolved TEXT,
            redirect_resolved_at TEXT,
            status TEXT DEFAULT 'pending',
            last_sync TEXT,
            last_error TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::AgentActions->value . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_site_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            target_plugin TEXT,
            status TEXT NOT NULL,
            details TEXT,
            error_msg TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (agent_site_id) REFERENCES " . TableType::AgentSites->value . "(id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_sites_status ON " . TableType::AgentSites->value . "(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_site_id ON " . TableType::AgentActions->value . "(agent_site_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_action ON " . TableType::AgentActions->value . "(action)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_created ON " . TableType::AgentActions->value . "(created_at)");

        $this->recordMigration(2);
    }

    private function migrateV3EnhancedTransactions(int $current): void {
        if ($current >= 3) {
            return;
        }

        $this->fileLogger->info('Applying migration v3: enhanced transaction fields');
        $table   = TableType::Transactions->value;
        $columns = array(
            'plugin_file'   => 'TEXT',
            'was_active'    => 'INTEGER',
            'triggered_by'  => 'TEXT',
            'agent_site_id' => 'INTEGER',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->fileLogger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_triggered_by ON {$table}(triggered_by)");

        $this->recordMigration(3);
    }
}
