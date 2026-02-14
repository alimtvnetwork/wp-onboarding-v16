<?php
/**
 * Riseup Asia Uploader - Snapshot Cleaner
 *
 * Consolidated cleanup engine handling retention policy enforcement,
 * orphan file cleanup, stuck snapshot handling, and storage management.
 * Supports dry-run mode, master snapshot protection, and audit trail logging.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once dirname(__FILE__) . '/Traits/CleanerRetentionTrait.php';
require_once dirname(__FILE__) . '/Traits/CleanerDeletionTrait.php';
require_once dirname(__FILE__) . '/Traits/CleanerOrphanTrait.php';
require_once dirname(__FILE__) . '/Traits/CleanerStorageTrait.php';
require_once dirname(__FILE__) . '/Traits/CleanerUtilsTrait.php';

/**
 * Snapshot Cleaner class.
 *
 * Manages cleanup of old snapshots based on retention policies,
 * removes orphan files, handles stuck snapshots, and provides storage statistics.
 */
class RiseupSnapshotCleaner {

    use CleanerRetentionTrait;
    use CleanerDeletionTrait;
    use CleanerOrphanTrait;
    use CleanerStorageTrait;
    use CleanerUtilsTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Execute full cleanup with unified response format.
     *
     * @param array $options Optional overrides (retention_type, retention_days, retention_count, dry_run).
     * @return array Cleanup result summary.
     */
    public function execute($options = array()) {
        $start = microtime(true);
        $dry_run = !empty($options['dry_run']);

        $results = array(
            'success'           => true,
            'retention'         => array('deleted' => 0, 'skipped_master' => 0, 'details' => array()),
            'orphans'           => array('removed' => 0, 'files' => array()),
            'stuck'             => array('cleaned' => 0, 'ids' => array()),
            'errors'            => array(),
            'dry_run'           => $dry_run,
            'space_freed_bytes' => 0,
        );

        $settings = $this->loadSettings($options);

        $results = $this->executeRetentionPhase($settings, $dry_run, $results);
        $results = $this->executeOrphanPhase($dry_run, $results);
        $results = $this->executeStuckPhase($dry_run, $results);

        $results['success']  = empty($results['errors']);
        $results['duration'] = round(microtime(true) - $start, 3);

        $total_deleted = $results['retention']['deleted']
            + $results['orphans']['removed']
            + $results['stuck']['cleaned'];

        $this->log(LogLevelType::Info->value, 'Cleanup complete', array(
            'deleted_total' => $total_deleted,
            'space_freed'   => RiseupPathUtils::formatBytes($results['space_freed_bytes']),
            'duration'      => $results['duration'],
            'dry_run'       => $dry_run,
        ));

        if (!$dry_run && $total_deleted > 0) {
            $this->logCleanupAudit($results);
        }

        return $results;
    }

    /**
     * Run full cleanup (legacy entry point for scheduler).
     *
     * @param array $settings Snapshot settings.
     * @return array Legacy-format result.
     */
    public function runCleanup($settings) {
        $result = $this->execute($settings);

        return array(
            'deleted_by_policy' => $result['retention']['deleted'] ?? 0,
            'deleted_orphans'   => $result['orphans']['removed'] ?? 0,
            'deleted_failed'    => $result['stuck']['cleaned'] ?? 0,
            'space_freed_bytes' => $result['space_freed_bytes'] ?? 0,
            'errors'            => $result['errors'] ?? array(),
        );
    }

    /**
     * Execute retention cleanup phase.
     *
     * @param array $settings Resolved settings.
     * @param bool  $dry_run  Simulate only.
     * @param array $results  Current results.
     * @return array Updated results.
     */
    private function executeRetentionPhase($settings, $dry_run, $results) {
        try {
            if ($settings['retention_type'] === 'none') {
                $this->log(LogLevelType::Debug->value, 'Retention policy is "none" - skipping policy cleanup');
            } else {
                $retention = $this->cleanByRetention($settings, $dry_run);
                $results['retention'] = $retention;
                $results['space_freed_bytes'] += $retention['bytes_freed'] ?? 0;
            }
        } catch (Throwable $e) {
            $results['errors'][] = 'Retention cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Retention cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }

    /**
     * Execute orphan cleanup phase.
     *
     * @param bool  $dry_run Simulate only.
     * @param array $results Current results.
     * @return array Updated results.
     */
    private function executeOrphanPhase($dry_run, $results) {
        try {
            $orphans = $this->cleanupOrphanFiles($dry_run);
            $results['orphans'] = $orphans;
            $results['space_freed_bytes'] += $orphans['bytes_freed'] ?? 0;
        } catch (Throwable $e) {
            $results['errors'][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Orphan cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }

    /**
     * Execute stuck snapshot cleanup phase.
     *
     * @param bool  $dry_run Simulate only.
     * @param array $results Current results.
     * @return array Updated results.
     */
    private function executeStuckPhase($dry_run, $results) {
        try {
            $stuck = $this->cleanupStuckSnapshots($dry_run);
            $results['stuck'] = $stuck;
        } catch (Throwable $e) {
            $results['errors'][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Stuck snapshot cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }
}
