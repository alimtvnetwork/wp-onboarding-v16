<?php
/**
 * Riseup Asia Uploader - Snapshot Factory
 *
 * Centralizes construction of snapshot-related classes (Detector, Cleaner, Scheduler)
 * using lazy-loaded singletons. Eliminates duplicated require_once + instantiation
 * blocks scattered across class-admin.php, class-snapshot-scheduler.php, and
 * class-snapshot-manager.php.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Factory class.
 *
 * Provides lazy-loaded singleton accessors for all snapshot subsystem classes.
 * Each accessor handles require_once and construction with proper logger/db injection.
 *
 * Usage:
 *   $detector  = RiseupSnapshotFactory::detector();
 *   $cleaner   = RiseupSnapshotFactory::cleaner();
 *   $scheduler = RiseupSnapshotFactory::scheduler();
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotFactory {

    /**
     * Cached detector instance.
     *
     * @var RiseupSnapshotDetector|null
     */
    private static $detector = null;

    /**
     * Cached cleaner instance.
     *
     * @var RiseupSnapshotCleaner|null
     */
    private static $cleaner = null;

    /**
     * Get or create the RiseupSnapshotDetector singleton.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotDetector
     */
    public static function detector($logger = null, $db = null) {
        if (self::$detector === null) {
            require_once dirname(__FILE__) . '/class-snapshot-detector.php';
            self::$detector = new RiseupSnapshotDetector(
                $logger ?: Riseup_File_Logger::get_instance(),
                $db ?: Riseup_Database::get_instance()
            );
        }
        return self::$detector;
    }

    /**
     * Get or create the RiseupSnapshotCleaner singleton.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotCleaner
     */
    public static function cleaner($logger = null, $db = null) {
        if (self::$cleaner === null) {
            require_once dirname(__FILE__) . '/class-snapshot-cleaner.php';
            self::$cleaner = new RiseupSnapshotCleaner(
                $logger ?: Riseup_File_Logger::get_instance(),
                $db ?: Riseup_Database::get_instance()
            );
        }
        return self::$cleaner;
    }

    /**
     * Get the RiseupSnapshotScheduler singleton.
     *
     * Delegates to the scheduler's own getInstance() method.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotScheduler
     */
    public static function scheduler($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/class-snapshot-scheduler.php';
        return RiseupSnapshotScheduler::getInstance(
            $logger ?: Riseup_File_Logger::get_instance(),
            $db ?: Riseup_Database::get_instance()
        );
    }

    /**
     * Get the RiseupSnapshotManager singleton.
     *
     * Delegates to the manager's own getInstance() method.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotManager
     */
    public static function manager($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/class-snapshot-manager.php';
        return RiseupSnapshotManager::getInstance(
            $logger ?: Riseup_File_Logger::get_instance(),
            $db ?: Riseup_Database::get_instance()
        );
    }

    /**
     * Get the RiseupSnapshotWorker singleton.
     *
     * Delegates to the worker's own getInstance() method.
     * Requires RiseupRootDb and RiseupDependencyAnalyzer as additional dependencies.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotWorker
     */
    public static function worker($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/class-snapshot-worker.php';
        require_once dirname(__FILE__) . '/class-dependency-analyzer.php';
        require_once dirname(__FILE__) . '/class-root-db.php';
        $l = $logger ?: Riseup_File_Logger::get_instance();
        $d = $db ?: Riseup_Database::get_instance();
        $analyzer = RiseupDependencyAnalyzer::getInstance($l);
        $rootDb   = RiseupRootDb::getInstance($l, $analyzer);
        return RiseupSnapshotWorker::getInstance($l, $d, $rootDb, $analyzer);
    }

    /**
     * Get the RiseupSnapshotOrchestrator singleton.
     *
     * Delegates to the orchestrator's own getInstance() method.
     * Automatically resolves the manager dependency.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotOrchestrator
     */
    public static function orchestrator($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/class-snapshot-orchestrator.php';
        $l = $logger ?: Riseup_File_Logger::get_instance();
        $d = $db ?: Riseup_Database::get_instance();
        return RiseupSnapshotOrchestrator::getInstance($l, $d, self::manager($l, $d));
    }

    /**
     * Get the RiseupSnapshotExporter singleton.
     *
     * @param Riseup_File_Logger|null $logger Optional logger override.
     * @param Riseup_Database|null    $db     Optional database override.
     * @return RiseupSnapshotExporter
     */
    public static function exporter($logger = null, $db = null) {
        require_once dirname(__FILE__) . '/class-snapshot-exporter.php';
        return RiseupSnapshotExporter::getInstance(
            $logger ?: Riseup_File_Logger::get_instance(),
            $db ?: Riseup_Database::get_instance()
        );
    }

    /**
     * Reset all cached instances (useful for testing).
     */
    public static function reset() {
        self::$detector = null;
        self::$cleaner = null;
    }
}
