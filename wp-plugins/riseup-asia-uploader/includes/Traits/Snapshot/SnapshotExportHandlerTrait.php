<?php
/**
 * SnapshotExportHandlerTrait — snapshot export and download handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\StatusType;

trait SnapshotExportHandlerTrait {

    /**
     * Handle exporting a snapshot as ZIP.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handleExportSnapshot($request) {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->fileLogger->info('Exporting snapshot', array('id' => $id));

            $this->logger->logPluginAction(
                ActionType::SnapshotExport->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $manager->exportSnapshot($id);

            if (!$result['success']) {
                $this->logger->logPluginAction(
                    ActionType::SnapshotExport->value, 'snapshot', StatusType::Failed->value,
                    array('snapshot_id' => $id),
                    $result['error'] ?? 'Export failed'
                );
                return $this->errorResponse($result['error'], 400);
            }

            $filepath = $result['filepath'];
            if (RiseupBooleanHelpers::is_file_missing($filepath)) {
                $this->logger->logPluginAction(
                    ActionType::SnapshotExport->value, 'snapshot', StatusType::Failed->value,
                    array('snapshot_id' => $id),
                    'Export file not found'
                );
                return $this->errorResponse('Export file not found', 500);
            }

            $this->logger->logPluginAction(
                ActionType::SnapshotExport->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'filename' => $result['filename'], 'size' => $result['size'])
            );

            return new WP_REST_Response(array(
                'success'  => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'downloadUrl' => rest_url(API_FULL_NAMESPACE . '/' . EndpointType::SnapshotList->value . '/' . $id . '/download'),
            ), 200);
        }, 'export_snapshot');
    }

    /**
     * Handle ZIP download request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handleSnapshotDownload($request) {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $snapshotId = isset($body['snapshot_id']) ? (int) $body['snapshot_id'] : 0;

            if ($snapshotId <= 0) {
                return $this->errorResponse('Missing or invalid snapshot_id', 400);
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
        $this->fileLogger->info('Snapshot download requested', array('snapshot_id' => $snapshotId));

        $this->logger->logPluginAction(
            ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Success->value,
            array('snapshot_id' => $snapshotId, 'phase' => 'initiated')
        );

        require_once dirname(__FILE__) . '/../../Snapshot/SnapshotExporter.php';
        $exporter = RiseupSnapshotExporter::getInstance($this->fileLogger, $this->db);
        $result = $exporter->getOrBuildZip($snapshotId);

        if (!$result['success']) {
            $this->logger->logPluginAction(
                ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Failed->value,
                array('snapshot_id' => $snapshotId),
                $result['error'] ?? 'Download failed'
            );

            return $this->errorResponse($result['error'] ?? 'Export failed', 400);
        }

        $export = $result['export'];
        $downloadUrl = $exporter->getDownloadUrl((int) $export['id']);

        $this->logger->logPluginAction(
            ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Success->value,
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
            ->setRequestedAt('/' . EndpointType::SnapshotDownload->value)
            ->toResponse();
    }
}
