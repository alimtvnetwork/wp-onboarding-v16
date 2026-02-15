<?php
/**
 * Riseup Asia Uploader - Snapshot Factory
 *
 * Centralizes construction of snapshot-related classes (Detector, Cleaner, Scheduler)
 * using lazy-loaded singletons.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Factory class.
 */
class RiseupSnapshotFactory {

    private static ?RiseupSnapshotDetector $detector = null;
    private static ?RiseupSnapshotCleaner $cleaner = null;

    public static function detector(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotDetector {
        if (self::$detector === null) {
            require_once dirname(__FILE__) . '/SnapshotDetector.php';
            self::$detector = new RiseupSnapshotDetector(
                $logger ?: RiseupFileLogger::getInstance(),
                $db ?: RiseupDatabase::getInstance()
            );
        }
        return self::$detector;
    }

    public static function cleaner(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotCleaner {
        if (self::$cleaner === null) {
            require_once dirname(__FILE__) . '/SnapshotCleaner.php';
            self::$cleaner = new RiseupSnapshotCleaner(
                $logger ?: RiseupFileLogger::getInstance(),
                $db ?: RiseupDatabase::getInstance()
            );
        }
        return self::$cleaner;
    }

    public static function scheduler(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotScheduler {
        require_once dirname(__FILE__) . '/SnapshotScheduler.php';
        return RiseupSnapshotScheduler::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    public static function manager(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotManager {
        require_once dirname(__FILE__) . '/SnapshotManager.php';
        return RiseupSnapshotManager::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    public static function worker(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotWorker {
        require_once dirname(__FILE__) . '/SnapshotWorker.php';
        require_once dirname(__FILE__) . '/DependencyAnalyzer.php';
        require_once dirname(__FILE__) . '/../Database/RootDb.php';
        $l = $logger ?: RiseupFileLogger::getInstance();
        $d = $db ?: RiseupDatabase::getInstance();
        $analyzer = RiseupDependencyAnalyzer::getInstance($l);
        $rootDb   = RiseupRootDb::getInstance($l, $analyzer);
        return RiseupSnapshotWorker::getInstance($l, $d, $rootDb, $analyzer);
    }

    public static function orchestrator(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotOrchestrator {
        require_once dirname(__FILE__) . '/SnapshotOrchestrator.php';
        $l = $logger ?: RiseupFileLogger::getInstance();
        $d = $db ?: RiseupDatabase::getInstance();
        return RiseupSnapshotOrchestrator::getInstance($l, $d, self::manager($l, $d));
    }

    public static function exporter(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): RiseupSnapshotExporter {
        require_once dirname(__FILE__) . '/SnapshotExporter.php';
        return RiseupSnapshotExporter::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    public static function reset(): void {
        self::$detector = null;
        self::$cleaner = null;
    }
}
