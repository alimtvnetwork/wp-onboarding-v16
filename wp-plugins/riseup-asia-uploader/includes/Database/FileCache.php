<?php
/**
 * Riseup Asia Uploader - File Hash Cache
 *
 * SQLite-backed file hash caching for efficient sync comparisons.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/FileCacheScanTrait.php';
require_once __DIR__ . '/Traits/FileCacheStoreTrait.php';

/**
 * Class RiseupFileCache
 *
 * Manages cached file hashes in the plugin's SQLite database.
 */
class RiseupFileCache {

    use FileCacheScanTrait;
    use FileCacheStoreTrait;

    /** @var RiseupFileCache|null */
    private static $instance = null;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger $logger File logger.
     * @param RiseupDatabase    $db     Database instance.
     * @return RiseupFileCache
     */
    public static function getInstance($logger, $db) {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
