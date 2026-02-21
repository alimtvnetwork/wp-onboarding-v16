<?php
/**
 * WorkerExecuteTrait — async and sync snapshot execution entry points.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;

trait WorkerExecuteTrait {

    public function execute(array $config): array {
        $start_time = microtime(true);

        $sizeCheck = $this->validatePreSnapshotSize();
        if ($sizeCheck !== null) {
            return $sizeCheck;
        }

        $prepared = $this->prepareSnapshotDir($config);
        $isPreparationFailed = BooleanHelpers::isResultFailed($prepared);

        if ($isPreparationFailed) {
            return $prepared;
        }

        try {
            $rootPdo = $this->initRootDb($prepared['snapshot_dir'], $config);
            $seed_order = $this->populateAndGetSeedOrder($rootPdo, $config);
            $rootPdo = null;

            $this->initProgressRecords($seed_order);
            $job_id = $this->createJob($prepared['snapshot_dir'], $seed_order, $config);
            $isJobCreationFailed = ($job_id === null || $job_id === false || $job_id === 0);

            if ($isJobCreationFailed) {
                $this->cleanupOrphanedDir($prepared['snapshot_dir']);

                return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Failed to create snapshot job');
            }

            $this->scheduleNextBatch($job_id);

            return $this->buildAsyncSnapshotResult($prepared, $seed_order, $job_id, $start_time);
        } catch (Throwable $e) {
            $this->cleanupOrphanedDir($prepared['snapshot_dir']);
            $this->log(LogLevelType::Error->value, 'Per-table snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage());
        }
    }

    public function executeSynchronous(array $config): array {
        $start_time = microtime(true);

        $sizeCheck = $this->validatePreSnapshotSize();
        if ($sizeCheck !== null) {
            return $sizeCheck;
        }

        $prepared = $this->prepareSnapshotDir($config);
        $isPreparationFailed = BooleanHelpers::isResultFailed($prepared);

        if ($isPreparationFailed) {
            return $prepared;
        }

        try {
            $rootPdo = $this->initRootDb($prepared['snapshot_dir'], $config);
            $seed_order = $this->populateAndGetSeedOrder($rootPdo, $config);
            $this->initProgressRecords($seed_order);

            $export = $this->exportBatchesSynchronously($seed_order, $prepared['snapshot_dir'], $rootPdo);
            $this->rootDb->updateStats($rootPdo, $export['exported_tables'], $export['total_rows']);
            $rootPdo = null;

            return $this->buildSyncSnapshotResult($prepared, $export, $start_time);
        } catch (Throwable $e) {
            $this->cleanupOrphanedDir($prepared['snapshot_dir']);
            $this->log(LogLevelType::Error->value, 'Synchronous snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage());
        }
    }

    /**
     * Validate estimated database size against the configured maximum.
     *
     * @return array|null Error result if size exceeds limit, null if OK.
     */
    private function validatePreSnapshotSize(): ?array {
        $maxBytes = SnapshotConfigType::MaxSizeMb->value * 1024 * 1024;

        $sizeResult = $this->wpdb->get_var(
            "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()"
        );

        $estimatedBytes = (int) ($sizeResult ?? 0);
        $isOverLimit = ($estimatedBytes > $maxBytes);

        if ($isOverLimit) {
            $sizeMb = round($estimatedBytes / 1024 / 1024, 1);
            $this->log(LogLevelType::Error->value, 'Pre-snapshot size validation failed', array(
                'estimated_mb' => $sizeMb, 'max_mb' => SnapshotConfigType::MaxSizeMb->value,
            ));

            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => "Database size ({$sizeMb} MB) exceeds the maximum allowed snapshot size (" . SnapshotConfigType::MaxSizeMb->value . " MB)",
            );
        }

        return null;
    }

    /**
     * Remove an orphaned snapshot directory after a failed operation.
     */
    private function cleanupOrphanedDir(string $dir): void {
        $isDirExisting = PathHelper::dirExists($dir);
        if ($isDirExisting) {
            PathHelper::deleteDirectory($dir);
            $this->log(LogLevelType::Warn->value, 'Cleaned up orphaned snapshot directory', array('path' => $dir));
        }
    }
}
