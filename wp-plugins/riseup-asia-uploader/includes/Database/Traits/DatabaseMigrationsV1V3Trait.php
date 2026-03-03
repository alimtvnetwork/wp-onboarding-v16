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
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Action TEXT NOT NULL,
            PluginSlug TEXT,
            PostId INTEGER,
            UserLogin TEXT NOT NULL,
            UserId INTEGER,
            IpAddress TEXT NOT NULL,
            Details TEXT,
            Status TEXT NOT NULL,
            ErrorMsg TEXT,
            CreatedAt TEXT NOT NULL
        )");

        $this->recordMigration(1);
    }

    private function createTransactionIndexes(): void {
        $table   = TableType::Transactions->value;
        $indexes = array(
            'IdxTransactions_Action'    => 'Action',
            'IdxTransactions_PluginSlug' => 'PluginSlug',
            'IdxTransactions_UserLogin' => 'UserLogin',
            'IdxTransactions_Status'    => 'Status',
            'IdxTransactions_CreatedAt' => 'CreatedAt',
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
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT NOT NULL,
            Url TEXT NOT NULL,
            Username TEXT NOT NULL,
            AppPasswordEncrypted TEXT NOT NULL,
            RedirectUrl TEXT,
            RedirectResolved TEXT,
            RedirectResolvedAt TEXT,
            Status TEXT DEFAULT 'Pending',
            LastSync TEXT,
            LastError TEXT,
            CreatedAt TEXT NOT NULL,
            UpdatedAt TEXT
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::AgentActions->value . " (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            AgentSiteId INTEGER NOT NULL,
            Action TEXT NOT NULL,
            TargetPlugin TEXT,
            Status TEXT NOT NULL,
            Details TEXT,
            ErrorMsg TEXT,
            CreatedAt TEXT NOT NULL,
            FOREIGN KEY (AgentSiteId) REFERENCES " . TableType::AgentSites->value . "(Id) ON DELETE CASCADE
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxAgentSites_Status ON " . TableType::AgentSites->value . "(Status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxAgentActions_AgentSiteId ON " . TableType::AgentActions->value . "(AgentSiteId)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxAgentActions_Action ON " . TableType::AgentActions->value . "(Action)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxAgentActions_CreatedAt ON " . TableType::AgentActions->value . "(CreatedAt)");

        $this->recordMigration(2);
    }

    private function migrateV3EnhancedTransactions(int $current): void {
        if ($current >= 3) {
            return;
        }

        $this->fileLogger->info('Applying migration v3: enhanced transaction fields');
        $table   = TableType::Transactions->value;
        $columns = array(
            'PluginFile'  => 'TEXT',
            'WasActive'   => 'INTEGER',
            'TriggeredBy' => 'TEXT',
            'AgentSiteId' => 'INTEGER',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->fileLogger->debug("Column might exist: {$column}", array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS IdxTransactions_TriggeredBy ON {$table}(TriggeredBy)");

        $this->recordMigration(3);
    }
}
