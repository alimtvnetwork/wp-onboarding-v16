<?php
/**
 * SnapshotImportStreamTrait — snapshot import and file streaming handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotImportStreamTrait {

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

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotExporter.php';
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
