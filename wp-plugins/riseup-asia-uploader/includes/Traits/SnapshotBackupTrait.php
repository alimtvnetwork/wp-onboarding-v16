<?php
/**
 * SnapshotBackupTrait — snapshot settings, providers, backup, cleanup, and progress handlers.
 *
 * Extracted from riseup-asia-uploader.php (lines 5108–5499).
 *
 * @package RiseupAsiaUploader
 */

trait SnapshotBackupTrait {

    /**
     * Handle getting snapshot settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_snapshot_settings($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $settings = $manager->getSettings();

            return new WP_REST_Response(array(
                'success'  => true,
                'settings' => $settings,
            ), 200);
        }, 'get_snapshot_settings');
    }

    /**
     * Handle updating snapshot settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_update_snapshot_settings($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $this->file_logger->info('Updating snapshot settings', array('keys' => array_keys($body)));

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $updated = $manager->updateSettings($body);

            $this->logger->log_plugin_action(
                'snapshot_settings_update', 'snapshot', STATUS_SUCCESS,
                array('keys' => array_keys($body))
            );

            return new WP_REST_Response(array(
                'success'  => true,
                'settings' => $updated,
            ), 200);
        }, 'update_snapshot_settings');
    }

    /**
     * Handle listing snapshot providers.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshot_providers($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $providers = $manager->getProviders();

            return new WP_REST_Response(array(
                'success'   => true,
                'providers' => $providers,
            ), 200);
        }, 'list_snapshot_providers');
    }

    /**
     * Handle listing available database tables.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshot_tables($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $tables = $manager->getAvailableTables();

            return new WP_REST_Response(array(
                'success' => true,
                'tables'  => $tables,
            ), 200);
        }, 'list_snapshot_tables');
    }

    /**
     * Handle dependency analysis request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_analyze_dependencies($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body['scope']) ? $body['scope'] : 'all';

            $analyzer = RiseupDependencyAnalyzer::getInstance($this->file_logger);
            $analysis = $analyzer->analyze($scope);

            return new WP_REST_Response(array(
                'success'      => true,
                'tables'       => $analysis['tables'],
                'dependencies' => $analysis['dependencies'],
                'seed_order'   => $analysis['seed_order'],
                'table_count'  => $analysis['table_count'],
                'dep_count'    => $analysis['dep_count'],
            ), 200);
        }, 'analyze_dependencies');
    }

    /**
     * Handle per-table snapshot export.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_export_pertable($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $analyzer = RiseupDependencyAnalyzer::getInstance($this->file_logger);
            $rootDb = RiseupRootDb::getInstance($this->file_logger, $analyzer);
            $worker = RiseupSnapshotWorker::getInstance($this->file_logger, $this->db, $rootDb, $analyzer);

            $result = $worker->execute(array(
                'title'    => $body['title'] ?? null,
                'scope'    => $body['scope'] ?? 'wordpress',
                'type'     => $body['type'] ?? 'full',
                'settings' => $body['settings'] ?? null,
            ));

            $status = $result['success'] ? 200 : 500;

            return new WP_REST_Response(array(
                'success'    => $result['success'],
                'directory'  => $result['directory'] ?? null,
                'tables'     => $result['tables'] ?? 0,
                'total_rows' => $result['total_rows'] ?? 0,
                'errors'     => $result['errors'] ?? array(),
                'duration'   => $result['duration'] ?? 0,
                'error'      => $result['error'] ?? null,
            ), $status);
        }, 'export_pertable');
    }

    /**
     * Handle full end-to-end backup.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_full_backup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_FULL_BACKUP, 'snapshot', STATUS_SUCCESS,
                array('title' => $body['title'] ?? null, 'scope' => $body['scope'] ?? null, 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);

            $result = $orchestrator->executeFullBackup(array(
                'title'            => $body['title'] ?? null,
                'scope'            => $body['scope'] ?? null,
                'include_plugins'  => $body['include_plugins'] ?? null,
                'plugin_selection' => $body['plugin_selection'] ?? null,
                'compression'      => $body['compression'] ?? null,
            ));

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_FULL_BACKUP, 'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array(
                    'snapshot_id' => $result['snapshot_id'] ?? null,
                    'tables'      => $result['tables'] ?? 0,
                    'total_rows'  => $result['total_rows'] ?? 0,
                    'duration'    => $result['duration'] ?? 0,
                    'phase'       => 'complete',
                ),
                $result['success'] ? null : ($result['error'] ?? 'Full backup failed')
            );

            $status = $result['success'] ? 201 : 500;

            return new WP_REST_Response(array(
                'success'     => $result['success'],
                'snapshot_id' => $result['snapshot_id'] ?? null,
                'directory'   => $result['directory'] ?? null,
                'tables'      => $result['tables'] ?? 0,
                'total_rows'  => $result['total_rows'] ?? 0,
                'plugins'     => $result['plugins'] ?? 0,
                'zip_size'    => $result['zip_size'] ?? 0,
                'duration'    => $result['duration'] ?? 0,
                'errors'      => $result['errors'] ?? array(),
                'error'       => $result['error'] ?? null,
                'phase'       => $result['phase'] ?? null,
            ), $status);
        }, 'full_backup');
    }

    /**
     * Handle incremental backup.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_incremental_backup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_INCREMENTAL, 'snapshot', STATUS_SUCCESS,
                array('title' => $body['title'] ?? null, 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $rootDb = RiseupRootDb::getInstance($this->file_logger, RiseupDependencyAnalyzer::getInstance($this->file_logger));
            $incremental = RiseupIncrementalBackup::getInstance($this->file_logger, $this->db, $rootDb);

            $master_dir = $body['master_dir'] ?? null;
            if (!$master_dir) {
                $master_dir = $incremental->findLatestMasterSnapshot();
            }

            if (!$master_dir || RiseupBooleanHelpers::is_dir_missing($master_dir)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error'   => 'No master (full) snapshot found. Create a full backup first.',
                ), 400);
            }

            $result = $incremental->execute($master_dir, array(
                'title' => $body['title'] ?? null,
            ));

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_INCREMENTAL, 'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array(
                    'snapshot_id'    => $result['snapshot_id'] ?? null,
                    'tables_changed' => $result['tables_changed'] ?? 0,
                    'total_new_rows' => $result['total_new_rows'] ?? 0,
                    'duration'       => $result['duration'] ?? 0,
                    'phase'          => 'complete',
                ),
                $result['success'] ? null : ($result['error'] ?? 'Incremental backup failed')
            );

            $status = $result['success'] ? 201 : 500;

            return new WP_REST_Response(array(
                'success'        => $result['success'],
                'snapshot_id'    => $result['snapshot_id'] ?? null,
                'sequence'       => $result['sequence'] ?? null,
                'folder_name'    => $result['folder_name'] ?? null,
                'tables_changed' => $result['tables_changed'] ?? 0,
                'total_new_rows' => $result['total_new_rows'] ?? 0,
                'tables'         => $result['tables'] ?? array(),
                'duration'       => $result['duration'] ?? 0,
                'errors'         => $result['errors'] ?? array(),
                'error'          => $result['error'] ?? null,
            ), $status);
        }, 'incremental_backup');
    }

    /**
     * Handle snapshot cleanup.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_cleanup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $cleaner = RiseupSnapshotFactory::cleaner($this->file_logger, $this->db);

            $result = $cleaner->execute(array(
                'retention_type'  => $body['retention_type'] ?? null,
                'retention_days'  => $body['retention_days'] ?? null,
                'retention_count' => $body['retention_count'] ?? null,
                'dry_run'         => $body['dry_run'] ?? false,
            ));

            if (!($body['dry_run'] ?? false)) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_CLEANUP, 'snapshot',
                    $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                    array(
                        'retention_removed' => $result['retention']['deleted'] ?? 0,
                        'orphans_removed'   => $result['orphans']['removed'] ?? 0,
                        'stuck_marked'      => $result['stuck']['cleaned'] ?? 0,
                        'duration'          => $result['duration'] ?? 0,
                    ),
                    $result['success'] ? null : 'Cleanup encountered errors'
                );
            }

            return new WP_REST_Response(array(
                'success'   => $result['success'],
                'retention' => $result['retention'],
                'orphans'   => $result['orphans'],
                'stuck'     => $result['stuck'],
                'duration'  => $result['duration'],
                'dry_run'   => $result['dry_run'],
                'errors'    => $result['errors'],
            ), 200);
        }, 'snapshot_cleanup');
    }

    /**
     * Handle snapshot job progress polling.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response with progress data.
     */
    public function handle_snapshot_progress($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $job_id = $body['job_id'] ?? null;

            if (empty($job_id)) {
                return new WP_REST_Response(array(
                    'IsSuccess'    => false,
                    'HasAnyErrors' => true,
                    'error'        => 'Missing required field: job_id',
                ), HTTP_BAD_REQUEST);
            }

            require_once dirname(__FILE__) . '/../Snapshot/SnapshotFactory.php';
            $rootDb = RiseupRootDb::getInstance($this->file_logger, RiseupDependencyAnalyzer::getInstance($this->file_logger));
            $worker = RiseupSnapshotWorker::getInstance(
                $this->file_logger,
                $this->db,
                $rootDb,
                RiseupDependencyAnalyzer::getInstance($this->file_logger)
            );

            $progress = $worker->getJobProgress((int) $job_id);

            if (!$progress) {
                return new WP_REST_Response(array(
                    'IsSuccess'    => false,
                    'HasAnyErrors' => true,
                    'error'        => 'Job not found',
                    'code'         => 'JOB_NOT_FOUND',
                ), HTTP_NOT_FOUND);
            }

            return new WP_REST_Response(array(
                'IsSuccess'       => true,
                'HasAnyErrors'    => false,
                'job_id'          => $progress['job_id'],
                'status'          => $progress['status'],
                'total_tables'    => $progress['total_tables'],
                'tables_exported' => $progress['tables_exported'],
                'total_rows'      => $progress['total_rows'],
                'pool_size'       => $progress['pool_size'],
                'total_batches'   => $progress['total_batches'],
                'current_batch'   => $progress['current_batch'],
                'percent'         => $progress['percent'],
                'errors'          => $progress['errors'],
                'table_progress'  => $progress['table_progress'],
                'created_at'      => $progress['created_at'],
                'updated_at'      => $progress['updated_at'],
                'completed_at'    => $progress['completed_at'],
            ), HTTP_OK);
        }, 'snapshot_progress');
    }
}
