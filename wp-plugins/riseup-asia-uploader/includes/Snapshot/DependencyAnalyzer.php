<?php
/**
 * Riseup Asia Uploader - Dependency Analyzer
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Snapshot
 * @since   1.12.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use LogicException;
use wpdb;
use RiseupAsia\Snapshot\Traits\AnalyzerQueryTrait;
use RiseupAsia\Snapshot\Traits\AnalyzerGraphTrait;
use RiseupAsia\Logging\FileLogger;

/**
 * Dependency Analyzer class.
 */
class DependencyAnalyzer {
    use AnalyzerQueryTrait;
    use AnalyzerGraphTrait;

    private wpdb $wpdb;
    private FileLogger $logger;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null): self {
        $isReadyToInit = self::$instance === null && $logger;

        if ($isReadyToInit) {
            self::$instance = new self($logger);
        }

        if (self::$instance === null) {
            throw new LogicException('DependencyAnalyzer::getInstance() called before initialization.');
        }

        return self::$instance;
    }

    private function __construct(FileLogger $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
    }
}
