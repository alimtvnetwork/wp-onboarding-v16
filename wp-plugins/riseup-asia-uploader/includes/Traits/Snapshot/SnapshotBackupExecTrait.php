<?php
/**
 * SnapshotBackupExecTrait — Full and incremental backup REST handlers.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;

trait SnapshotBackupExecTrait {

    /** Handle full end-to-end backup. */
    public function handleFullBackup($request) {
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
    public function handleIncrementalBackup($request) {
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
        $this->logger->logPluginAction($action, 'snapshot', StatusType::Success->value,
            array('title' => $body['title'] ?? null, 'scope' => $body['scope'] ?? null, 'phase' => 'initiated'));
    }

    /** Create the orchestrator for full backup. */
    private function createFullBackupOrchestrator() {
        $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
        return RiseupSnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
    }

    /** Extract full backup options from request body. */
    private function extractFullBackupOptions(array $body): array {
        return array(
            'title' => $body['title'] ?? null, 'scope' => $body['scope'] ?? null,
            'include_plugins' => $body['include_plugins'] ?? null,
            'plugin_selection' => $body['plugin_selection'] ?? null,
            'compression' => $body['compression'] ?? null,
        );
    }

    /** Log full backup completion. */
    private function logBackupComplete(string $action, array $result) {
        $this->logger->logPluginAction($action, 'snapshot',
            $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
            array('snapshot_id' => $result['snapshot_id'] ?? null, 'tables' => $result['tables'] ?? 0,
                'total_rows' => $result['total_rows'] ?? 0, 'duration' => $result['duration'] ?? 0, 'phase' => 'complete'),
            $result['success'] ? null : ($result['error'] ?? 'Backup failed'));
    }

    /** Build full backup response. */
    private function buildFullBackupResponse(array $result): WP_REST_Response {
        return new WP_REST_Response(array(
            'success' => $result['success'], 'snapshot_id' => $result['snapshot_id'] ?? null,
            'directory' => $result['directory'] ?? null, 'tables' => $result['tables'] ?? 0,
            'total_rows' => $result['total_rows'] ?? 0, 'plugins' => $result['plugins'] ?? 0,
            'zip_size' => $result['zip_size'] ?? 0, 'duration' => $result['duration'] ?? 0,
            'errors' => $result['errors'] ?? array(), 'error' => $result['error'] ?? null, 'phase' => $result['phase'] ?? null,
        ), $result['success'] ? 201 : 500);
    }

    /** Resolve the master directory for an incremental backup. */
    private function resolveIncrementalMasterDir(array $body) {
        $incremental = $this->createIncrementalBackup();

        $master_dir = $body['master_dir'] ?? null;
        if (!$master_dir) {
            $master_dir = $incremental->findLatestMasterSnapshot();
        }

        if (!$master_dir || RiseupBooleanHelpers::isDirMissing($master_dir)) {
            return new WP_REST_Response(array(
                'success' => false, 'error' => 'No master (full) snapshot found. Create a full backup first.',
            ), 400);
        }

        return $master_dir;
    }

    /** Create an IncrementalBackup instance. */
    private function createIncrementalBackup() {
        $rootDb = RiseupRootDb::getInstance($this->fileLogger, RiseupDependencyAnalyzer::getInstance($this->fileLogger));
        return RiseupIncrementalBackup::getInstance($this->fileLogger, $this->db, $rootDb);
    }

    /** Log incremental backup completion. */
    private function logIncrementalComplete(array $result) {
        $this->logger->logPluginAction(ActionType::SnapshotIncremental->value, 'snapshot',
            $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
            array('snapshot_id' => $result['snapshot_id'] ?? null, 'tables_changed' => $result['tables_changed'] ?? 0,
                'total_new_rows' => $result['total_new_rows'] ?? 0, 'duration' => $result['duration'] ?? 0, 'phase' => 'complete'),
            $result['success'] ? null : ($result['error'] ?? 'Incremental backup failed'));
    }

    /** Build incremental backup response. */
    private function buildIncrementalResponse(array $result): WP_REST_Response {
        return new WP_REST_Response(array(
            'success' => $result['success'], 'snapshot_id' => $result['snapshot_id'] ?? null,
            'sequence' => $result['sequence'] ?? null, 'folder_name' => $result['folder_name'] ?? null,
            'tables_changed' => $result['tables_changed'] ?? 0, 'total_new_rows' => $result['total_new_rows'] ?? 0,
            'tables' => $result['tables'] ?? array(), 'duration' => $result['duration'] ?? 0,
            'errors' => $result['errors'] ?? array(), 'error' => $result['error'] ?? null,
        ), $result['success'] ? 201 : 500);
    }
}
