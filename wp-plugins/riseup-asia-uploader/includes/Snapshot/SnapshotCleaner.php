<?php
/**
 * Riseup Asia Uploader - Snapshot Cleaner
 *
 * @package RiseupAsia\Snapshot
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Snapshot\Traits\CleanerRetentionTrait;
use RiseupAsia\Snapshot\Traits\CleanerDeletionTrait;
use RiseupAsia\Snapshot\Traits\CleanerOrphanTrait;
use RiseupAsia\Snapshot\Traits\CleanerStorageTrait;
use RiseupAsia\Snapshot\Traits\CleanerHelperTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;

class SnapshotCleaner {

    use CleanerRetentionTrait;
    use CleanerDeletionTrait;
    use CleanerOrphanTrait;
    use CleanerStorageTrait;
    use CleanerHelperTrait;

    private FileLogger $logger;
    private Database $db;

    public function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    public function execute(array $options = array()): array {
        $start = microtime(true);
        $isDryRun = BooleanHelpers::hasValue($options['dry_run'] ?? null);

        $results = ResultHelper::ok(array(
            'retention'         => array('deleted' => 0, 'skipped_master' => 0, 'details' => array()),
            'orphans'           => array(ResponseKeyType::Removed->value => 0, ResponseKeyType::Files->value => array()),
            'stuck'             => array('cleaned' => 0, 'ids' => array()),
            ResponseKeyType::Errors->value => array(),
            'dry_run'           => $isDryRun,
            'space_freed_bytes' => 0,
        ));

        $settings = $this->loadSettings($options);

        $results = $this->executeRetentionPhase($settings, $isDryRun, $results);
        $results = $this->executeOrphanPhase($isDryRun, $results);
        $results = $this->executeStuckPhase($isDryRun, $results);

        $results[ResponseKeyType::Success->value]  = empty($results[ResponseKeyType::Errors->value]);
        $results[ResponseKeyType::Duration->value] = round(microtime(true) - $start, 3);

        $totalDeleted = $results['retention']['deleted']
            + $results['orphans'][ResponseKeyType::Removed->value]
            + $results['stuck']['cleaned'];

        $this->log(LogLevelType::Info->value, 'Cleanup complete', array(
            'deleted_total' => $totalDeleted,
            'space_freed'   => PathHelper::formatBytes($results['space_freed_bytes']),
            ResponseKeyType::Duration->value => $results[ResponseKeyType::Duration->value],
            'dry_run'       => $isDryRun,
        ));

        $isLiveRunWithDeletions = ($isDryRun === false) && $totalDeleted > 0;
        if ($isLiveRunWithDeletions) {
            $this->logCleanupAudit($results);
        }

        return $results;
    }

    public function runCleanup(array $settings): array {
        $result = $this->execute($settings);

        return array(
            'deleted_by_policy' => $result['retention']['deleted'] ?? 0,
            'deleted_orphans'   => $result['orphans'][ResponseKeyType::Removed->value] ?? 0,
            'deleted_failed'    => $result['stuck']['cleaned'] ?? 0,
            'space_freed_bytes' => $result['space_freed_bytes'] ?? 0,
            ResponseKeyType::Errors->value => $result[ResponseKeyType::Errors->value] ?? array(),
        );
    }

    private function executeRetentionPhase(
        array $settings,
        bool $isDryRun,
        array $results,
    ): array {
        try {
            if ($settings['retention_type'] === RetentionType::None->value) {
                $this->log(LogLevelType::Debug->value, 'Retention policy is "none" - skipping policy cleanup');
            } else {
                $retention = $this->cleanByRetention($settings, $isDryRun);
                $results['retention'] = $retention;
                $results['space_freed_bytes'] += $retention['bytes_freed'] ?? 0;
            }
        } catch (Throwable $e) {
            $results[ResponseKeyType::Errors->value][] = 'Retention cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Retention cleanup failed', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $results;
    }

    private function executeOrphanPhase(bool $isDryRun, array $results): array {
        try {
            $orphans = $this->cleanupOrphanFiles($isDryRun);
            $results['orphans'] = $orphans;
            $results['space_freed_bytes'] += $orphans['bytes_freed'] ?? 0;
        } catch (Throwable $e) {
            $results[ResponseKeyType::Errors->value][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Orphan cleanup failed', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $results;
    }

    private function executeStuckPhase(bool $isDryRun, array $results): array {
        try {
            $stuck = $this->cleanupStuckSnapshots($isDryRun);
            $results['stuck'] = $stuck;
        } catch (Throwable $e) {
            $results[ResponseKeyType::Errors->value][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Stuck snapshot cleanup failed', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $results;
    }
}
