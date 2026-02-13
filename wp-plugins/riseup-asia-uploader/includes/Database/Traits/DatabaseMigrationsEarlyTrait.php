<?php
/**
 * Database Migrations Early Trait
 *
 * Schema migrations v1 through v5 (transactions, agents, snapshots).
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsEarlyTrait {

    /**
     * Migration v1: Initial transactions table.
     */
    private function migrate_v1_transactions($current) {
        if ($current >= 1) {
            return;
        }

        $this->file_logger->info('Applying migration v1: transactions table');
        $table = self::TABLE_TRANSACTIONS;

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

        $this->record_migration(1);
    }

    /**
     * Create indexes for the transactions table (idempotent).
     */
    private function create_transaction_indexes() {
        $table   = self::TABLE_TRANSACTIONS;
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

        $this->file_logger->info('Transaction indexes ensured');
    }

    /**
     * Migration v2: Agent sites and actions tables.
     */
    private function migrate_v2_agent_tables($current) {
        if ($current >= 2) {
            return;
        }

        $this->file_logger->info('Applying migration v2: agent sites tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS agent_sites (
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

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS agent_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_site_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            target_plugin TEXT,
            status TEXT NOT NULL,
            details TEXT,
            error_msg TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (agent_site_id) REFERENCES agent_sites(id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_sites_status ON agent_sites(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_site_id ON agent_actions(agent_site_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_action ON agent_actions(action)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_created ON agent_actions(created_at)");

        $this->record_migration(2);
    }

    /**
     * Migration v3: Enhanced transaction logging fields.
     */
    private function migrate_v3_enhanced_transactions($current) {
        if ($current >= 3) {
            return;
        }

        $this->file_logger->info('Applying migration v3: enhanced transaction fields');
        $table   = self::TABLE_TRANSACTIONS;
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
                $this->file_logger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_triggered_by ON {$table}(triggered_by)");

        $this->record_migration(3);
    }

    /**
     * Migration v4: Source machine tracking.
     */
    private function migrate_v4_source_machine($current) {
        if ($current >= 4) {
            return;
        }

        $this->file_logger->info('Applying migration v4: source machine tracking');
        $table = self::TABLE_TRANSACTIONS;

        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN source_machine TEXT");
        } catch (PDOException $e) {
            $this->file_logger->debug("Column might exist: source_machine", array('error' => $e->getMessage()));
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_machine ON {$table}(source_machine)");

        $this->record_migration(4);
    }

    /**
     * Migration v5: Snapshot system tables.
     */
    private function migrate_v5_snapshot_tables($current) {
        if ($current >= 5) {
            return;
        }

        $this->file_logger->info('Applying migration v5: snapshot system tables');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOTS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sequence INTEGER NOT NULL,
            filename TEXT NOT NULL UNIQUE,
            filepath TEXT NOT NULL,
            created_at TEXT NOT NULL,
            completed_at TEXT,
            status TEXT DEFAULT 'pending',
            provider TEXT NOT NULL,
            scope TEXT NOT NULL,
            tables_json TEXT,
            table_counts_json TEXT,
            total_rows INTEGER,
            file_size INTEGER,
            duration_ms INTEGER,
            triggered_by TEXT,
            error_message TEXT,
            metadata_json TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOT_PROGRESS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            snapshot_id INTEGER NOT NULL,
            table_name TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            rows_total INTEGER,
            rows_exported INTEGER DEFAULT 0,
            started_at TEXT,
            completed_at TEXT,
            error_message TEXT,
            FOREIGN KEY (snapshot_id) REFERENCES snapshots(id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_created ON " . self::TABLE_SNAPSHOTS . "(created_at DESC)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_status ON " . self::TABLE_SNAPSHOTS . "(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_provider ON " . self::TABLE_SNAPSHOTS . "(provider)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_progress_snapshot ON " . self::TABLE_SNAPSHOT_PROGRESS . "(snapshot_id)");

        $this->record_migration(5);
    }
}
