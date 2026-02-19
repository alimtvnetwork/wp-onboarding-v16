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
                'title' => $body['title'] ?? null, 'scope' => $body['scope'] ?? SnapshotScopeType::WordPress->value,
                'type' => $body['type'] ?? SnapshotModeType::Full->value, 'settings' => $body['settings'] ?? null,
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
            'success' => $result['success'], 'directory' => $result['directory'] ?? null,
            'tables' => $result['tables'] ?? 0, 'total_rows' => $result['total_rows'] ?? 0,
            'errors' => $result['errors'] ?? array(), 'duration' => $result['duration'] ?? 0,
            'error' => $result['error'] ?? null,
        ), $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::ServerError->value);
    }

    /** Extract cleanup options from body. */
    private function extractCleanupOptions(array $body): array {
        return array(
            'retention_type' => $body['retention_type'] ?? null, 'retention_days' => $body['retention_days'] ?? null,
            'retention_count' => $body['retention_count'] ?? null, 'dry_run' => $body['dry_run'] ?? false,
        );
    }

    /** Log cleanup if not a dry run. */
    private function logCleanupIfNotDryRun(array $body, array $result): void {
        $isDryRun = ($body['dry_run'] ?? false);

        if ($isDryRun) {
            return;
        }

        $this->logger->logPluginAction(ActionType::SnapshotCleanup->value, LogCategoryType::Snapshot->value,
            $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
            array('retention_removed' => $result['retention']['deleted'] ?? 0, 'orphans_removed' => $result['orphans']['removed'] ?? 0,
                'stuck_marked' => $result['stuck']['cleaned'] ?? 0, 'duration' => $result['duration'] ?? 0),
            $result['success'] ? null : 'Cleanup encountered errors');
    }

    /** Build cleanup response. */
    private function buildCleanupResponse(array $result): WP_REST_Response {
        return new WP_REST_Response(array(
            'success' => $result['success'], 'retention' => $result['retention'], 'orphans' => $result['orphans'],
            'stuck' => $result['stuck'], 'duration' => $result['duration'], 'dry_run' => $result['dry_run'], 'errors' => $result['errors'],
        ), HttpStatusType::Ok->value);
    }

    /** Build a progress error response. */
    private function buildProgressError(
        string $message,
        int $code,
        string $errorCode = '',
    ): WP_REST_Response {
        $data = array('IsSuccess' => false, 'HasAnyErrors' => true, 'error' => $message);
        if ($errorCode) {
            $data['code'] = $errorCode;
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
            'job_id' => $p['job_id'], 'status' => $p['status'],
            'total_tables' => $p['total_tables'], 'tables_exported' => $p['tables_exported'],
            'total_rows' => $p['total_rows'], 'pool_size' => $p['pool_size'],
            'total_batches' => $p['total_batches'], 'current_batch' => $p['current_batch'],
            'percent' => $p['percent'], 'errors' => $p['errors'],
            'table_progress' => $p['table_progress'], 'created_at' => $p['created_at'],
            'updated_at' => $p['updated_at'], 'completed_at' => $p['completed_at'],
        ), HttpStatusType::Ok->value);
    }
}
