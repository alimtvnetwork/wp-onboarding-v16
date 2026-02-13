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

    /** @var RiseupFileLogger */
    private $logger;
    /** @var RiseupDatabase */
    private $db;
    /** @var RiseupSnapshotDetector */
    private $detector;
    /** @var wpdb */
    private $wpdb;
    /** @var RiseupSnapshotManager|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance($logger = null, $db = null) {
        if (self::$instance === null && $logger && $db) {
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
        require_once dirname(__FILE__) . '/SnapshotFactory.php';
        $this->detector = RiseupSnapshotFactory::detector($logger, $db);

        global $wpdb;
        $this->wpdb = $wpdb;
    }
}
