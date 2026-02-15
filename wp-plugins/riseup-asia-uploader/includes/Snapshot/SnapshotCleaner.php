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
 */
class RiseupSnapshotCleaner {

    use CleanerRetentionTrait;
    use CleanerDeletionTrait;
    use CleanerOrphanTrait;
    use CleanerStorageTrait;
    use CleanerUtilsTrait;

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    public function execute(array $options = array()): array {
        $start = microtime(true);
        $dryRun = !empty($options['dry_run']);

        $results = array(
            'success'           => true,
            'retention'         => array('deleted' => 0, 'skipped_master' => 0, 'details' => array()),
            'orphans'           => array('removed' => 0, 'files' => array()),
            'stuck'             => array('cleaned' => 0, 'ids' => array()),
            'errors'            => array(),
            'dry_run'           => $dryRun,
            'space_freed_bytes' => 0,
        );

        $settings = $this->loadSettings($options);

        $results = $this->executeRetentionPhase($settings, $dryRun, $results);
        $results = $this->executeOrphanPhase($dryRun, $results);
        $results = $this->executeStuckPhase($dryRun, $results);

        $results['success']  = empty($results['errors']);
        $results['duration'] = round(microtime(true) - $start, 3);

        $totalDeleted = $results['retention']['deleted']
            + $results['orphans']['removed']
            + $results['stuck']['cleaned'];

        $this->log(LogLevelType::Info->value, 'Cleanup complete', array(
            'deleted_total' => $totalDeleted,
            'space_freed'   => RiseupPathUtils::formatBytes($results['space_freed_bytes']),
            'duration'      => $results['duration'],
            'dry_run'       => $dryRun,
        ));

        if (!$dryRun && $totalDeleted > 0) {
            $this->logCleanupAudit($results);
        }

        return $results;
    }

    public function runCleanup(array $settings): array {
        $result = $this->execute($settings);

        return array(
            'deleted_by_policy' => $result['retention']['deleted'] ?? 0,
            'deleted_orphans'   => $result['orphans']['removed'] ?? 0,
            'deleted_failed'    => $result['stuck']['cleaned'] ?? 0,
            'space_freed_bytes' => $result['space_freed_bytes'] ?? 0,
            'errors'            => $result['errors'] ?? array(),
        );
    }

    private function executeRetentionPhase(array $settings, bool $dryRun, array $results): array {
        try {
            if ($settings['retention_type'] === RetentionType::None->value) {
                $this->log(LogLevelType::Debug->value, 'Retention policy is "none" - skipping policy cleanup');
            } else {
                $retention = $this->cleanByRetention($settings, $dryRun);
                $results['retention'] = $retention;
                $results['space_freed_bytes'] += $retention['bytes_freed'] ?? 0;
            }
        } catch (Throwable $e) {
            $results['errors'][] = 'Retention cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Retention cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }

    private function executeOrphanPhase(bool $dryRun, array $results): array {
        try {
            $orphans = $this->cleanupOrphanFiles($dryRun);
            $results['orphans'] = $orphans;
            $results['space_freed_bytes'] += $orphans['bytes_freed'] ?? 0;
        } catch (Throwable $e) {
            $results['errors'][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Orphan cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }

    private function executeStuckPhase(bool $dryRun, array $results): array {
        try {
            $stuck = $this->cleanupStuckSnapshots($dryRun);
            $results['stuck'] = $stuck;
        } catch (Throwable $e) {
            $results['errors'][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Stuck snapshot cleanup failed', array('error' => $e->getMessage()));
        }
        return $results;
    }
}
