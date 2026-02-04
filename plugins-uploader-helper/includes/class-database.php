<?php
/**
 * Rise Up Uploader - Database Handler
 *
 * SQLite database for transaction logging with PDO.
 *
 * @package RiseUpUploader
 * @since   1.2.0
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

            // Create tables.
            $this->create_tables();
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Database initialization failed: ' . $e->getMessage());
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
     * Log a transaction.
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
            $sql = "INSERT INTO " . RISEUP_TABLE_TRANSACTIONS . " 
                    (action, plugin_slug, post_id, user_login, user_id, ip_address, details, status, error_msg, created_at)
                    VALUES (:action, :plugin_slug, :post_id, :user_login, :user_id, :ip_address, :details, :status, :error_msg, :created_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array(
                ':action'      => $action,
                ':plugin_slug' => $plugin_slug,
                ':post_id'     => $post_id,
                ':user_login'  => $user_login,
                ':user_id'     => $user_id,
                ':ip_address'  => $ip_address,
                ':details'     => !empty($details) ? json_encode($details) : null,
                ':status'      => $status,
                ':error_msg'   => $error_msg,
                ':created_at'  => gmdate('Y-m-d\TH:i:s\Z'),
            ));

            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Failed to log transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Query transactions with filtering and pagination.
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

        $where_clauses = array();
        $params = array();

        // Filter by plugin.
        if (!empty($filters['plugin'])) {
            $where_clauses[] = 'plugin_slug = :plugin';
            $params[':plugin'] = $filters['plugin'];
        }

        // Filter by action (supports comma-separated list).
        if (!empty($filters['action'])) {
            $actions = array_map('trim', explode(',', $filters['action']));
            $action_placeholders = array();
            foreach ($actions as $i => $action) {
                $key = ':action' . $i;
                $action_placeholders[] = $key;
                $params[$key] = $action;
            }
            $where_clauses[] = 'action IN (' . implode(',', $action_placeholders) . ')';
        }

        // Filter by user.
        if (!empty($filters['user'])) {
            $where_clauses[] = 'user_login = :user';
            $params[':user'] = $filters['user'];
        }

        // Filter by status.
        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        // Filter by date range.
        if (!empty($filters['from'])) {
            $where_clauses[] = 'created_at >= :from';
            $params[':from'] = $filters['from'] . 'T00:00:00Z';
        }
        if (!empty($filters['to'])) {
            $where_clauses[] = 'created_at <= :to';
            $params[':to'] = $filters['to'] . 'T23:59:59Z';
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        try {
            // Get total count.
            $count_sql = "SELECT COUNT(*) as total FROM " . RISEUP_TABLE_TRANSACTIONS . " " . $where_sql;
            $count_stmt = $this->pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total = (int) $count_stmt->fetch()['total'];

            // Get paginated results.
            $sql = "SELECT id, action, plugin_slug, post_id, user_login, user_id, ip_address, details, status, error_msg, created_at
                    FROM " . RISEUP_TABLE_TRANSACTIONS . " 
                    " . $where_sql . "
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $logs = $stmt->fetchAll();

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
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Failed to query transactions: ' . $e->getMessage());
            return array('total' => 0, 'logs' => array());
        }
    }

    /**
     * Get transaction by ID.
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
            $sql = "SELECT * FROM " . RISEUP_TABLE_TRANSACTIONS . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array(':id' => (int) $id));
            $log = $stmt->fetch();

            if ($log && !empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }

            return $log ?: null;
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Failed to get transaction: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get statistics summary.
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
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM " . RISEUP_TABLE_TRANSACTIONS);
            $stats['total_transactions'] = (int) $stmt->fetch()['total'];

            // Transactions by action.
            $stmt = $this->pdo->query("SELECT action, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY action");
            $stats['by_action'] = array();
            while ($row = $stmt->fetch()) {
                $stats['by_action'][$row['action']] = (int) $row['count'];
            }

            // Transactions by status.
            $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " GROUP BY status");
            $stats['by_status'] = array();
            while ($row = $stmt->fetch()) {
                $stats['by_status'][$row['status']] = (int) $row['count'];
            }

            // Last 24 hours.
            $yesterday = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM " . RISEUP_TABLE_TRANSACTIONS . " WHERE created_at >= :yesterday");
            $stmt->execute(array(':yesterday' => $yesterday));
            $stats['last_24h'] = (int) $stmt->fetch()['count'];

            return $stats;
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Failed to get stats: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Cleanup old transactions.
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
            $stmt = $this->pdo->prepare("DELETE FROM " . RISEUP_TABLE_TRANSACTIONS . " WHERE created_at < :cutoff");
            $stmt->execute(array(':cutoff' => $cutoff));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('[RiseUp Uploader] Failed to cleanup transactions: ' . $e->getMessage());
            return 0;
        }
    }
}
