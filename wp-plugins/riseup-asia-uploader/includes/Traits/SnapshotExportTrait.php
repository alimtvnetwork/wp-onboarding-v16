<?php
/**
 * SnapshotExportTrait — snapshot export, download, and import handlers.
 *
 * Extracted from riseup-asia-uploader.php (lines 4828–5107).
 *
 * @package RiseupAsiaUploader
 */

trait SnapshotExportTrait {

    /**
     * Handle exporting a snapshot as ZIP.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_export_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->file_logger->info('Exporting snapshot', array('id' => $id));

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_EXPORT, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->exportSnapshot($id);

            if (!$result['success']) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_EXPORT, 'snapshot', STATUS_FAILED,
                    array('snapshot_id' => $id),
                    $result['error'] ?? 'Export failed'
                );
                return $this->error_response($result['error'], 400);
            }

            $filepath = $result['filepath'];
            if (RiseupBooleanHelpers::is_file_missing($filepath)) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_EXPORT, 'snapshot', STATUS_FAILED,
                    array('snapshot_id' => $id),
                    'Export file not found'
                );
                return $this->error_response('Export file not found', 500);
            }

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_EXPORT, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'filename' => $result['filename'], 'size' => $result['size'])
            );

            return new WP_REST_Response(array(
                'success'  => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'downloadUrl' => rest_url(API_FULL_NAMESPACE . '/' . ENDPOINT_SNAPSHOTS . '/' . $id . '/download'),
            ), 200);
        }, 'export_snapshot');
    }

    /**
     * Handle ZIP download request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_download($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $snapshotId = isset($body['snapshot_id']) ? (int) $body['snapshot_id'] : 0;

            if ($snapshotId <= 0) {
                return $this->error_response('Missing or invalid snapshot_id', 400);
            }

            return $this->buildDownloadResponse($snapshotId);
        }, 'snapshot_download');
    }

    /**
     * Build the download response for a snapshot ZIP.
     *
     * @param int $snapshotId Snapshot ID.
     * @return WP_REST_Response
     */
    private function buildDownloadResponse(int $snapshotId) {
        $this->file_logger->info('Snapshot download requested', array('snapshot_id' => $snapshotId));

        $this->logger->log_plugin_action(
            ACTION_SNAPSHOT_ZIP_DOWNLOAD, 'snapshot', STATUS_SUCCESS,
            array('snapshot_id' => $snapshotId, 'phase' => 'initiated')
        );

        require_once dirname(__FILE__) . '/../Snapshot/SnapshotExporter.php';
        $exporter = RiseupSnapshotExporter::getInstance($this->file_logger, $this->db);
        $result = $exporter->getOrBuildZip($snapshotId);

        if (!$result['success']) {
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_ZIP_DOWNLOAD, 'snapshot', STATUS_FAILED,
                array('snapshot_id' => $snapshotId),
                $result['error'] ?? 'Download failed'
            );

            return $this->error_response($result['error'] ?? 'Export failed', 400);
        }

        $export = $result['export'];
        $downloadUrl = $exporter->getDownloadUrl((int) $export['id']);

        $this->logger->log_plugin_action(
            ACTION_SNAPSHOT_ZIP_DOWNLOAD, 'snapshot', STATUS_SUCCESS,
            array(
                'snapshot_id' => $snapshotId,
                'cached'      => $result['cached'] ?? false,
                'size'        => $export['zip_size'] ?? 0,
                'filename'    => $export['zip_filename'] ?? '',
            )
        );

        return RiseupEnvelopeBuilder::success()
            ->setResults(array(array(
                'url'               => $downloadUrl,
                'filename'          => $export['zip_filename'],
                'size'              => (int) $export['zip_size'],
                'cached'            => $result['cached'] ?? false,
                'included_ids'      => json_decode($export['included_ids'] ?? '[]', true),
                'incremental_count' => (int) ($export['incremental_count'] ?? 0),
                'created_at'        => $export['created_at'] ?? '',
                'status'            => $export['status'] ?? 'valid',
            )))
            ->setRequestedAt('/' . ENDPOINT_SNAPSHOT_DOWNLOAD)
            ->toResponse();
    }

    /**
     * Handle ZIP file download.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|void Response or direct file stream.
     */
    public function handle_snapshot_download_file($request) {
        $validated = $this->validateAndResolveExport($request);
        if ($validated instanceof WP_REST_Response) {
            return $validated;
        }

        $this->streamZipFile($validated['export_id'], $validated['export']);
    }

    /**
     * Validate download token and resolve the export record.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Export data or error response.
     */
    private function validateAndResolveExport($request) {
        $exportId = (int) $request->get_param('id');
        $token    = sanitize_text_field($request->get_param('token'));

        if ($exportId <= 0 || empty($token)) {
            return new WP_REST_Response(array(
                'success' => false, 'error' => 'Missing id or token parameter', 'code' => ERR_EXPORT_TOKEN_INVALID,
            ), 400);
        }

        require_once dirname(__FILE__) . '/../Snapshot/SnapshotExporter.php';
        $export = RiseupSnapshotExporter::getInstance($this->file_logger, $this->db)->validateDownloadToken($exportId, $token);

        if (!$export) {
            return new WP_REST_Response(array(
                'success' => false, 'error' => 'Invalid or expired download token', 'code' => ERR_EXPORT_TOKEN_INVALID,
            ), 403);
        }

        return array('export_id' => $exportId, 'export' => $export);
    }

    /**
     * Send ZIP file headers for streaming.
     *
     * @param string $filename Filename for Content-Disposition.
     * @param int    $filesize File size in bytes.
     */
    private function sendZipHeaders(string $filename, int $filesize) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    /**
     * Stream a ZIP file to the client with proper headers.
     *
     * @param int   $exportId Export record ID.
     * @param array $export   Export record with zip_path and zip_filename.
     */
    private function streamZipFile(int $exportId, array $export) {
        $filepath = $export['zip_path'];
        $filename = $export['zip_filename'];
        $filesize = filesize($filepath);

        $this->logger->log_plugin_action(ACTION_SNAPSHOT_ZIP_DOWNLOAD, 'snapshot', STATUS_SUCCESS,
            array('export_id' => $exportId, 'filename' => $filename, 'size' => $filesize, 'phase' => 'streaming'));

        $this->sendZipHeaders($filename, $filesize);

        while (ob_get_level()) {
            ob_end_clean();
        }

        $handle = fopen($filepath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        } else {
            readfile($filepath);
        }

        exit;
    }

    /**
     * Handle importing a snapshot from ZIP upload.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_import_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $files = $request->get_file_params();

            if (empty($files['file']['tmp_name'])) {
                return $this->error_response('No file uploaded', 400);
            }

            $tmp_file = $files['file']['tmp_name'];
            $original_name = $files['file']['name'] ?? 'unknown';
            $this->file_logger->info('Importing snapshot from uploaded ZIP', array(
                'originalName' => $original_name,
                'size'         => $files['file']['size'],
            ));

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_IMPORT, 'snapshot', STATUS_SUCCESS,
                array('filename' => $original_name, 'size' => $files['file']['size'], 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $importer = new RiseupSnapshotImport($this->file_logger, $this->db, $manager);
            $result = $importer->import($tmp_file);

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_IMPORT, 'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('filename' => $original_name, 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Import failed')
            );

            $status_code = $result['success'] ? 201 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'import_snapshot');
    }
}
