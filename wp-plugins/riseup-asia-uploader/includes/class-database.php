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
        if (self::$instance === null) {
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
        
        // Ensure base directory exists
        if (!file_exists($base_dir)) {
            $this->file_logger->info('Creating base directory', array('dir' => $base_dir));
            
            if (!wp_mkdir_p($base_dir)) {
                $this->file_logger->error('Failed to create base directory', array('dir' => $base_dir));
                throw new Exception('Failed to create data directory: ' . $base_dir);
            }
            
            // Add .htaccess to protect database
            $htaccess_path = $base_dir . '/.htaccess';
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            if (@file_put_contents($htaccess_path, $htaccess_content) === false) {
                $this->file_logger->warn('Failed to create .htaccess', array('path' => $htaccess_path));
            } else {
                $this->file_logger->debug('.htaccess created', array('path' => $htaccess_path));
            }
            
            // Add index.php for additional protection
            $index_path = $base_dir . '/index.php';
            if (@file_put_contents($index_path, '<?php // Silence is golden.') === false) {
                $this->file_logger->warn('Failed to create index.php', array('path' => $index_path));
            } else {
                $this->file_logger->debug('index.php created', array('path' => $index_path));
            }
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
            // Check if PDO class exists
            if (!class_exists('PDO')) {
                $this->file_logger->error('PDO class not found - PHP PDO extension not installed');
                throw new Exception('PDO class not found. Please ensure the PHP PDO extension is installed and enabled.');
            }
            
            // Check if SQLite extension is available
            if (!extension_loaded('pdo_sqlite')) {
                $this->file_logger->error('PDO SQLite extension not loaded');
                throw new Exception('PDO SQLite extension is not available. Please enable pdo_sqlite in php.ini.');
            }
            
            $this->file_logger->debug('Creating PDO connection', array('path' => $this->db_path));
            
            $this->pdo = new PDO('sqlite:' . $this->db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $this->file_logger->info('PDO connection established');

            // Enable WAL mode for better performance
            if (RISEUP_DB_WAL_MODE) {
                $this->file_logger->debug('Enabling WAL mode');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
                $this->file_logger->info('WAL mode enabled');
            }

            // Enable auto-vacuum
            $this->file_logger->debug('Setting auto-vacuum');
            $this->pdo->exec('PRAGMA auto_vacuum = INCREMENTAL');

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
            
            // Future migrations can be added here:
            // if ($current_version < 2) { ... }
            
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
     * @param string      $action      Action type (use RISEUP_ACTION_* constants).
     * @param string|null $plugin_slug Plugin slug (for plugin operations).
     * @param int|null    $post_id     Post ID (for post operations).
     * @param string      $user_login  WordPress username.
     * @param int|null    $user_id     WordPress user ID.
     * @param string      $ip_address  Client IP address.
     * @param array       $details     Additional details (will be JSON encoded).
     * @param string      $status      Status (use RISEUP_STATUS_* constants).
     * @param string|null $error_msg   Error message if failed.
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
        $error_msg = null
    ) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready, cannot log transaction');
            return false;
        }

        try {
            $this->file_logger->debug('Logging transaction', array(
                'action' => $action,
                'status' => $status,
            ));
            
            $result = Riseup_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
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
                ->set('created_at', gmdate('Y-m-d\TH:i:s\Z'))
                ->save();
                
            $this->file_logger->info('Transaction logged', array('id' => $result));
            return $result;
            
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to log transaction');
            return false;
        }
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
