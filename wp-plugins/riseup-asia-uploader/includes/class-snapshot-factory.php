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
     * Reset all cached instances (useful for testing).
     */
    public static function reset() {
        self::$detector = null;
        self::$cleaner = null;
    }
}
