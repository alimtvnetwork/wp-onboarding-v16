<?php
/**
 * Database Schema Trait — Table creation for main and audit databases.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardDatabaseSchemaTrait
 *
 * Handles CREATE TABLE statements and index creation for both
 * the plugin manager and audit SQLite databases.
 */
trait OnboardDatabaseSchemaTrait {

    /**
     * Create database tables.
     */
    public function create_tables() {
        if (!$this->connected) {
            return false;
        }

        try {
            $this->create_plugin_manager_tables();
            $this->create_audit_tables();
            return true;
        } catch (Exception $e) {
            $this->last_error = 'Failed to create tables: ' . $e->getMessage();
            OnboardErrorLog::log($e, 'Onboard DB:');
            return false;
        }
    }

    /**
     * Create plugin manager database tables.
     */
    private function create_plugin_manager_tables() {
        if (!$this->pdo) {
            return;
        }

        // Applications table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS applications (
                app_id TEXT PRIMARY KEY,
                client_id TEXT UNIQUE NOT NULL,
                client_secret TEXT NOT NULL,
                app_name TEXT NOT NULL,
                description TEXT,
                redirect_uri TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT,
                status TEXT DEFAULT 'active',
                ip_whitelist TEXT DEFAULT '[]',
                scopes TEXT DEFAULT '[\"onboard:plugin_manage\", \"onboard:backup\"]'
            )
        ");

        // OAuth tokens table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS oauth_tokens (
                token_id TEXT PRIMARY KEY,
                app_id TEXT NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT NOT NULL,
                token_type TEXT DEFAULT 'Bearer',
                scopes TEXT,
                issued_at TEXT NOT NULL,
                access_expires_at TEXT NOT NULL,
                refresh_expires_at TEXT NOT NULL,
                FOREIGN KEY (app_id) REFERENCES applications(app_id) ON DELETE CASCADE
            )
        ");

        // OAuth codes table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS oauth_codes (
                code_id TEXT PRIMARY KEY,
                app_id TEXT NOT NULL,
                auth_code TEXT UNIQUE NOT NULL,
                state TEXT,
                issued_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used INTEGER DEFAULT 0,
                FOREIGN KEY (app_id) REFERENCES applications(app_id) ON DELETE CASCADE
            )
        ");

        // Mutation tokens table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS mutation_tokens (
                token_id TEXT PRIMARY KEY,
                app_id TEXT NOT NULL,
                token TEXT UNIQUE NOT NULL,
                action TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                issued_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used INTEGER DEFAULT 0,
                FOREIGN KEY (app_id) REFERENCES applications(app_id) ON DELETE CASCADE
            )
        ");

        // IP approvals table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ip_approvals (
                approval_id TEXT PRIMARY KEY,
                app_id TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                approval_code TEXT,
                status TEXT DEFAULT 'pending',
                requested_at TEXT NOT NULL,
                approved_at TEXT,
                approved_by INTEGER,
                expires_at TEXT,
                FOREIGN KEY (app_id) REFERENCES applications(app_id) ON DELETE CASCADE
            )
        ");

        // Plugin settings table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS plugin_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                updated_at TEXT NOT NULL
            )
        ");

        // Snapshots table.
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS snapshots (
                snapshot_id TEXT PRIMARY KEY,
                plugin_slug TEXT NOT NULL,
                version TEXT NOT NULL,
                backup_date TEXT NOT NULL,
                file_path TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                checksum TEXT NOT NULL,
                trigger_action TEXT NOT NULL,
                requestor_app_id TEXT,
                requestor_ip_address TEXT,
                created_at TEXT NOT NULL,
                status TEXT DEFAULT 'success'
            )
        ");

        // Create indexes.
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_snapshots_plugin ON snapshots(plugin_slug)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_oauth_tokens_app ON oauth_tokens(app_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_mutation_tokens_app ON mutation_tokens(app_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ip_approvals_app ON ip_approvals(app_id)');
    }

    /**
     * Create audit database tables.
     */
    private function create_audit_tables() {
        if (!$this->audit_pdo) {
            return;
        }

        // Audit logs table.
        $this->audit_pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                log_id TEXT PRIMARY KEY,
                timestamp TEXT NOT NULL,
                action TEXT NOT NULL,
                plugin_slug TEXT,
                app_id TEXT,
                app_name TEXT,
                ip_address TEXT,
                mutation_token TEXT,
                status TEXT NOT NULL,
                details TEXT,
                error_message TEXT
            )
        ");

        // IP approval logs table.
        $this->audit_pdo->exec("
            CREATE TABLE IF NOT EXISTS ip_approval_logs (
                log_id TEXT PRIMARY KEY,
                app_id TEXT,
                app_name TEXT,
                ip_address TEXT NOT NULL,
                action TEXT NOT NULL,
                timestamp TEXT NOT NULL,
                details TEXT
            )
        ");

        // Create indexes.
        $this->audit_pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_timestamp ON audit_logs(timestamp)');
        $this->audit_pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action)');
        $this->audit_pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_app ON audit_logs(app_id)');
    }
}
