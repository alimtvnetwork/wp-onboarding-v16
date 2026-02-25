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
use RiseupAsia\Database\Traits\DatabaseConvenienceTrait;
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
    use DatabaseConvenienceTrait;
    use DatabaseMigrationsEarlyTrait;
    use DatabaseMigrationsLateTrait;
    use DatabaseQueryTrait;

    private ?PDO $pdo = null;
    private string $dbPath = '';
    private FileLogger $fileLogger;
    private static ?self $instance = null;
    private bool $isInitAttempted = false;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self {
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
