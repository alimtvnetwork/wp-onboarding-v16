<?php
/**
 * Rise Up Asia - Database Handler
 *
 * SQLite database for transaction logging using the micro-ORM.
 *
 * @package RiseUpAsia
 * @since   1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseUp_Database
 *
 * Handles all SQLite database operations for transaction logging.
 */
class RiseUp_Database {

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
     * Singleton instance.
     *
     * @var RiseUp_Database|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseUp_Database
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
        $this->db_path = $this->get_database_path();
        $this->init_database();
    }

    /**
     * Get the database file path.
     *
     * @return string
     */
    private function get_database_path() {
        $plugin_dir = dirname(__DIR__);
        $data_dir   = $plugin_dir . '/' . RISEUP_DATA_DIR;

        // Create data directory if it doesn't exist.
        if (!file_exists($data_dir)) {
            wp_mkdir_p($data_dir);
            // Add .htaccess to protect database.
            file_put_contents($data_dir . '/.htaccess', 'Deny from all');
            // Add index.php for additional protection.
            file_put_contents($data_dir . '/index.php', '<?php // Silence is golden.');
        }

        return $data_dir . '/' . RISEUP_DB_FILENAME;
    }

    /**
     * Initialize the database connection and create tables.
     *
     * @return void
     */
    private function init_database() {
        try {
            $this->pdo = new PDO('sqlite:' . $this->db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable WAL mode for better performance.
            if (RISEUP_DB_WAL_MODE) {
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            }

            // Enable auto-vacuum.
            $this->pdo->exec('PRAGMA auto_vacuum = INCREMENTAL');

            // Configure the ORM with our PDO instance.
            RiseUp_ORM::configure($this->pdo);

            // Create tables.
            $this->create_tables();
        } catch (PDOException $e) {
            error_log(RISEUP_LOG_PREFIX . ' Database initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    private function create_tables() {
        $sql = "CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_TRANSACTIONS . " (
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

        // Create indexes for common queries.
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_action ON " . RISEUP_TABLE_TRANSACTIONS . " (action)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_plugin_slug ON " . RISEUP_TABLE_TRANSACTIONS . " (plugin_slug)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_login ON " . RISEUP_TABLE_TRANSACTIONS . " (user_login)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON " . RISEUP_TABLE_TRANSACTIONS . " (status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON " . RISEUP_TABLE_TRANSACTIONS . " (created_at)");
    }

    /**
     * Get PDO instance.
     *
     * @return PDO|null
     */
    public function get_pdo() {
        return $this->pdo;
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
        if (!$this->pdo) {
            return false;
        }

        try {
            return RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
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
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to log transaction: ' . $e->getMessage());
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
        if (!$this->pdo) {
            return array('total' => 0, 'logs' => array());
        }

        // Sanitize limit.
        $limit = min(max(1, (int) $limit), RISEUP_MAX_LIMIT);
        $offset = max(0, (int) $offset);

        try {
            // Build count query
            $count_query = RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS);
            $data_query = RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS);

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

            // Decode JSON details.
            foreach ($logs as &$log) {
                if (!empty($log['details'])) {
                    $log['details'] = json_decode($log['details'], true);
                }
            }

            return array(
                'total' => $total,
                'logs'  => $logs,
            );
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to query transactions: ' . $e->getMessage());
            return array('total' => 0, 'logs' => array());
        }
    }

    /**
     * Apply filters to an ORM query.
     *
     * @param RiseUp_ORM $query   ORM query instance.
     * @param array      $filters Filters to apply.
     *
     * @return void
     */
    private function apply_filters($query, $filters) {
        // Filter by plugin.
        if (!empty($filters['plugin'])) {
            $query->where('plugin_slug', $filters['plugin']);
        }

        // Filter by action (supports comma-separated list).
        if (!empty($filters['action'])) {
            $actions = array_map('trim', explode(',', $filters['action']));
            if (count($actions) === 1) {
                $query->where('action', $actions[0]);
            } else {
                $query->where_in('action', $actions);
            }
        }

        // Filter by user.
        if (!empty($filters['user'])) {
            $query->where('user_login', $filters['user']);
        }

        // Filter by status.
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by date range.
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
        if (!$this->pdo) {
            return null;
        }

        try {
            $log = RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
                ->find_one((int) $id);

            if ($log && !empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }

            return $log;
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to get transaction: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get statistics summary using ORM.
     *
     * @return array Statistics.
     */
    public function get_stats() {
        if (!$this->pdo) {
            return array();
        }

        try {
            $stats = array();

            // Total transactions.
            $stats['total_transactions'] = RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)->count();

            // Transactions by action.
            $by_action = RiseUp_ORM::raw_execute(
                "SELECT action, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY action"
            );
            $stats['by_action'] = array();
            foreach ($by_action as $row) {
                $stats['by_action'][$row['action']] = (int) $row['count'];
            }

            // Transactions by status.
            $by_status = RiseUp_ORM::raw_execute(
                "SELECT status, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY status"
            );
            $stats['by_status'] = array();
            foreach ($by_status as $row) {
                $stats['by_status'][$row['status']] = (int) $row['count'];
            }

            // Last 24 hours.
            $yesterday = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
            $stats['last_24h'] = RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
                ->where_gte('created_at', $yesterday)
                ->count();

            return $stats;
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to get stats: ' . $e->getMessage());
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
        if (!$this->pdo) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($days_to_keep * 86400));
            
            return RiseUp_ORM::for_table(RISEUP_TABLE_TRANSACTIONS)
                ->where_lt('created_at', $cutoff)
                ->delete();
        } catch (Exception $e) {
            error_log(RISEUP_LOG_PREFIX . ' Failed to cleanup transactions: ' . $e->getMessage());
            return 0;
        }
    }
}
