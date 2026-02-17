<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use wpdb;
use RiseupAsia\Snapshot\Traits\ManagerCoreTrait;
use RiseupAsia\Snapshot\Traits\ManagerRestoreTrait;
use RiseupAsia\Snapshot\Traits\ManagerTableRestoreTrait;
use RiseupAsia\Snapshot\Traits\ManagerExportTrait;
use RiseupAsia\Snapshot\Traits\ManagerImportTrait;
use RiseupAsia\Snapshot\Traits\ManagerSettingsTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotManager {
    use ManagerCoreTrait;
    use ManagerRestoreTrait;
    use ManagerTableRestoreTrait;
    use ManagerExportTrait;
    use ManagerImportTrait;
    use ManagerSettingsTrait;

    private FileLogger $logger;
    private Database $db;
    private SnapshotDetector $detector;
    private wpdb $wpdb;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?Database $db = null): self {
        $isReadyToInit = self::$instance === null && $logger && $db;
        if ($isReadyToInit) {
            self::$instance = new self($logger, $db);
        }

        return self::$instance;
    }

    private function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
        $this->detector = SnapshotFactory::detector($logger, $db);
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}
