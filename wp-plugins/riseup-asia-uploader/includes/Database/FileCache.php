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

    private static ?RiseupFileCache $instance = null;
    private RiseupFileLogger $logger;
    private RiseupDatabase $db;

    /**
     * Get singleton instance.
     */
    public static function getInstance(RiseupFileLogger $logger, RiseupDatabase $db): static {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }

        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
