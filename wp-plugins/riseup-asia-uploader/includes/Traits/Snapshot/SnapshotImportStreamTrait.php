<?php
/**
 * SnapshotImportStreamTrait — snapshot import and file streaming handlers.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\SnapshotExporter;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotImport;

trait SnapshotImportStreamTrait {

    /**
     * Handle ZIP file download.
     */
    public function handleSnapshotDownloadFile(WP_REST_Request $request): ?WP_REST_Response {
        $validated = $this->validateAndResolveExport($request);
        if ($validated instanceof WP_REST_Response) {
            return $validated;
        }

        $this->streamZipFile($validated['export_id'], $validated['export']);
    }

    /**
     * Validate download token and resolve the export record.
     */
    private function validateAndResolveExport(WP_REST_Request $request): array|WP_REST_Response {
        $exportId = (int) $request->get_param('id');
        $token    = sanitize_text_field($request->get_param('token'));

        if ($exportId <= 0 || empty($token)) {
            return new WP_REST_Response(array(
                'success' => false, 'error' => 'Missing id or token parameter', 'code' => SnapshotErrorType::ExportTokenInvalid->value,
            ), 400);
        }

        $export = SnapshotExporter::getInstance($this->fileLogger, $this->db)->validateDownloadToken($exportId, $token);

        if (!$export) {
            return new WP_REST_Response(array(
                'success' => false, 'error' => 'Invalid or expired download token', 'code' => SnapshotErrorType::ExportTokenInvalid->value,
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

        $this->logger->logPluginAction(ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Success->value,
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
     */
    public function handleImportSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $files = $request->get_file_params();

            if (empty($files['file']['tmp_name'])) {
                return $this->errorResponse('No file uploaded', 400);
            }

            $tmp_file = $files['file']['tmp_name'];
            $original_name = $files['file']['name'] ?? 'unknown';
            $this->fileLogger->info('Importing snapshot from uploaded ZIP', array(
                'originalName' => $original_name,
                'size'         => $files['file']['size'],
            ));

            $this->logger->logPluginAction(
                ActionType::SnapshotImport->value, 'snapshot', StatusType::Success->value,
                array('filename' => $original_name, 'size' => $files['file']['size'], 'phase' => 'initiated')
            );

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $importer = new SnapshotImport($this->fileLogger, $this->db, $manager);
            $result = $importer->import($tmp_file);

            $this->logger->logPluginAction(
                ActionType::SnapshotImport->value, 'snapshot',
                $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
                array('filename' => $original_name, 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Import failed')
            );

            $status_code = $result['success'] ? 201 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'import_snapshot');
    }
}