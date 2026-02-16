<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Snapshot\Traits\ExporterPublicApiTrait;
use RiseupAsia\Snapshot\Traits\ExporterBuildTrait;
use RiseupAsia\Snapshot\Traits\ExporterHelpersTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotExporter {
    use ExporterPublicApiTrait;
    use ExporterBuildTrait;
    use ExporterHelpersTrait;

    private FileLogger $logger;
    private Database $db;
    private static ?SnapshotExporter $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?Database $db = null): ?static {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    private function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db     = $db;
    }
}
