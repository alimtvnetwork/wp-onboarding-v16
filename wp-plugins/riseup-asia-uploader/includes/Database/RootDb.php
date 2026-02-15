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
 */
class RiseupRootDb {

    use RootDbSchemaTrait;
    use RootDbRegistrationTrait;

    private RiseupFileLogger $logger;
    private RiseupDependencyAnalyzer $analyzer;
    private static ?self $instance = null;

    public static function getInstance(?RiseupFileLogger $logger = null, ?RiseupDependencyAnalyzer $analyzer = null): self {
        if (self::$instance === null && $logger && $analyzer) {
            self::$instance = new self($logger, $analyzer);
        }
        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger, RiseupDependencyAnalyzer $analyzer) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
    }

    public function create(string $filepath): PDO {
        $this->log(LogLevelType::Info->value, 'Creating a-root.db', array('path' => $filepath));

        $dir = dirname($filepath);
        if (RiseupBooleanHelpers::isDirMissing($dir)) {
            RiseupPathUtils::ensureDir($dir);
        }

        $pdo = new PDO('sqlite:' . $filepath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');

        $this->createSchema($pdo);
        $this->log(LogLevelType::Info->value, 'a-root.db schema created');

        return $pdo;
    }

    private function log(string $level, string $message, array $context = array()): void {
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
