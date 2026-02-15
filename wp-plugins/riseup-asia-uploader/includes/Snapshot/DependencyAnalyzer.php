<?php
/**
 * Riseup Asia Uploader - Dependency Analyzer
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/AnalyzerQueryTrait.php';
require_once __DIR__ . '/Traits/AnalyzerGraphTrait.php';

/**
 * Dependency Analyzer class.
 */
class RiseupDependencyAnalyzer {

    use AnalyzerQueryTrait;
    use AnalyzerGraphTrait;

    private \wpdb $wpdb;
    private RiseupFileLogger $logger;
    private static ?self $instance = null;

    public static function getInstance(?RiseupFileLogger $logger = null): self {
        if (self::$instance === null && $logger) {
            self::$instance = new self($logger);
        }
        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
    }
}
