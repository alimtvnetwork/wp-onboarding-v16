<?php
/**
 * SnapshotBackupExecTrait — Full and incremental backup REST handlers.
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
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;
use RiseupAsia\Snapshot\DependencyAnalyzer;
use RiseupAsia\Snapshot\IncrementalBackup;
use RiseupAsia\Database\RootDb;

trait SnapshotBackupExecTrait {

    /** Handle full end-to-end backup. */
    public function handleFullBackup(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->logBackupInitiated(ActionType::SnapshotFullBackup->value, $body);

            $orchestrator = $this->createFullBackupOrchestrator();
            $result = $orchestrator->executeFullBackup($this->extractFullBackupOptions($body));

            $this->logBackupComplete(ActionType::SnapshotFullBackup->value, $result);

            return $this->buildFullBackupResponse($result);
        }, 'full_backup');
    }

    /** Handle incremental backup. */
    public function handleIncrementalBackup(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->logBackupInitiated(ActionType::SnapshotIncremental->value, $body);

            $master_dir = $this->resolveIncrementalMasterDir($body);
            if ($master_dir instanceof WP_REST_Response) {

                return $master_dir;
            }

            $incremental = $this->createIncrementalBackup();
            $result = $incremental->execute($master_dir, array('title' => $body['title'] ?? null));

            $this->logIncrementalComplete($result);

            return $this->buildIncrementalResponse($result);
        }, 'incremental_backup');
    }

    /** Log a backup initiation event. */
    private function logBackupInitiated(string $action, array $body) {
        $this->logger->logPluginAction($action, LogCategoryType::Snapshot->value, StatusType::Success->value,
            array('title' => $body['title'] ?? null, ResponseKeyType::Scope->value => $body[ResponseKeyType::Scope->value] ?? null, ResponseKeyType::Phase->value => 'initiated'));
    }

    /** Create the orchestrator for full backup. */
    private function createFullBackupOrchestrator(): SnapshotOrchestrator {
        $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);

        return SnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
    }

    /** Extract full backup options from request body. */
    private function extractFullBackupOptions(array $body): array {

        return array(
            'title' => $body['title'] ?? null, ResponseKeyType::Scope->value => $body[ResponseKeyType::Scope->value] ?? null,
            'include_plugins' => $body['include_plugins'] ?? null,
            'plugin_selection' => $body['plugin_selection'] ?? null,
            'compression' => $body['compression'] ?? null,
        );
    }

    /** Log full backup completion. */
    private function logBackupComplete(string $action, array $result) {
        $this->logger->logPluginAction($action, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array(ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null, ResponseKeyType::Tables->value => $result[ResponseKeyType::Tables->value] ?? 0,
                ResponseKeyType::TotalRows->value => $result[ResponseKeyType::TotalRows->value] ?? 0, ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0, ResponseKeyType::Phase->value => 'complete'),
            $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Backup failed'));
    }

    /** Build full backup response. */
    private function buildFullBackupResponse(array $result): WP_REST_Response {

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => $result[ResponseKeyType::Success->value], ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
            ResponseKeyType::Directory->value => $result[ResponseKeyType::Directory->value] ?? null, ResponseKeyType::Tables->value => $result[ResponseKeyType::Tables->value] ?? 0,
            ResponseKeyType::TotalRows->value => $result[ResponseKeyType::TotalRows->value] ?? 0, ResponseKeyType::Plugins->value => $result[ResponseKeyType::Plugins->value] ?? 0,
            ResponseKeyType::ZipSize->value => $result[ResponseKeyType::ZipSize->value] ?? 0, ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0,
            ResponseKeyType::Errors->value => $result[ResponseKeyType::Errors->value] ?? array(), ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value] ?? null, ResponseKeyType::Phase->value => $result[ResponseKeyType::Phase->value] ?? null,
        ), $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
    }

    /** Resolve the master directory for an incremental backup. */
    private function resolveIncrementalMasterDir(array $body): string|WP_REST_Response {
        $incremental = $this->createIncrementalBackup();

        $master_dir = $body['master_dir'] ?? null;
        $isMasterDirEmpty = ($master_dir === null || $master_dir === '');
        if ($isMasterDirEmpty) {
            $master_dir = $incremental->findLatestMasterSnapshot();
        }

        $isMasterDirInvalid = ($master_dir === null || $master_dir === '' || PathHelper::isDirMissing($master_dir));
        if ($isMasterDirInvalid) {

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'No master (full) snapshot found. Create a full backup first.',
            ), HttpStatusType::BadRequest->value);
        }

        return $master_dir;
    }

    /** Create an IncrementalBackup instance. */
    private function createIncrementalBackup(): IncrementalBackup {
        $rootDb = RootDb::getInstance($this->fileLogger, DependencyAnalyzer::getInstance($this->fileLogger));

        return IncrementalBackup::getInstance($this->fileLogger, $this->db, $rootDb);
    }

    /** Log incremental backup completion. */
    private function logIncrementalComplete(array $result) {
        $this->logger->logPluginAction(ActionType::SnapshotIncremental->value, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array(ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null, ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0,
                ResponseKeyType::TotalNewRows->value => $result[ResponseKeyType::TotalNewRows->value] ?? 0, ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0, ResponseKeyType::Phase->value => 'complete'),
            $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Incremental backup failed'));
    }

    /** Build incremental backup response. */
    private function buildIncrementalResponse(array $result): WP_REST_Response {

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => $result[ResponseKeyType::Success->value], ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
            ResponseKeyType::Sequence->value => $result[ResponseKeyType::Sequence->value] ?? null, ResponseKeyType::FolderName->value => $result[ResponseKeyType::FolderName->value] ?? null,
            ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0, ResponseKeyType::TotalNewRows->value => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
            ResponseKeyType::Tables->value => $result[ResponseKeyType::Tables->value] ?? array(), ResponseKeyType::Duration->value => $result[ResponseKeyType::Duration->value] ?? 0,
            ResponseKeyType::Errors->value => $result[ResponseKeyType::Errors->value] ?? array(), ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value] ?? null,
        ), $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
    }
}
