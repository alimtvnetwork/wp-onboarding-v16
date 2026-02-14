<?php
/**
 * Riseup Asia Uploader - Database Handler
 *
 * SQLite database for transaction logging using the micro-ORM.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/DatabaseConnectionTrait.php';
require_once __DIR__ . '/Traits/DatabaseMigrationsEarlyTrait.php';
require_once __DIR__ . '/Traits/DatabaseMigrationsLateTrait.php';
require_once __DIR__ . '/Traits/DatabaseQueryTrait.php';

/**
 * Class RiseupDatabase
 *
 * Handles all SQLite database operations for transaction logging.
 */
class RiseupDatabase {

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

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupDatabase|null */
    private static $instance = null;

    /** @var bool */
    private $isInitAttempted = false;

    /**
     * Get singleton instance.
     *
     * @return RiseupDatabase
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
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->fileLogger->info('Database constructor called');
    }
}
