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
use RiseupAsia\Helpers\BooleanHelpers;

trait WorkerExecuteTrait {

    public function execute(array $config): array {
        $start_time = microtime(true);
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
                return array('success' => false, 'error' => 'Failed to create snapshot job');
            }

            $this->scheduleNextBatch($job_id);

            return $this->buildAsyncSnapshotResult($prepared, $seed_order, $job_id, $start_time);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Per-table snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function executeSynchronous(array $config): array {
        $start_time = microtime(true);
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
            $this->log(LogLevelType::Error->value, 'Synchronous snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array('success' => false, 'error' => $e->getMessage());
        }
    }
}
