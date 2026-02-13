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

    /** @var RiseupSnapshotDetector|null */
    private static $detector = null;

    /** @var RiseupSnapshotCleaner|null */
    private static $cleaner = null;

    /** Get or create the RiseupSnapshotDetector singleton. */
    public static function detector($logger = null, $db = null) {
        if (self::$detector === null) {
            require_once dirname(__FILE__) . '/SnapshotDetector.php';
            self::$detector = new RiseupSnapshotDetector(
                $logger ?: RiseupFileLogger::getInstance(),
                $db ?: RiseupDatabase::getInstance()
            );
        }
        return self::$detector;
    }

    /** Get or create the RiseupSnapshotCleaner singleton. */
    public static function cleaner($logger = null, $db = null) {
        if (self::$cleaner === null) {
            require_once dirname(__FILE__) . '/SnapshotCleaner.php';
            self::$cleaner = new RiseupSnapshotCleaner(
                $logger ?: RiseupFileLogger::getInstance(),
                $db ?: RiseupDatabase::getInstance()
            );
        }
        return self::$cleaner;
    }

    /** Get the RiseupSnapshotScheduler singleton. */
    public static function scheduler($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/SnapshotScheduler.php';
        return RiseupSnapshotScheduler::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    /** Get the RiseupSnapshotManager singleton. */
    public static function manager($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/SnapshotManager.php';
        return RiseupSnapshotManager::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    /** Get the RiseupSnapshotWorker singleton. */
    public static function worker($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/SnapshotWorker.php';
        require_once dirname(__FILE__) . '/DependencyAnalyzer.php';
        require_once dirname(__FILE__) . '/../Database/RootDb.php';
        $l = $logger ?: RiseupFileLogger::getInstance();
        $d = $db ?: RiseupDatabase::getInstance();
        $analyzer = RiseupDependencyAnalyzer::getInstance($l);
        $rootDb   = RiseupRootDb::getInstance($l, $analyzer);
        return RiseupSnapshotWorker::getInstance($l, $d, $rootDb, $analyzer);
    }

    /** Get the RiseupSnapshotOrchestrator singleton. */
    public static function orchestrator($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/SnapshotOrchestrator.php';
        $l = $logger ?: RiseupFileLogger::getInstance();
        $d = $db ?: RiseupDatabase::getInstance();
        return RiseupSnapshotOrchestrator::getInstance($l, $d, self::manager($l, $d));
    }

    /** Get the RiseupSnapshotExporter singleton. */
    public static function exporter($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/SnapshotExporter.php';
        return RiseupSnapshotExporter::getInstance(
            $logger ?: RiseupFileLogger::getInstance(),
            $db ?: RiseupDatabase::getInstance()
        );
    }

    /** Reset all cached instances (useful for testing). */
    public static function reset() {
        self::$detector = null;
        self::$cleaner = null;
    }
}
