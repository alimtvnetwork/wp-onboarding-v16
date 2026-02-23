<?php
/**
 * SnapshotBackupOpsTrait — Export, cleanup, and progress REST handlers.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\DependencyAnalyzer;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Snapshot\SnapshotWorker;
use RiseupAsia\Snapshot\SnapshotFactory;

trait SnapshotBackupOpsTrait {

    public function handleExportPertable(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $analyzer = DependencyAnalyzer::getInstance($this->fileLogger);
            $rootDb = RootDb::getInstance($this->fileLogger, $analyzer);
            $worker = SnapshotWorker::getInstance($this->fileLogger, $this->db, $rootDb, $analyzer);

            $result = $worker->execute(array(
                ResponseKeyType::Title->value => $body[ResponseKeyType::Title->value] ?? null,
                ResponseKeyType::Scope->value => $body[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value,
                ResponseKeyType::Type->value => $body[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value,
                ResponseKeyType::Settings->value => $body[ResponseKeyType::Settings->value] ?? null,
            ));

            return $this->buildExportResponse($result);
        }, 'export_pertable');
    }

    public function handleSnapshotCleanup(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $cleaner = SnapshotFactory::cleaner($this->fileLogger, $this->db);
            $result = $cleaner->execute($this->extractCleanupOptions($body));

            $this->logCleanupIfNotDryRun($body, $result);

            return $this->buildCleanupResponse($result);
        }, 'snapshot_cleanup');
    }

    public function handleSnapshotProgress(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $job_id = $body['job_id'] ?? null;

            if (empty($job_id)) {

                return $this->buildProgressError('Missing required field: job_id', HttpStatusType::BadRequest->value);
            }

            $progress = $this->fetchJobProgress((int) $job_id);
            $isProgressMissing = ($progress === null || $progress === false);

            if ($isProgressMissing) {

                return $this->buildProgressError('Job not found', HttpStatusType::NotFound->value, 'JOB_NOT_FOUND');
            }

            return $this->buildProgressResponse($progress);
        }, 'snapshot_progress');
    }

    /** Build export response. */
    private function buildExportResponse(array $result): WP_REST_Response {

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => $result[ResponseKeyType::Success->value], ResponseKeyType::Directory->value => $result[ResponseKeyType::Directory->value] ?? null,
            ResponseKeyType::Tables->value => $result[ResponseKeyType::Tables->value] ?? 0, ResponseKeyType::TotalRows->value => $result[ResponseKeyType::TotalRows->value] ?? 0,
            ResponseKeyType::Errors->value => $result[ResponseKeyType::Errors->value] ?? array(), ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0,
            ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value] ?? null,
        ), $result[ResponseKeyType::Success->value] ? HttpStatusType::Ok->value : HttpStatusType::ServerError->value);
    }

    /** Extract cleanup options from body. */
    private function extractCleanupOptions(array $body): array {

        return array(
            'retention_type' => $body['retention_type'] ?? null, 'retention_days' => $body['retention_days'] ?? null,
            'retention_count' => $body['retention_count'] ?? null, ResponseKeyType::DryRun->value => $body[ResponseKeyType::DryRun->value] ?? false,
        );
    }

    /** Log cleanup if not a dry run. */
    private function logCleanupIfNotDryRun(array $body, array $result): void {
        $isDryRun = ($body[ResponseKeyType::DryRun->value] ?? false);

        if ($isDryRun) {

            return;
        }

        $this->logger->logPluginAction(ActionType::SnapshotCleanup->value, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array('retention_removed' => $result[ResponseKeyType::Retention->value][ResponseKeyType::Deleted->value] ?? 0, 'orphans_removed' => $result[ResponseKeyType::Orphans->value][ResponseKeyType::Removed->value] ?? 0,
                'stuck_marked' => $result[ResponseKeyType::Stuck->value][ResponseKeyType::Cleaned->value] ?? 0, ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0),
            $result[ResponseKeyType::Success->value] ? null : 'Cleanup encountered errors');
    }

    /** Build cleanup response. */
    private function buildCleanupResponse(array $result): WP_REST_Response {

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => $result[ResponseKeyType::Success->value], ResponseKeyType::Retention->value => $result[ResponseKeyType::Retention->value], ResponseKeyType::Orphans->value => $result[ResponseKeyType::Orphans->value],
            ResponseKeyType::Stuck->value => $result[ResponseKeyType::Stuck->value], ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value], ResponseKeyType::DryRun->value => $result[ResponseKeyType::DryRun->value], ResponseKeyType::Errors->value => $result[ResponseKeyType::Errors->value],
        ), HttpStatusType::Ok->value);
    }

    /** Build a progress error response. */
    private function buildProgressError(
        string $message,
        int $code,
        string $errorCode = '',
    ): WP_REST_Response {
        $data = array('IsSuccess' => false, 'HasAnyErrors' => true, ResponseKeyType::Error->value => $message);
        if ($errorCode) {
            $data[ResponseKeyType::Code->value] = $errorCode;
        }

        return new WP_REST_Response($data, $code);
    }

    /** Fetch job progress from the worker. */
    private function fetchJobProgress(int $jobId) {
        $rootDb = RootDb::getInstance($this->fileLogger, DependencyAnalyzer::getInstance($this->fileLogger));
        $worker = SnapshotWorker::getInstance($this->fileLogger, $this->db, $rootDb, DependencyAnalyzer::getInstance($this->fileLogger));

        return $worker->getJobProgress($jobId);
    }

    /** Build progress response. */
    private function buildProgressResponse(array $p): WP_REST_Response {

        return new WP_REST_Response(array(
            'IsSuccess' => true, 'HasAnyErrors' => false,
            ResponseKeyType::JobId->value => $p[ResponseKeyType::JobId->value], 'status' => $p['status'],
            ResponseKeyType::TotalTables->value => $p[ResponseKeyType::TotalTables->value], ResponseKeyType::TablesExported->value => $p[ResponseKeyType::TablesExported->value],
            ResponseKeyType::TotalRows->value => $p[ResponseKeyType::TotalRows->value], ResponseKeyType::PoolSize->value => $p[ResponseKeyType::PoolSize->value],
            ResponseKeyType::TotalBatches->value => $p[ResponseKeyType::TotalBatches->value], ResponseKeyType::CurrentBatch->value => $p[ResponseKeyType::CurrentBatch->value],
            'percent' => $p['percent'], ResponseKeyType::Errors->value => $p[ResponseKeyType::Errors->value],
            ResponseKeyType::TableProgress->value => $p[ResponseKeyType::TableProgress->value], ResponseKeyType::CreatedAt->value => $p[ResponseKeyType::CreatedAt->value],
            ResponseKeyType::UpdatedAt->value => $p[ResponseKeyType::UpdatedAt->value], ResponseKeyType::CompletedAt->value => $p[ResponseKeyType::CompletedAt->value],
        ), HttpStatusType::Ok->value);
    }
}
