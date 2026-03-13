<?php
/**
 * SQLite Database class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/DatabaseSchemaTrait.php';
require_once __DIR__ . '/traits/DatabaseSettingsTrait.php';

/**
 * Class OnboardDatabase
 *
 * Handles SQLite database operations.
 * Schema creation delegated to OnboardDatabaseSchemaTrait.
 * Settings CRUD delegated to OnboardDatabaseSettingsTrait.
 */
class OnboardDatabase {
    use OnboardDatabaseSchemaTrait;
    use OnboardDatabaseSettingsTrait;

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
        if (OnboardBooleanHelpers::isExtensionMissing('pdo_sqlite')) {
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
            if (OnboardBooleanHelpers::isDirMissing($db_dir)) {
                $this->last_error = "Database directory does not exist: {$db_dir}. CRITICAL: Directories must be created first via OnboardInitHelpers::ensure_directories_exist()";
                OnboardLogger::error('[DB] ' . $this->last_error);
                error_log('Onboard DB: ' . $this->last_error);
                return;
            }
            OnboardLogger::debug('[DB] ✓ Database directory exists');

            // STEP 4: Verify directory is writable.
            OnboardLogger::debug('[DB] Verifying database directory is writable...');
            if (OnboardBooleanHelpers::isDirReadonly($db_dir)) {
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
            OnboardErrorLog::log($e, 'Onboard DB:');
            $this->connected = false;
        } catch (Throwable $e) {
            $this->last_error = 'Database error: ' . $e->getMessage();
            OnboardLogger::critical('Unexpected database exception', $e);
            OnboardErrorLog::log($e, 'Onboard DB:');
            $this->connected = false;
        }
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
            OnboardErrorLog::log($e, 'Onboard DB Query:');
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
            OnboardErrorLog::log($e, 'Onboard DB Audit Query:');
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
