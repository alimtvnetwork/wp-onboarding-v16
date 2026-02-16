<?php
/**
 * Riseup Asia Uploader - File Hash Cache
 *
 * SQLite-backed file hash caching for efficient sync comparisons.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Database
 * @since   1.10.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Database\Traits\FileCacheScanTrait;
use RiseupAsia\Database\Traits\FileCacheStoreTrait;
use RiseupAsia\Logging\FileLogger;

/**
 * Class FileCache
 *
 * Manages cached file hashes in the plugin's SQLite database.
 */
class FileCache {

    use FileCacheScanTrait;
    use FileCacheStoreTrait;

    private static ?FileCache $instance = null;
    private FileLogger $logger;
    private Database $db;

    /**
     * Get singleton instance.
     */
    public static function getInstance(FileLogger $logger, Database $db): static {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }

        return self::$instance;
    }

    private function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}

class_alias(FileCache::class, 'RiseupFileCache');
