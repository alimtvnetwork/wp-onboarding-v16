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
use RiseupAsia\Enums\SettingsKeyType;
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
        $isDryRun = BooleanHelpers::hasValue($options[ResponseKeyType::DryRun->value] ?? null);

        $results = ResultHelper::ok(array(
            ResponseKeyType::Retention->value       => array(
                ResponseKeyType::Deleted->value       => 0,
                ResponseKeyType::SkippedMaster->value => 0,
                ResponseKeyType::Details->value       => array(),
            ),
            ResponseKeyType::Orphans->value         => array(
                ResponseKeyType::Removed->value => 0,
                ResponseKeyType::Files->value   => array(),
            ),
            ResponseKeyType::Stuck->value           => array(
                ResponseKeyType::Cleaned->value => 0,
                ResponseKeyType::Ids->value     => array(),
            ),
            ResponseKeyType::Errors->value          => array(),
            ResponseKeyType::DryRun->value          => $isDryRun,
            ResponseKeyType::SpaceFreedBytes->value => 0,
        ));

        $settings = $this->loadSettings($options);

        $results = $this->executeRetentionPhase($settings, $isDryRun, $results);
        $results = $this->executeOrphanPhase($isDryRun, $results);
        $results = $this->executeStuckPhase($isDryRun, $results);

        $results[ResponseKeyType::Success->value]  = empty($results[ResponseKeyType::Errors->value]);
        $results[ResponseKeyType::Duration->value] = round(microtime(true) - $start, 3);

        $totalDeleted = $results[ResponseKeyType::Retention->value][ResponseKeyType::Deleted->value]
            + $results[ResponseKeyType::Orphans->value][ResponseKeyType::Removed->value]
            + $results[ResponseKeyType::Stuck->value][ResponseKeyType::Cleaned->value];

        $this->log(LogLevelType::Info->value, 'Cleanup complete', array(
            'deletedTotal' => $totalDeleted,
            'spaceFreed'   => PathHelper::formatBytes($results[ResponseKeyType::SpaceFreedBytes->value]),
            ResponseKeyType::Duration->value => $results[ResponseKeyType::Duration->value],
            ResponseKeyType::DryRun->value   => $isDryRun,
        ));

        $hasDeletions = $totalDeleted > 0;
        $shouldAudit = !$isDryRun && $hasDeletions;

        if ($shouldAudit) {
            $this->logCleanupAudit($results);
        }

        return $results;
    }

    public function runCleanup(array $settings): array {
        $result = $this->execute($settings);

        return array(
            ResponseKeyType::DeletedByPolicy->value => $result[ResponseKeyType::Retention->value][ResponseKeyType::Deleted->value] ?? 0,
            ResponseKeyType::DeletedOrphans->value   => $result[ResponseKeyType::Orphans->value][ResponseKeyType::Removed->value] ?? 0,
            ResponseKeyType::DeletedFailed->value    => $result[ResponseKeyType::Stuck->value][ResponseKeyType::Cleaned->value] ?? 0,
            ResponseKeyType::SpaceFreedBytes->value  => $result[ResponseKeyType::SpaceFreedBytes->value] ?? 0,
            ResponseKeyType::Errors->value           => $result[ResponseKeyType::Errors->value] ?? array(),
        );
    }

    private function executeRetentionPhase(
        array $settings,
        bool $isDryRun,
        array $results,
    ): array {
        try {
            if ($settings[SettingsKeyType::RetentionType->value] === RetentionType::None->value) {
                $this->log(LogLevelType::Debug->value, 'Retention policy is "none" - skipping policy cleanup');
            } else {
                $retention = $this->cleanByRetention($settings, $isDryRun);
                $results[ResponseKeyType::Retention->value] = $retention;
                $results[ResponseKeyType::SpaceFreedBytes->value] += $retention[ResponseKeyType::BytesFreed->value] ?? 0;
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
            $results[ResponseKeyType::Orphans->value] = $orphans;
            $results[ResponseKeyType::SpaceFreedBytes->value] += $orphans[ResponseKeyType::BytesFreed->value] ?? 0;
        } catch (Throwable $e) {
            $results[ResponseKeyType::Errors->value][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Orphan cleanup failed', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $results;
    }

    private function executeStuckPhase(bool $isDryRun, array $results): array {
        try {
            $stuck = $this->cleanupStuckSnapshots($isDryRun);
            $results[ResponseKeyType::Stuck->value] = $stuck;
        } catch (Throwable $e) {
            $results[ResponseKeyType::Errors->value][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->log(LogLevelType::Error->value, 'Stuck snapshot cleanup failed', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $results;
    }
}
