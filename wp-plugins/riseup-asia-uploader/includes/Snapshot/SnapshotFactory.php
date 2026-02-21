<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Database\Database;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Logging\FileLogger;

class SnapshotFactory {
    private static ?SnapshotDetector $detector = null;
    private static ?SnapshotCleaner $cleaner = null;

    public static function detector(?FileLogger $logger = null, ?Database $db = null): SnapshotDetector {
        if (self::$detector === null) {
            self::$detector = new SnapshotDetector(
                $logger ?: FileLogger::getInstance(),
                $db ?: Database::getInstance()
            );
        }

        return self::$detector;
    }

    public static function cleaner(?FileLogger $logger = null, ?Database $db = null): SnapshotCleaner {
        if (self::$cleaner === null) {
            self::$cleaner = new SnapshotCleaner(
                $logger ?: FileLogger::getInstance(),
                $db ?: Database::getInstance()
            );
        }

        return self::$cleaner;
    }

    public static function scheduler(?FileLogger $logger = null, ?Database $db = null): SnapshotScheduler {
        return SnapshotScheduler::getInstance(
            $logger ?: FileLogger::getInstance(),
            $db ?: Database::getInstance()
        );
    }

    public static function manager(?FileLogger $logger = null, ?Database $db = null): SnapshotManager {
        return SnapshotManager::getInstance(
            $logger ?: FileLogger::getInstance(),
            $db ?: Database::getInstance()
        );
    }

    public static function worker(?FileLogger $logger = null, ?Database $db = null): SnapshotWorker {
        $l = $logger ?: FileLogger::getInstance();
        $d = $db ?: Database::getInstance();
        $analyzer = DependencyAnalyzer::getInstance($l);
        $rootDb   = RootDb::getInstance($l, $analyzer);
        return SnapshotWorker::getInstance($l, $d, $rootDb, $analyzer);
    }

    public static function orchestrator(?FileLogger $logger = null, ?Database $db = null): SnapshotOrchestrator {
        $l = $logger ?: FileLogger::getInstance();
        $d = $db ?: Database::getInstance();
        return SnapshotOrchestrator::getInstance($l, $d, self::manager($l, $d));
    }

    public static function exporter(?FileLogger $logger = null, ?Database $db = null): SnapshotExporter {
        return SnapshotExporter::getInstance(
            $logger ?: FileLogger::getInstance(),
            $db ?: Database::getInstance()
        );
    }

    public static function reset(): void {
        self::$detector = null;
        self::$cleaner = null;
    }
}
