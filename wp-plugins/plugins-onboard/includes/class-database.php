<?php
/**
 * SQLite Database class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardDatabase
 *
 * Handles SQLite database operations.
 */
class OnboardDatabase {

    /**
     * PDO instance for plugin manager database.
     *
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * PDO instance for audit database.
     *
     * @var PDO|null
     */
    private $audit_pdo = null;

    /**
     * Connection status.
     *
     * @var bool
     */
    private $connected = false;

    /**
     * Last error message.
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Constructor.
     */
    public function __construct() {
        OnboardLogger::debug('OnboardDatabase constructor called');
        $this->connect();
        OnboardLogger::debug('OnboardDatabase constructor completed');
    }

    /**
     * Check if database is connected.
     *
     * @return bool
     */
    public function is_connected() {
        return $this->connected;
    }

    /**
     * Get last error.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get database directory path.
     *
     * @return string
     */
    private function get_db_dir() {
        return OnboardPaths::get(OnboardPaths::DIR_PLUGIN_DATA);
    }

    /**
     * Get main database path.
     *
     * @return string
     */
    private function get_main_db_path() {
        return OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE);
    }

    /**
     * Get audit database path.
     *
     * @return string
     */
    private function get_audit_db_path() {
        return OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE);
    }

    /**
     * Connect to databases.
     *
     * NOTE: This assumes directories have already been created by OnboardInitHelpers.
     */
    private function connect() {
        OnboardLogger::debug('[DB] Connection starting...');

        // STEP 1: Check if SQLite extension is available.
        OnboardLogger::debug('[DB] Checking for pdo_sqlite extension...');
        if (OnboardBooleanHelpers::is_extension_missing('pdo_sqlite')) {
            $this->last_error = 'PDO SQLite extension is not loaded.';
            OnboardLogger::error('[DB] ' . $this->last_error);
            error_log('Onboard DB: ' . $this->last_error);
            return;
        }
        OnboardLogger::debug('[DB] ✓ pdo_sqlite extension is loaded');

        try {
            // STEP 2: Get and verify database directory path.
            OnboardLogger::debug('[DB] Getting database directory path...');
            $db_dir = $this->get_db_dir();

            if (empty($db_dir)) {
                $this->last_error = 'Database directory path is empty';
                OnboardLogger::error('[DB] ' . $this->last_error);
                return;
            }
            OnboardLogger::debug("[DB] Database directory path: {$db_dir}");

            // STEP 3: Verify directory exists (should already exist from helpers).
            OnboardLogger::debug('[DB] Verifying database directory exists...');
            if (OnboardBooleanHelpers::is_dir_missing($db_dir)) {
                $this->last_error = "Database directory does not exist: {$db_dir}. CRITICAL: Directories must be created first via OnboardInitHelpers::ensure_directories_exist()";
                OnboardLogger::error('[DB] ' . $this->last_error);
                error_log('Onboard DB: ' . $this->last_error);
                return;
            }
            OnboardLogger::debug('[DB] ✓ Database directory exists');

            // STEP 4: Verify directory is writable.
            OnboardLogger::debug('[DB] Verifying database directory is writable...');
            if (OnboardBooleanHelpers::is_dir_readonly($db_dir)) {
                $this->last_error = "Database directory is read-only: {$db_dir}";
                OnboardLogger::error('[DB] ' . $this->last_error);
                error_log('Onboard DB: ' . $this->last_error);
                return;
            }
            OnboardLogger::debug('[DB] ✓ Database directory is writable');

            // STEP 5: Connect to main database.
            $main_db = $this->get_main_db_path();
            OnboardLogger::debug("[DB] Connecting to main database: {$main_db}");
            $this->pdo = new PDO('sqlite:' . $main_db);
            OnboardLogger::debug('[DB] ✓ Main database PDO connection established');

            OnboardLogger::debug('[DB] Setting main database attributes...');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 5000);
            OnboardLogger::debug('[DB] ✓ Main database attributes set');

            OnboardLogger::debug('[DB] Executing main database PRAGMAs...');
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            OnboardLogger::debug('[DB] ✓ Main database PRAGMAs executed');

            // STEP 6: Connect to audit database.
            $audit_db = $this->get_audit_db_path();
            OnboardLogger::debug("[DB] Connecting to audit database: {$audit_db}");
            $this->audit_pdo = new PDO('sqlite:' . $audit_db);
            OnboardLogger::debug('[DB] ✓ Audit database PDO connection established');

            OnboardLogger::debug('[DB] Setting audit database attributes...');
            $this->audit_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->audit_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->audit_pdo->setAttribute(PDO::ATTR_TIMEOUT, 5000);
            OnboardLogger::debug('[DB] ✓ Audit database attributes set');

            OnboardLogger::debug('[DB] Executing audit database PRAGMAs...');
            $this->audit_pdo->exec('PRAGMA journal_mode = WAL');
            OnboardLogger::debug('[DB] ✓ Audit database PRAGMAs executed');

            // STEP 7: Mark as connected.
            $this->connected = true;
            OnboardLogger::debug('[DB] === DATABASE CONNECTION SUCCESSFUL ===');

        } catch (PDOException $e) {
            $this->last_error = 'Database connection failed: ' . $e->getMessage();
            OnboardLogger::critical('Database PDO exception', $e);
            error_log('Onboard DB: ' . $this->last_error);
            $this->connected = false;
        } catch (Exception $e) {
            $this->last_error = 'Database error: ' . $e->getMessage();
            OnboardLogger::critical('Unexpected database exception', $e);
            error_log('Onboard DB: ' . $this->last_error);
            $this->connected = false;
        }
    }

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
            error_log('Onboard DB: ' . $this->last_error);
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

    /**
     * Execute query on plugin manager database.
     *
     * @param string $sql    SQL query.
     * @param array  $params Query parameters.
     * @return PDOStatement|false
     */
    public function query($sql, $params = array()) {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->last_error = 'Query failed: ' . $e->getMessage();
            error_log('Onboard DB Query: ' . $this->last_error . ' SQL: ' . $sql);
            return false;
        }
    }

    /**
     * Execute query on audit database.
     *
     * @param string $sql    SQL query.
     * @param array  $params Query parameters.
     * @return PDOStatement|false
     */
    public function audit_query($sql, $params = array()) {
        if (!$this->audit_pdo) {
            return false;
        }
        
        try {
            $stmt = $this->audit_pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->last_error = 'Audit query failed: ' . $e->getMessage();
            error_log('Onboard DB Audit Query: ' . $this->last_error);
            return false;
        }
    }

    /**
     * Get PDO instance for plugin manager database.
     *
     * @return PDO|null
     */
    public function get_pdo() {
        return $this->pdo;
    }

    /**
     * Get PDO instance for audit database.
     *
     * @return PDO|null
     */
    public function get_audit_pdo() {
        return $this->audit_pdo;
    }

    /**
     * Generate UUID.
     *
     * @return string
     */
    public function generate_uuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Get setting value.
     *
     * @param string $key Setting key.
     * @return mixed|null
     */
    public function get_setting($key) {
        if (!$this->connected) {
            return null;
        }
        
        try {
            $stmt = $this->query(
                'SELECT setting_value FROM plugin_settings WHERE setting_key = ?',
                array($key)
            );

            if ($stmt === false) {
                return null;
            }

            $result = $stmt->fetch();
            if ($result) {
                $decoded = json_decode($result['setting_value'], true);
                // If JSON decode failed, return raw value.
                return ($decoded !== null || $result['setting_value'] === 'null') ? $decoded : $result['setting_value'];
            }
            return null;
        } catch (Exception $e) {
            error_log('Onboard get_setting error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save setting value.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public function save_setting($key, $value) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $json_value = json_encode($value);
            $now = gmdate('Y-m-d H:i:s');

            // Check if exists.
            $stmt = $this->query(
                'SELECT setting_key FROM plugin_settings WHERE setting_key = ?',
                array($key)
            );
            
            if ($stmt && $stmt->fetch()) {
                // Update.
                $this->query(
                    'UPDATE plugin_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
                    array($json_value, $now, $key)
                );
            } else {
                // Insert.
                $this->query(
                    'INSERT INTO plugin_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                    array($key, $json_value, $now)
                );
            }
            return true;
        } catch (Exception $e) {
            error_log('Onboard save_setting error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all_settings() {
        if (!$this->connected) {
            return array();
        }
        
        try {
            $stmt = $this->query('SELECT * FROM plugin_settings');
            if (!$stmt) {
                return array();
            }
            
            $results = $stmt->fetchAll();
            $settings = array();
            
            foreach ($results as $row) {
                $decoded = json_decode($row['setting_value'], true);
                $settings[$row['setting_key']] = ($decoded !== null || $row['setting_value'] === 'null') ? $decoded : $row['setting_value'];
            }
            return $settings;
        } catch (Exception $e) {
            error_log('Onboard get_all_settings error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Begin transaction.
     */
    public function begin_transaction() {
        if ($this->pdo) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * Commit transaction.
     */
    public function commit() {
        if ($this->pdo && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    /**
     * Rollback transaction.
     */
    public function rollback() {
        if ($this->pdo && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Get last insert ID.
     *
     * @return string
     */
    public function last_insert_id() {
        if ($this->pdo) {
            return $this->pdo->lastInsertId();
        }
        return '';
    }
}
