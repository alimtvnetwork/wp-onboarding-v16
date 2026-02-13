<?php
/**
 * Riseup Asia Uploader - Root Database Manager
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once dirname(__FILE__) . '/Traits/RootDbSchemaTrait.php';
require_once dirname(__FILE__) . '/Traits/RootDbRegistrationTrait.php';

/**
 * Root Database Manager class.
 *
 * Creates and manages a-root.db with snapshot metadata,
 * table inventories, and dependency graphs.
 */
class RiseupRootDb {

    use RootDbSchemaTrait;
    use RootDbRegistrationTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDependencyAnalyzer */
    private $analyzer;

    /** @var RiseupRootDb|null */
    private static $instance = null;

    /** @return RiseupRootDb */
    public static function getInstance($logger = null, $analyzer = null) {
        if (self::$instance === null && $logger && $analyzer) {
            self::$instance = new self($logger, $analyzer);
        }
        return self::$instance;
    }

    /** Constructor. */
    private function __construct($logger, $analyzer) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
    }

    /**
     * Create a new a-root.db at the given path with full schema.
     *
     * @param string $filepath Full path to the a-root.db file.
     * @return PDO The opened PDO connection.
     */
    public function create($filepath) {
        $this->log(LogLevelType::Info->value, 'Creating a-root.db', array('path' => $filepath));

        $dir = dirname($filepath);
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            RiseupPathUtils::ensure_dir($dir);
        }

        $pdo = new PDO('sqlite:' . $filepath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');

        $this->createSchema($pdo);
        $this->log(LogLevelType::Info->value, 'a-root.db schema created');

        return $pdo;
    }

    /**
     * Log a message with root-db context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [ROOT-DB]';
        $full = $prefix . ' ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method($full);
        } else {
            $this->logger->info($full);
        }
    }
}
