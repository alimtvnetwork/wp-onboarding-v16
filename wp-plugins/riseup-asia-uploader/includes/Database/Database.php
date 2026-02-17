<?php
/**
 * Riseup Asia Uploader - Database Handler
 *
 * SQLite database for transaction logging using the micro-ORM.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Database
 * @since   1.4.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Database\Traits\DatabaseConnectionTrait;
use RiseupAsia\Database\Traits\DatabaseMigrationsEarlyTrait;
use RiseupAsia\Database\Traits\DatabaseMigrationsLateTrait;
use RiseupAsia\Database\Traits\DatabaseQueryTrait;
use RiseupAsia\Logging\FileLogger;

/**
 * Class Database
 *
 * Handles all SQLite database operations for transaction logging.
 */
class Database {

    use DatabaseConnectionTrait;
    use DatabaseMigrationsEarlyTrait;
    use DatabaseMigrationsLateTrait;
    use DatabaseQueryTrait;

    /** Default and max limits for queries */

    /** Default and max limits for queries */
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 1000;

    /** @var PDO|null */
    private $pdo = null;

    /** @var string */
    private $dbPath;

    /** @var FileLogger */
    private $fileLogger;

    /** @var Database|null */
    private static $instance = null;

    /** @var bool */
    private $isInitAttempted = false;

    /**
     * Get singleton instance.
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->fileLogger->info('Database constructor called');
    }
}
