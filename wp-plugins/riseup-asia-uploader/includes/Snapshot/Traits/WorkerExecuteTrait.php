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
use RiseupAsia\Helpers\ResultHelper;

trait WorkerExecuteTrait {
    public function execute(array $config): array {
        $startTime = microtime(true);
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
            return $this->initAndScheduleJob($prepared, $config, $startTime);
        } catch (Throwable $e) {
            $this->cleanupOrphanedDir($prepared[ResponseKeyType::SnapshotDir->value]);
            $this->logError($e, 'Per-table snapshot failed');

            return ResultHelper::errorFromException($e);
        }
    }

    private function initAndScheduleJob(array $prepared, array $config, float $startTime): array {
        $rootPdo = $this->initRootDb($prepared[ResponseKeyType::SnapshotDir->value], $config);
        $seedOrder = $this->populateAndGetSeedOrder($rootPdo, $config);
        $rootPdo = null;

        $this->initProgressRecords($seedOrder);
        $jobId = $this->createJob($prepared[ResponseKeyType::SnapshotDir->value], $seedOrder, $config);
        $isJobCreationFailed = ($jobId === null || $jobId === false || $jobId === 0);

        if ($isJobCreationFailed) {
            $this->cleanupOrphanedDir($prepared[ResponseKeyType::SnapshotDir->value]);

            return ResultHelper::error('Failed to create snapshot job');
        }

        $this->scheduleNextBatch($jobId);

        return $this->buildAsyncSnapshotResult($prepared, $seedOrder, $jobId, $startTime);
    }

    public function executeSynchronous(array $config): array {
        $startTime = microtime(true);
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
            return $this->runSynchronousExport($prepared, $config, $startTime);
        } catch (Throwable $e) {
            $this->cleanupOrphanedDir($prepared[ResponseKeyType::SnapshotDir->value]);
            $this->logError($e, 'Synchronous snapshot failed');

            return ResultHelper::errorFromException($e);
        }
    }

    private function runSynchronousExport(array $prepared, array $config, float $startTime): array {
        $rootPdo = $this->initRootDb($prepared[ResponseKeyType::SnapshotDir->value], $config);
        $seedOrder = $this->populateAndGetSeedOrder($rootPdo, $config);
        $this->initProgressRecords($seedOrder);

        $export = $this->exportBatchesSynchronously($seedOrder, $prepared[ResponseKeyType::SnapshotDir->value], $rootPdo);
        $this->rootDb->updateStats($rootPdo, $export[ResponseKeyType::ExportedTables->value], $export[ResponseKeyType::TotalRows->value]);
        $rootPdo = null;

        return $this->buildSyncSnapshotResult($prepared, $export, $startTime);
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
                'estimatedMb' => $sizeMb,
                'maxMb'       => SnapshotConfigType::MaxSizeMb->value,
            ));

            return ResultHelper::error(
                "Database size ({$sizeMb} MB) exceeds the maximum allowed snapshot size (" . SnapshotConfigType::MaxSizeMb->value . " MB)",
            );
        }

        return null;
    }

    private function cleanupOrphanedDir(string $dir): void {
        $isDirExisting = PathHelper::dirExists($dir);

        if ($isDirExisting) {
            PathHelper::deleteDirectory($dir);
            $this->log(LogLevelType::Warn->value, 'Cleaned up orphaned snapshot directory', array('path' => $dir));
        }
    }
}
