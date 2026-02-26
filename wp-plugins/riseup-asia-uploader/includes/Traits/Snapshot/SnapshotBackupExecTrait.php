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
use RiseupAsia\Enums\SnapshotPhaseType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;
use RiseupAsia\Snapshot\DependencyAnalyzer;
use RiseupAsia\Snapshot\IncrementalBackup;
use RiseupAsia\Database\RootDb;

trait SnapshotBackupExecTrait {

    public function handleFullBackup(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->logBackupInitiated(ActionType::SnapshotFullBackup->value, $body);
            $orchestrator = $this->createFullBackupOrchestrator();
            $result = $orchestrator->executeFullBackup($this->extractFullBackupOptions($body));
            $this->logBackupComplete(ActionType::SnapshotFullBackup->value, $result);

            return $this->buildFullBackupResponse($result);
        }, SnapshotPhaseType::FullBackup->value);
    }

    public function handleIncrementalBackup(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $this->logBackupInitiated(ActionType::SnapshotIncremental->value, $body);
            $masterDir = $this->resolveIncrementalMasterDir($body);

            if ($masterDir instanceof WP_REST_Response) {
                return $masterDir;
            }

            $incremental = $this->createIncrementalBackup();
            $result = $incremental->execute($masterDir, array(ResponseKeyType::Title->value => $body[ResponseKeyType::Title->value] ?? null));
            $this->logIncrementalComplete($result);

            return $this->buildIncrementalResponse($result);
        }, SnapshotPhaseType::IncrementalBackup->value);
    }

    private function logBackupInitiated(string $action, array $body) {
        $this->logger->logPluginAction($action, LogCategoryType::Snapshot->value, StatusType::Success->value,
            array(
                ResponseKeyType::Title->value => $body[ResponseKeyType::Title->value] ?? null,
                ResponseKeyType::Scope->value => $body[ResponseKeyType::Scope->value] ?? null,
                ResponseKeyType::Phase->value => SnapshotPhaseType::Initiated->value,
            ));
    }

    private function createFullBackupOrchestrator(): SnapshotOrchestrator {
        $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);

        return SnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
    }

    private function extractFullBackupOptions(array $body): array {
        return array(
            ResponseKeyType::Title->value          => $body[ResponseKeyType::Title->value] ?? null,
            ResponseKeyType::Scope->value          => $body[ResponseKeyType::Scope->value] ?? null,
            ResponseKeyType::IncludePlugins->value  => $body[ResponseKeyType::IncludePlugins->value] ?? null,
            ResponseKeyType::PluginSelection->value => $body[ResponseKeyType::PluginSelection->value] ?? null,
            ResponseKeyType::Compression->value     => $body[ResponseKeyType::Compression->value] ?? null,
        );
    }

    private function logBackupComplete(string $action, array $result) {
        $this->logger->logPluginAction($action, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array(
                ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
                ResponseKeyType::Tables->value     => $result[ResponseKeyType::Tables->value] ?? 0,
                ResponseKeyType::TotalRows->value  => $result[ResponseKeyType::TotalRows->value] ?? 0,
                ResponseKeyType::Duration->value   => $result[ResponseKeyType::Duration->value] ?? 0,
                ResponseKeyType::Phase->value      => SnapshotPhaseType::Complete->value,
            ),
            $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Backup failed'));
    }

    private function buildFullBackupResponse(array $result): WP_REST_Response {
        return new WP_REST_Response(array(
            ResponseKeyType::Success->value    => $result[ResponseKeyType::Success->value],
            ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
            ResponseKeyType::Directory->value  => $result[ResponseKeyType::Directory->value] ?? null,
            ResponseKeyType::Tables->value     => $result[ResponseKeyType::Tables->value] ?? 0,
            ResponseKeyType::TotalRows->value  => $result[ResponseKeyType::TotalRows->value] ?? 0,
            ResponseKeyType::Plugins->value    => $result[ResponseKeyType::Plugins->value] ?? 0,
            ResponseKeyType::ZipSize->value    => $result[ResponseKeyType::ZipSize->value] ?? 0,
            ResponseKeyType::Duration->value   => $result[ResponseKeyType::Duration->value] ?? 0,
            ResponseKeyType::Errors->value     => $result[ResponseKeyType::Errors->value] ?? array(),
            ResponseKeyType::Error->value      => $result[ResponseKeyType::Error->value] ?? null,
            ResponseKeyType::Phase->value      => $result[ResponseKeyType::Phase->value] ?? null,
        ), $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
    }

    private function resolveIncrementalMasterDir(array $body): string|WP_REST_Response {
        $incremental = $this->createIncrementalBackup();
        $masterDir = $body[ResponseKeyType::MasterDir->value] ?? null;
        $isMasterDirEmpty = ($masterDir === null || $masterDir === '');

        if ($isMasterDirEmpty) {
            $masterDir = $incremental->findLatestMasterSnapshot();
        }

        $isMasterDirInvalid = ($masterDir === null || $masterDir === '' || PathHelper::isDirMissing($masterDir));

        if ($isMasterDirInvalid) {
            return new WP_REST_Response(
                ResultHelper::error('No master (full) snapshot found. Create a full backup first.'),
                HttpStatusType::BadRequest->value,
            );
        }

        return $masterDir;
    }

    private function createIncrementalBackup(): IncrementalBackup {
        $rootDb = RootDb::getInstance($this->fileLogger, DependencyAnalyzer::getInstance($this->fileLogger));

        return IncrementalBackup::getInstance($this->fileLogger, $this->db, $rootDb);
    }

    private function logIncrementalComplete(array $result) {
        $this->logger->logPluginAction(ActionType::SnapshotIncremental->value, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array(
                ResponseKeyType::SnapshotId->value    => $result[ResponseKeyType::SnapshotId->value] ?? null,
                ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0,
                ResponseKeyType::TotalNewRows->value  => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
                ResponseKeyType::Duration->value      => $result[ResponseKeyType::Duration->value] ?? 0,
                ResponseKeyType::Phase->value         => SnapshotPhaseType::Complete->value,
            ),
            $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Incremental backup failed'));
    }

    private function buildIncrementalResponse(array $result): WP_REST_Response {
        return new WP_REST_Response(array(
            ResponseKeyType::Success->value       => $result[ResponseKeyType::Success->value],
            ResponseKeyType::SnapshotId->value    => $result[ResponseKeyType::SnapshotId->value] ?? null,
            ResponseKeyType::Sequence->value      => $result[ResponseKeyType::Sequence->value] ?? null,
            ResponseKeyType::FolderName->value    => $result[ResponseKeyType::FolderName->value] ?? null,
            ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0,
            ResponseKeyType::TotalNewRows->value  => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
            ResponseKeyType::Tables->value        => $result[ResponseKeyType::Tables->value] ?? array(),
            ResponseKeyType::Duration->value      => $result[ResponseKeyType::Duration->value] ?? 0,
            ResponseKeyType::Errors->value        => $result[ResponseKeyType::Errors->value] ?? array(),
            ResponseKeyType::Error->value         => $result[ResponseKeyType::Error->value] ?? null,
        ), $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
    }
}
