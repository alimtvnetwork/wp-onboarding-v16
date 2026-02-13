<?php
/**
 * Riseup Asia Uploader - Database Handler
 *
 * SQLite database for transaction logging using the micro-ORM.
 * Database is stored in wp-content/uploads/riseup-asia-uploader/
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupDatabase
 *
 * Handles all SQLite database operations for transaction logging.
 */
class RiseupDatabase {

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

    /**
     * PDO instance.
     *
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * Database file path.
     *
     * @var string
     */
    private $db_path;

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $file_logger;

    /**
     * Singleton instance.
     *
     * @var RiseupDatabase|null
     */
    private static $instance = null;

    /**
     * Whether initialization has been attempted.
     *
     * @var bool
     */
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
     *
     * @return string
     */
    private function get_database_path() {
        $this->file_logger->debug('Resolving database path');
        
        // Get base directory from file logger
        $base_dir = $this->file_logger->get_base_dir();
        
        $this->file_logger->debug('Base directory', array('dir' => $base_dir));
        
        // Ensure base directory exists with security files (idempotent)
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
     *
     * @return bool True if successful.
     */
    private function init_database() {
        $this->file_logger->info('Initializing PDO connection');
        
        try {
            // Use centralized SQLite connection helper
            $this->pdo = RiseupInitHelpers::initSqliteConnection($this->db_path, $this->file_logger);
            
            if ($this->pdo === null) {
                // Warning already logged once by initSqliteConnection; no need to repeat
                return false;
            }

            // Configure the ORM with our PDO instance
            $this->file_logger->debug('Configuring ORM');
            RiseupORM::configure($this->pdo);
            $this->file_logger->info('ORM configured');

            // Create tables
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
            // Catch PHP 7+ fatal errors (like class not found)
            $this->file_logger->error('Fatal error during database init: ' . $e->getMessage());
            $this->pdo = null;

            return false;
        }
    }

    /**
     * Create database tables (migration).
     * This runs on every init to ensure schema is up to date.
     *
     * @return void
     */
    private function create_tables() {
        $this->file_logger->info('Running database migration - creating/updating tables');

        try {
            // Ensure schema_version table exists
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
     *
     * @param int $version Migration version number.
     */
    private function record_migration($version) {
        $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES ({$version}, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
        $this->file_logger->info("Migration v{$version} applied successfully");
    }

    /**
     * Migration v1: Initial transactions table.
     *
     * @param int $current Current schema version.
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
     * Create indexes for the transactions table (runs every init, idempotent).
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
     *
     * @param int $current Current schema version.
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
     *
     * @param int $current Current schema version.
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
     *
     * @param int $current Current schema version.
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
     *
     * @param int $current Current schema version.
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

    /**
     * Migration v6: Remote plugins cache table.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v6_remote_plugins_cache($current) {
        if ($current >= 6) {
            return;
        }

        $this->file_logger->info('Applying migration v6: remote plugins cache');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS remote_plugins_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            data_json TEXT NOT NULL,
            fetched_at TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_rpc_site_id ON remote_plugins_cache(site_id)");

        $this->record_migration(6);
    }

    /**
     * Migration v7: File hash cache table.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v7_file_hash_cache($current) {
        if ($current >= 7) {
            return;
        }

        $this->file_logger->info('Applying migration v7: file hash cache table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_FILE_CACHE . " (
            plugin_slug TEXT NOT NULL,
            relative_path TEXT NOT NULL,
            md5_hash TEXT NOT NULL,
            modified_at TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            cached_at TEXT NOT NULL,
            PRIMARY KEY (plugin_slug, relative_path)
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_file_cache_slug ON " . self::TABLE_FILE_CACHE . "(plugin_slug)");

        $this->record_migration(7);
    }

    /**
     * Migration v8: Snapshot settings key-value store.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v8_snapshot_settings($current) {
        if ($current >= 8) {
            return;
        }

        $this->file_logger->info('Applying migration v8: snapshot settings table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS snapshot_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'string',
            updated_at TEXT NOT NULL
        )");

        $defaults = array(
            array('snapshot.mode',             'per_table',    'string'),
            array('snapshot.backup_type',      'incremental',  'string'),
            array('snapshot.worker_count',     '10',           'int'),
            array('snapshot.storage_path',     'snapshots/',   'string'),
            array('snapshot.include_plugins',  '1',            'bool'),
            array('snapshot.plugin_selection', 'all',          'string'),
            array('snapshot.retention_days',   '30',           'int'),
            array('snapshot.retention_count',  '10',           'int'),
            array('snapshot.compression',      '1',            'bool'),
            array('snapshot.batch_size',       '1000',         'int'),
            array('snapshot.provider',         'auto',         'string'),
            array('snapshot.scope',            'wordpress',    'string'),
            array('snapshot.frequency',        'manual',       'string'),
            array('snapshot.schedule_time',    '03:00',        'string'),
            array('snapshot.pre_restore_backup', '1',          'bool'),
        );

        $now  = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO snapshot_settings (key, value, type, updated_at) VALUES (?, ?, ?, ?)");

        foreach ($defaults as $row) {
            $stmt->execute(array($row[0], $row[1], $row[2], $now));
        }

        $this->record_migration(8);
    }

    /**
     * Migration v9: Error sessions table + flash state.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v9_error_sessions($current) {
        if ($current >= 9) {
            return;
        }

        $this->file_logger->info('Applying migration v9: error sessions and flash state');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS error_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            level TEXT NOT NULL,
            message TEXT NOT NULL,
            file TEXT,
            line INTEGER,
            context_json TEXT,
            stack_trace TEXT,
            created_at TEXT NOT NULL
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_error_sessions_level ON error_sessions(level)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_error_sessions_created ON error_sessions(created_at DESC)");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS flash_state (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $this->pdo->exec("INSERT OR IGNORE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $this->pdo->exec("INSERT OR IGNORE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        $this->record_migration(9);
    }

    /**
     * Migration v10: Plugin version and upload source tracking.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v10_version_tracking($current) {
        if ($current >= 10) {
            return;
        }

        $this->file_logger->info('Applying migration v10: plugin version and upload source columns');
        $table   = self::TABLE_TRANSACTIONS;
        $columns = array(
            'plugin_version' => 'TEXT',
            'upload_source'  => 'TEXT',
        );

        foreach ($columns as $column => $type) {
            try {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
            } catch (PDOException $e) {
                $this->file_logger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
            }
        }

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_plugin_version ON {$table}(plugin_version)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_upload_source ON {$table}(upload_source)");

        $this->record_migration(10);
    }

    /**
     * Migration v11: Snapshot ZIP export cache table.
     *
     * @param int $current Current schema version.
     */
    private function migrate_v11_snapshot_exports($current) {
        if ($current >= 11) {
            return;
        }

        $this->file_logger->info('Applying migration v11: snapshot exports table');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . self::TABLE_SNAPSHOT_EXPORTS . " (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            snapshot_id     INTEGER NOT NULL,
            zip_filename    TEXT NOT NULL,
            zip_path        TEXT NOT NULL,
            zip_size        INTEGER NOT NULL DEFAULT 0,
            included_ids    TEXT NOT NULL,
            incremental_count INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT NOT NULL DEFAULT (datetime('now')),
            expires_at      TEXT,
            status          TEXT NOT NULL DEFAULT '" . self::SNAPSHOT_EXPORT_STATUS_VALID . "',
            UNIQUE(snapshot_id)
        )");

        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_exports_snapshot ON " . self::TABLE_SNAPSHOT_EXPORTS . "(snapshot_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_exports_status ON " . self::TABLE_SNAPSHOT_EXPORTS . "(status)");

        $this->record_migration(11);
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

    /**
     * Log a transaction using ORM.
     *
     * @param string      $action         Action type (use ACTION_* constants).
     * @param string|null $plugin_slug    Plugin slug (for plugin operations).
     * @param int|null    $post_id        Post ID (for post operations).
     * @param string      $user_login     WordPress username.
     * @param int|null    $user_id        WordPress user ID.
     * @param string      $ip_address     Client IP address.
     * @param array       $details        Additional details (will be JSON encoded).
     * @param string      $status         Status (use STATUS_* constants).
     * @param string|null $error_msg      Error message if failed.
     * @param array       $enhanced       Enhanced fields: plugin_file, was_active, triggered_by, agent_site_id.
     *
     * @return int|false Insert ID on success, false on failure.
     */
    public function log_transaction(
        $action,
        $plugin_slug = null,
        $post_id = null,
        $user_login = '',
        $user_id = null,
        $ip_address = '',
        $details = array(),
        $status = self::STATUS_SUCCESS,
        $error_msg = null,
        $enhanced = array()
    ) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready, cannot log transaction');
            return false;
        }

        try {
            $this->file_logger->debug('Logging transaction', array(
                'action' => $action, 'status' => $status, 'enhanced' => $enhanced,
            ));

            $record = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->create()
                ->set('action', $action)
                ->set('plugin_slug', $plugin_slug)
                ->set('post_id', $post_id)
                ->set('user_login', $user_login)
                ->set('user_id', $user_id)
                ->set('ip_address', $ip_address)
                ->set('details', !empty($details) ? json_encode($details) : null)
                ->set('status', $status)
                ->set('error_msg', $error_msg)
                ->set('created_at', gmdate('Y-m-d\TH:i:s\Z'));

            $this->applyEnhancedFields($record, $enhanced);
            $result = $record->save();
            $this->file_logger->info('Transaction logged', array('id' => $result));

            return $result;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to log transaction');
            return false;
        }
    }

    /**
     * Apply enhanced metadata fields to a transaction record.
     *
     * @param object $record   ORM record instance.
     * @param array  $enhanced Enhanced fields array.
     */
    private function applyEnhancedFields($record, array $enhanced) {
        $string_fields = array('plugin_file', 'triggered_by', 'source_machine', 'plugin_version', 'upload_source');
        foreach ($string_fields as $field) {
            if (!empty($enhanced[$field])) {
                $record->set($field, $enhanced[$field]);
            }
        }

        if (!empty($enhanced['agent_site_id'])) {
            $record->set('agent_site_id', (int) $enhanced['agent_site_id']);
        }

        if (isset($enhanced['was_active'])) {
            $record->set('was_active', $enhanced['was_active'] ? 1 : 0);
        }
    }

    /**
     * Log a transaction with enhanced context (convenience wrapper).
     *
     * @param array $params All parameters as associative array.
     * @return int|false Insert ID on success, false on failure.
     */
    public function log_enhanced_transaction($params) {
        return $this->log_transaction(
            $params['action'] ?? '',
            $params['plugin_slug'] ?? null,
            $params['post_id'] ?? null,
            $params['user_login'] ?? '',
            $params['user_id'] ?? null,
            $params['ip_address'] ?? '',
            $params['details'] ?? array(),
            $params['status'] ?? self::STATUS_SUCCESS,
            $params['error_msg'] ?? null,
            array(
                'plugin_file'    => $params['plugin_file'] ?? null,
                'was_active'     => $params['was_active'] ?? null,
                'triggered_by'   => $params['triggered_by'] ?? null,
                'agent_site_id'  => $params['agent_site_id'] ?? null,
                'plugin_version' => $params['plugin_version'] ?? null,
                'upload_source'  => $params['upload_source'] ?? null,
            )
        );
    }

    /**
     * Query transactions with filtering and pagination using ORM.
     *
     * @param array $filters Filters: plugin, action, user, status, from, to.
     * @param int   $limit   Number of records to return.
     * @param int   $offset  Offset for pagination.
     *
     * @return array Array with 'total' and 'logs' keys.
     */
    public function query_transactions($filters = array(), $limit = self::DEFAULT_LIMIT, $offset = 0) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready for query');

            return array('total' => 0, 'logs' => array());
        }

        // Sanitize limit
        $limit = min(max(1, (int) $limit), self::MAX_LIMIT);
        $offset = max(0, (int) $offset);

        try {
            $this->file_logger->debug('Querying transactions', array('filters' => $filters));
            
            // Build count query
            $count_query = RiseupORM::for_table(self::TABLE_TRANSACTIONS);
            $data_query = RiseupORM::for_table(self::TABLE_TRANSACTIONS);

            // Apply filters to both queries
            $this->apply_filters($count_query, $filters);
            $this->apply_filters($data_query, $filters);

            // Get total count
            $total = $count_query->count();

            // Get paginated results
            $logs = $data_query
                ->order_by_desc('created_at')
                ->limit($limit)
                ->offset($offset)
                ->find_many();

            // Decode JSON details
            foreach ($logs as &$log) {
                if (!empty($log['details'])) {
                    $log['details'] = json_decode($log['details'], true);
                }
            }

            $this->file_logger->debug('Query complete', array('total' => $total, 'returned' => count($logs)));
            
            return array(
                'total' => $total,
                'logs'  => $logs,
            );
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to query transactions');

            return array('total' => 0, 'logs' => array());
        }
    }

    /**
     * Apply filters to an ORM query.
     *
     * @param RiseupORM $query   ORM query instance.
     * @param array      $filters Filters to apply.
     *
     * @return void
     */
    private function apply_filters($query, $filters) {
        // Filter by plugin
        if (!empty($filters['plugin'])) {
            $query->where('plugin_slug', $filters['plugin']);
        }

        // Filter by action (supports comma-separated list)
        if (!empty($filters['action'])) {
            $actions = array_map('trim', explode(',', $filters['action']));
            if (count($actions) === 1) {
                $query->where('action', $actions[0]);
            } else {
                $query->where_in('action', $actions);
            }
        }

        // Filter by user
        if (!empty($filters['user'])) {
            $query->where('user_login', $filters['user']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by date range
        if (!empty($filters['from'])) {
            $query->where_gte('created_at', $filters['from'] . 'T00:00:00Z');
        }

        if (!empty($filters['to'])) {
            $query->where_lte('created_at', $filters['to'] . 'T23:59:59Z');
        }

        // Filter by triggered_by (source type)
        if (!empty($filters['triggered_by'])) {
            $query->where('triggered_by', $filters['triggered_by']);
        }

        // Filter by source_machine (hostname)
        if (!empty($filters['source_machine'])) {
            $query->where_like('source_machine', '%' . $filters['source_machine'] . '%');
        }

        // Filter by upload_source
        if (!empty($filters['upload_source'])) {
            $query->where('upload_source', $filters['upload_source']);
        }
    }

    /**
     * Get transaction by ID using ORM.
     *
     * @param int $id Transaction ID.
     *
     * @return array|null Transaction data or null.
     */
    public function get_transaction($id) {
        if (!$this->is_ready()) {
            return null;
        }

        try {
            $log = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->find_one((int) $id);

            if ($log && !empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }

            return $log;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to get transaction');

            return null;
        }
    }

    /**
     * Get statistics summary using ORM.
     *
     * @return array Statistics.
     */
    public function get_stats() {
        if (!$this->is_ready()) {
            return array();
        }

        try {
            return array(
                'total_transactions' => RiseupORM::for_table(self::TABLE_TRANSACTIONS)->count(),
                'by_action'          => $this->countByColumn('action'),
                'by_status'          => $this->countByColumn('status'),
                'last_24h'           => RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                    ->where_gte('created_at', gmdate('Y-m-d\TH:i:s\Z', time() - 86400))
                    ->count(),
            );
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to get stats');
            return array();
        }
    }

    /**
     * Count rows grouped by a column in the transactions table.
     *
     * @param string $column Column name to group by.
     * @return array Associative array of column_value => count.
     */
    private function countByColumn(string $column): array {
        $rows = RiseupORM::raw_execute(
            "SELECT {$column}, COUNT(*) as count FROM " . self::TABLE_TRANSACTIONS . " GROUP BY {$column}"
        );
        $result = array();
        foreach ($rows as $row) {
            $result[$row[$column]] = (int) $row['count'];
        }
        return $result;
    }

    /**
     * Cleanup old transactions using ORM.
     *
     * @param int $days_to_keep Number of days to keep.
     *
     * @return int Number of deleted records.
     */
    public function cleanup_old_transactions($days_to_keep = 365) {
        if (!$this->is_ready()) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($days_to_keep * 86400));
            
            $deleted = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->where_lt('created_at', $cutoff)
                ->delete();
                
            $this->file_logger->info('Cleanup complete', array('deleted' => $deleted));

            return $deleted;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to cleanup transactions');

            return 0;
        }
    }
}
