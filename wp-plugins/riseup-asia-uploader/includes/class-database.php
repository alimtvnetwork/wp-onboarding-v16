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
 * Class Riseup_Database
 *
 * Handles all SQLite database operations for transaction logging.
 */
class Riseup_Database {

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
     * @var Riseup_File_Logger
     */
    private $file_logger;

    /**
     * Singleton instance.
     *
     * @var Riseup_Database|null
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
     * @return Riseup_Database
     */
    public static function get_instance() {
        if (RiseupBooleanHelpers::is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = Riseup_File_Logger::get_instance();
        $this->file_logger->info('Database constructor called');
    }

    /**
     * Initialize database (lazy loading).
     *
     * @return bool True if successful.
     */
    public function init() {
        if ($this->init_attempted) {
            return RiseupBooleanHelpers::is_set($this->pdo);
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
        if (RiseupBooleanHelpers::is_falsy(RiseupInitHelpers::ensureDir($base_dir, true))) {
            $this->file_logger->error('Failed to create base directory', array('dir' => $base_dir));
            throw new Exception('Failed to create data directory: ' . $base_dir);
        }
        
        $db_path = $base_dir . '/' . RISEUP_DB_FILENAME;
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
            
            if (RiseupBooleanHelpers::is_null($this->pdo)) {
                $this->file_logger->error('SQLite connection returned null - check PDO/pdo_sqlite availability');
                return false;
            }

            // Configure the ORM with our PDO instance
            $this->file_logger->debug('Configuring ORM');
            Riseup_ORM::configure($this->pdo);
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
            $table_name = RISEUP_TABLE_TRANSACTIONS;
            $this->file_logger->debug('Migrating table', array('table' => $table_name));
            
            // Schema version tracking
            $this->file_logger->debug('Checking schema version');
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
                version INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL
            )");
            
            // Check current version
            $stmt = $this->pdo->query("SELECT MAX(version) as v FROM schema_version");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_version = (int) ($row['v'] ?? 0);
            $this->file_logger->info('Current schema version', array('version' => $current_version));
            
            // Migration v1: Initial transactions table
            if ($current_version < 1) {
                $this->file_logger->info('Applying migration v1: transactions table');
                
                $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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
                )";
                $this->pdo->exec($sql);
                $this->file_logger->debug('Table created', array('table' => $table_name));
                
                // Record migration
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (1, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v1 applied successfully');
            }

            // Create indexes for common queries
            $this->file_logger->debug('Creating indexes for table', array('table' => $table_name));
            $indexes = array(
                'idx_action'      => 'action',
                'idx_plugin_slug' => 'plugin_slug',
                'idx_user_login'  => 'user_login',
                'idx_status'      => 'status',
                'idx_created_at'  => 'created_at',
            );
            
            foreach ($indexes as $index_name => $column) {
                $this->file_logger->debug('Creating index', array('index' => $index_name, 'column' => $column));
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$index_name} ON {$table_name} ({$column})");
            }
            
            $this->file_logger->info('All indexes created');
            
            // Migration v2: Agent sites and actions tables
            if ($current_version < 2) {
                $this->file_logger->info('Applying migration v2: agent sites tables');
                
                // Agent sites table
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
                $this->file_logger->debug('Table created: agent_sites');
                
                // Agent actions log table
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
                $this->file_logger->debug('Table created: agent_actions');
                
                // Indexes for agent tables
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_sites_status ON agent_sites(status)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_site_id ON agent_actions(agent_site_id)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_action ON agent_actions(action)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_agent_actions_created ON agent_actions(created_at)");
                
                // Record migration
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (2, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v2 applied successfully');
            }
            
            // Migration v3: Enhanced transaction logging fields
            if ($current_version < 3) {
                $this->file_logger->info('Applying migration v3: enhanced transaction fields');
                
                // Add new columns to transactions table for richer logging
                // plugin_file: Full plugin file path (e.g., "akismet/akismet.php")
                // was_active: Previous active state before enable/disable
                // triggered_by: Source of the action (api, dashboard, agent_push, cron)
                // agent_site_id: If action was pushed from a master site
                $columns = array(
                    'plugin_file'    => 'TEXT',
                    'was_active'     => 'INTEGER', // 0/1 for boolean
                    'triggered_by'   => 'TEXT',    // api, dashboard, agent_push, cron
                    'agent_site_id'  => 'INTEGER', // FK to agent_sites if agent triggered
                );
                
                foreach ($columns as $column => $type) {
                    try {
                        $this->pdo->exec("ALTER TABLE " . RISEUP_TABLE_TRANSACTIONS . " ADD COLUMN {$column} {$type}");
                        $this->file_logger->debug("Column added: {$column}");
                    } catch (PDOException $e) {
                        // Column might already exist
                        $this->file_logger->debug("Column might exist: {$column}", array('error' => $e->getMessage()));
                    }
                }
                
                // Create index for triggered_by queries
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_triggered_by ON " . RISEUP_TABLE_TRANSACTIONS . "(triggered_by)");
                
                // Record migration
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (3, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v3 applied successfully');
            }
            
            // Migration v4: Source machine tracking for request attribution
            if ($current_version < 4) {
                $this->file_logger->info('Applying migration v4: source machine tracking');
                
                // Add source_machine column to track which server triggered the action
                try {
                    $this->pdo->exec("ALTER TABLE " . RISEUP_TABLE_TRANSACTIONS . " ADD COLUMN source_machine TEXT");
                    $this->file_logger->debug("Column added: source_machine");
                } catch (PDOException $e) {
                    // Column might already exist
                    $this->file_logger->debug("Column might exist: source_machine", array('error' => $e->getMessage()));
                }
                
                // Create index for source_machine queries
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_machine ON " . RISEUP_TABLE_TRANSACTIONS . "(source_machine)");
                
                // Record migration
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (4, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v4 applied successfully');
            }
            
            // Migration v5: Snapshot system tables
            if ($current_version < 5) {
                $this->file_logger->info('Applying migration v5: snapshot system tables');
                
                // Snapshots table - stores metadata about each snapshot
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_SNAPSHOTS . " (
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
                $this->file_logger->debug('Table created: snapshots');
                
                // Snapshot progress table - tracks per-table export progress
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_SNAPSHOT_PROGRESS . " (
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
                $this->file_logger->debug('Table created: snapshot_progress');
                
                // Indexes for snapshot tables
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_created ON " . RISEUP_TABLE_SNAPSHOTS . "(created_at DESC)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_status ON " . RISEUP_TABLE_SNAPSHOTS . "(status)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshots_provider ON " . RISEUP_TABLE_SNAPSHOTS . "(provider)");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_progress_snapshot ON " . RISEUP_TABLE_SNAPSHOT_PROGRESS . "(snapshot_id)");
                
                // Record migration
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (5, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v5 applied successfully');
            }
            
            // Migration v6: Remote plugins cache table
            if ($current_version < 6) {
                $this->file_logger->info('Applying migration v6: remote plugins cache');
                
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS remote_plugins_cache (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    site_id INTEGER NOT NULL,
                    data_json TEXT NOT NULL,
                    fetched_at TEXT NOT NULL,
                    expires_at TEXT NOT NULL
                )");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_rpc_site_id ON remote_plugins_cache(site_id)");
                
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (6, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v6 applied successfully');
            }
            
            // Migration v7: File hash cache table (Phase 41 - Sync System)
            if ($current_version < 7) {
                $this->file_logger->info('Applying migration v7: file hash cache table');
                
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_FILE_CACHE . " (
                    plugin_slug TEXT NOT NULL,
                    relative_path TEXT NOT NULL,
                    md5_hash TEXT NOT NULL,
                    modified_at TEXT NOT NULL,
                    file_size INTEGER NOT NULL DEFAULT 0,
                    cached_at TEXT NOT NULL,
                    PRIMARY KEY (plugin_slug, relative_path)
                )");
                
                // Indexes for efficient lookups
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_file_cache_slug ON " . RISEUP_TABLE_FILE_CACHE . "(plugin_slug)");
                
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (7, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v7 applied successfully');
            }
            
            // Migration v8: Snapshot settings key-value store
            if ($current_version < 8) {
                $this->file_logger->info('Applying migration v8: snapshot settings table');
                
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS snapshot_settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL,
                    type TEXT NOT NULL DEFAULT 'string',
                    updated_at TEXT NOT NULL
                )");
                
                // Seed default settings
                $defaults = array(
                    array('snapshot.mode',             'per_table',    'string'),
                    array('snapshot.backup_type',      'incremental',  'string'),
                    array('snapshot.worker_count',     '10',           'int'),
                    array('snapshot.storage_path',     'snapshots/',   'string'),
                    array('snapshot.include_plugins',  '1',            'bool'),
                    array('snapshot.plugin_selection',  'all',          'string'),
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
                
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO snapshot_settings (key, value, type, updated_at) VALUES (?, ?, ?, ?)");
                foreach ($defaults as $row) {
                    $stmt->execute(array($row[0], $row[1], $row[2], $now));
                }
                
                $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES (8, '" . gmdate('Y-m-d\TH:i:s\Z') . "')");
                $this->file_logger->info('Migration v8 applied successfully');
            }
            
            $this->file_logger->info('Database migration complete');
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Database migration failed');
            throw $e;
        }
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
     * @param string      $action         Action type (use RISEUP_ACTION_* constants).
     * @param string|null $plugin_slug    Plugin slug (for plugin operations).
     * @param int|null    $post_id        Post ID (for post operations).
     * @param string      $user_login     WordPress username.
     * @param int|null    $user_id        WordPress user ID.
     * @param string      $ip_address     Client IP address.
     * @param array       $details        Additional details (will be JSON encoded).
     * @param string      $status         Status (use RISEUP_STATUS_* constants).
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
        $status = RISEUP_STATUS_SUCCESS,
        $error_msg = null,
        $enhanced = array()
    ) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready, cannot log transaction');
            return false;
        }

        try {
            $this->file_logger->debug('Logging transaction', array(
                'action' => $action,
                'status' => $status,
                'enhanced' => $enhanced,
            ));
            
            $record = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
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
            
            // Apply enhanced fields if provided
            if (!empty($enhanced['plugin_file'])) {
                $record->set('plugin_file', $enhanced['plugin_file']);
            }
            if (isset($enhanced['was_active'])) {
                $record->set('was_active', $enhanced['was_active'] ? 1 : 0);
            }
            if (!empty($enhanced['triggered_by'])) {
                $record->set('triggered_by', $enhanced['triggered_by']);
            }
            if (!empty($enhanced['agent_site_id'])) {
                $record->set('agent_site_id', (int) $enhanced['agent_site_id']);
            }
            if (!empty($enhanced['source_machine'])) {
                $record->set('source_machine', $enhanced['source_machine']);
            }
            
            $result = $record->save();
                
            $this->file_logger->info('Transaction logged', array('id' => $result));
            return $result;
            
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to log transaction');
            return false;
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
            $params['status'] ?? RISEUP_STATUS_SUCCESS,
            $params['error_msg'] ?? null,
            array(
                'plugin_file'   => $params['plugin_file'] ?? null,
                'was_active'    => $params['was_active'] ?? null,
                'triggered_by'  => $params['triggered_by'] ?? null,
                'agent_site_id' => $params['agent_site_id'] ?? null,
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
    public function query_transactions($filters = array(), $limit = RISEUP_DEFAULT_LIMIT, $offset = 0) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready for query');
            return array('total' => 0, 'logs' => array());
        }

        // Sanitize limit
        $limit = min(max(1, (int) $limit), RISEUP_MAX_LIMIT);
        $offset = max(0, (int) $offset);

        try {
            $this->file_logger->debug('Querying transactions', array('filters' => $filters));
            
            // Build count query
            $count_query = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS);
            $data_query = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS);

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
     * @param Riseup_ORM $query   ORM query instance.
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
            $log = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
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
            $stats = array();

            // Total transactions
            $stats['total_transactions'] = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)->count();

            // Transactions by action
            $by_action = Riseup_ORM::raw_execute(
                "SELECT action, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY action"
            );
            $stats['by_action'] = array();
            foreach ($by_action as $row) {
                $stats['by_action'][$row['action']] = (int) $row['count'];
            }

            // Transactions by status
            $by_status = Riseup_ORM::raw_execute(
                "SELECT status, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY status"
            );
            $stats['by_status'] = array();
            foreach ($by_status as $row) {
                $stats['by_status'][$row['status']] = (int) $row['count'];
            }

            // Last 24 hours
            $yesterday = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
            $stats['last_24h'] = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
                ->where_gte('created_at', $yesterday)
                ->count();

            return $stats;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to get stats');
            return array();
        }
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
            
            $deleted = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
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
