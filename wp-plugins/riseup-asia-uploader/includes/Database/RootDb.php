<?php
/**
 * Riseup Asia Uploader - Root Database Manager
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Database
 * @since   1.12.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use LogicException;
use PDO;
use Throwable;

use RiseupAsia\Database\Traits\RootDbRegistrationTrait;
use RiseupAsia\Database\Traits\RootDbSchemaTrait;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Snapshot\DependencyAnalyzer;

class RootDb {
    use RootDbSchemaTrait;
    use RootDbRegistrationTrait;

    private FileLogger $logger;
    private DependencyAnalyzer $analyzer;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?DependencyAnalyzer $analyzer = null): self {
        $isReadyToInit = self::$instance === null && $logger && $analyzer;

        if ($isReadyToInit) {
            self::$instance = new self($logger, $analyzer);
        }

        if (self::$instance === null) {
            throw new LogicException('RootDb::getInstance() called before initialization.');
        }

        return self::$instance;
    }

    private function __construct(FileLogger $logger, DependencyAnalyzer $analyzer) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
    }

    public function create(string $filepath): PDO {
        $this->log(LogLevelType::Info->value, 'Creating a-root.db', array('path' => $filepath));

        $dir = dirname($filepath);

        if (PathHelper::isDirMissing($dir)) {
            PathHelper::makeDirectory($dir);
        }

        $pdo = new PDO('sqlite:' . $filepath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');

        $this->createSchema($pdo);
        $this->log(LogLevelType::Info->value, 'a-root.db schema created');

        return $pdo;
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $prefix = '[SNAPSHOT] [ROOT-DB]';
        $full = $prefix . ' ' . $message;
        $hasContext = !empty($context);

        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        $method = strtolower($level);

        if (method_exists($this->logger, $method)) {
            $this->logger->$method($full);
        } else {
            $this->logger->info($full);
        }
    }

    private function logError(\Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Error->value, $message, $context);
    }
}
