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
 *
 * Queries INFORMATION_SCHEMA to build a directed graph of table
 * dependencies and returns a topologically sorted seed order.
 */
class RiseupDependencyAnalyzer {

    use AnalyzerQueryTrait;
    use AnalyzerGraphTrait;

    /** @var wpdb */
    private $wpdb;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDependencyAnalyzer|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null $logger Logger instance.
     * @return RiseupDependencyAnalyzer
     */
    public static function getInstance($logger = null) {
        if (self::$instance === null && $logger) {
            self::$instance = new self($logger);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct($logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
    }
}
