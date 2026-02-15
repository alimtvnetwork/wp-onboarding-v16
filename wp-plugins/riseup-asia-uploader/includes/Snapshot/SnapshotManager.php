<?php
/**
 * Riseup Asia Uploader - Snapshot Manager
 *
 * Central manager for database snapshot operations.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/ManagerCoreTrait.php';
require_once __DIR__ . '/Traits/ManagerRestoreTrait.php';
require_once __DIR__ . '/Traits/ManagerTableRestoreTrait.php';
require_once __DIR__ . '/Traits/ManagerExportTrait.php';
require_once __DIR__ . '/Traits/ManagerImportTrait.php';
require_once __DIR__ . '/Traits/ManagerSettingsTrait.php';

/**
 * Snapshot Manager class.
 */
class RiseupSnapshotManager {

    use ManagerCoreTrait;
    use ManagerRestoreTrait;
    use ManagerTableRestoreTrait;
    use ManagerExportTrait;
    use ManagerImportTrait;
    use ManagerSettingsTrait;

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private RiseupSnapshotDetector $detector;
    private \wpdb $wpdb;
    private static ?self $instance = null;

    public static function getInstance(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): self {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db = $db;
        require_once dirname(__FILE__) . '/SnapshotFactory.php';
        $this->detector = RiseupSnapshotFactory::detector($logger, $db);

        global $wpdb;
        $this->wpdb = $wpdb;
    }
}
