<?php
/**
 * Riseup Asia Uploader - Database Handler
 *
 * SQLite database for transaction logging using the micro-ORM.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/DatabaseMigrationsEarlyTrait.php';
require_once __DIR__ . '/Traits/DatabaseMigrationsLateTrait.php';
require_once __DIR__ . '/Traits/DatabaseQueryTrait.php';

/**
 * Class RiseupDatabase
 *
 * Handles all SQLite database operations for transaction logging.
 */
class RiseupDatabase {

    use DatabaseMigrationsEarlyTrait;
    use DatabaseMigrationsLateTrait;
    use DatabaseQueryTrait;

    /** Database table constants */
    public const TABLE_TRANSACTIONS      = 'transactions';
    public const TABLE_SNAPSHOTS         = 'snapshots';
    public const TABLE_SNAPSHOT_PROGRESS = 'snapshot_progress';
    public const TABLE_FILE_CACHE        = 'file_cache';
    public const TABLE_SNAPSHOT_EXPORTS  = 'snapshot_exports';

    /** Snapshot export status constants */
    public const SNAPSHOT_EXPORT_STATUS_VALID = 'valid';

    /** Transaction status constants */
    public const STATUS_SUCCESS = 'success';

    /** Default and max limits for queries */
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 1000;

    /** @var PDO|null */
    private $pdo = null;

    /** @var string */
    private $db_path;

    /** @var RiseupFileLogger */
    private $file_logger;

    /** @var RiseupDatabase|null */
    private static $instance = null;

    /** @var bool */
    private $init_attempted = false;

    /**
     * Get singleton instance.
     *
     * @return RiseupDatabase
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
        $this->file_logger->info('Database constructor called');
    }

    /**
     * Initialize database (lazy loading).
     *
     * @return bool True if successful.
     */
    public function init() {
        if ($this->init_attempted) {
            return $this->pdo !== null;
        }

        $this->init_attempted = true;
        $this->file_logger->info('Starting database initialization');

        try {
            $this->db_path = $this->get_database_path();
            $this->file_logger->info('Database path resolved', array('path' => $this->db_path));

            return $this->init_database();
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Database init failed');

            return false;
        }
    }

    /**
     * Get the database file path.
     */
    private function get_database_path() {
        $this->file_logger->debug('Resolving database path');
        $base_dir = $this->file_logger->get_base_dir();
        $this->file_logger->debug('Base directory', array('dir' => $base_dir));

        if (RiseupPathUtils::is_dir_missing($base_dir, true)) {
            $this->file_logger->error('Failed to create base directory', array('dir' => $base_dir));

            throw new Exception('Failed to create data directory: ' . $base_dir);
        }

        $db_path = RiseupPathUtils::get_db_path();
        $this->file_logger->info('Database path set', array('path' => $db_path));

        return $db_path;
    }

    /**
     * Initialize the database connection and create tables.
     */
    private function init_database() {
        $this->file_logger->info('Initializing PDO connection');

        try {
            $this->pdo = RiseupInitHelpers::initSqliteConnection($this->db_path, $this->file_logger);

            if ($this->pdo === null) {
                return false;
            }

            $this->file_logger->debug('Configuring ORM');
            RiseupORM::configure($this->pdo);
            $this->file_logger->info('ORM configured');

            $this->create_tables();
            $this->file_logger->info('Database initialization complete');

            return true;

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'PDO initialization failed');
            $this->pdo = null;

            return false;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Database initialization failed');
            $this->pdo = null;

            return false;
        } catch (Error $e) {
            $this->file_logger->error('Fatal error during database init: ' . $e->getMessage());
            $this->pdo = null;

            return false;
        }
    }

    /**
     * Create database tables (migration orchestrator).
     */
    private function create_tables() {
        $this->file_logger->info('Running database migration - creating/updating tables');

        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
                version INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL
            )");

            $stmt = $this->pdo->query("SELECT MAX(version) as v FROM schema_version");
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = (int) ($row['v'] ?? 0);
            $this->file_logger->info('Current schema version', array('version' => $current));

            $this->migrate_v1_transactions($current);
            $this->create_transaction_indexes();
            $this->migrate_v2_agent_tables($current);
            $this->migrate_v3_enhanced_transactions($current);
            $this->migrate_v4_source_machine($current);
            $this->migrate_v5_snapshot_tables($current);
            $this->migrate_v6_remote_plugins_cache($current);
            $this->migrate_v7_file_hash_cache($current);
            $this->migrate_v8_snapshot_settings($current);
            $this->migrate_v9_error_sessions($current);
            $this->migrate_v10_version_tracking($current);
            $this->migrate_v11_snapshot_exports($current);

            $this->file_logger->info('Database migration complete');
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Database migration failed');

            throw $e;
        }
    }

    /**
     * Record a schema version.
     */
    private function record_migration($version) {
        $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES ({$version}, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
        $this->file_logger->info("Migration v{$version} applied successfully");
    }

    /**
     * Get PDO instance.
     *
     * @return PDO|null
     */
    public function get_pdo() {
        if (!$this->init_attempted) {
            $this->init();
        }

        return $this->pdo;
    }

    /**
     * Check if database is ready.
     *
     * @return bool
     */
    public function is_ready() {
        if (!$this->init_attempted) {
            $this->init();
        }

        return $this->pdo !== null;
    }
}
