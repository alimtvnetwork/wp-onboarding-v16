<?php
/**
 * SnapshotExportHandlerTrait — snapshot export and download handlers.
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
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotExporter;

trait SnapshotExportHandlerTrait {

    public function handleExportSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->fileLogger->info('Exporting snapshot', array('id' => $id));

            $this->logger->logPluginAction(
                ActionType::SnapshotExport->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $manager->exportSnapshot($id);
            $isExportFailed = BooleanHelpers::isResultFailed($result);

            if ($isExportFailed) {
                $this->logger->logPluginAction(
                    ActionType::SnapshotExport->value, 'snapshot', StatusType::Failed->value,
                    array('snapshot_id' => $id),
                    $result['error'] ?? 'Export failed'
                );

                return $this->errorResponse($result['error'], HttpStatusType::BadRequest->value);
            }

            $filepath = $result['filepath'];
            if (PathHelper::isFileMissing($filepath)) {
                $this->logger->logPluginAction(
                    ActionType::SnapshotExport->value, 'snapshot', StatusType::Failed->value,
                    array('snapshot_id' => $id),
                    'Export file not found'
                );

                return $this->errorResponse('Export file not found', HttpStatusType::ServerError->value);
            }

            $this->logger->logPluginAction(
                ActionType::SnapshotExport->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'filename' => $result['filename'], 'size' => $result['size'])
            );

            return new WP_REST_Response(array(
                'success'  => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'downloadUrl' => rest_url(PluginConfigType::apiFullNamespace() . '/' . EndpointType::SnapshotList->value . '/' . $id . '/download'),
            ), HttpStatusType::Ok->value);
        }, 'export_snapshot');
    }

    public function handleSnapshotDownload(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $snapshotId = isset($body['snapshot_id']) ? (int) $body['snapshot_id'] : 0;

            if ($snapshotId <= 0) {
                return $this->errorResponse('Missing or invalid snapshot_id', HttpStatusType::BadRequest->value);
            }

            return $this->buildDownloadResponse($snapshotId);
        }, 'snapshot_download');
    }

    private function buildDownloadResponse(int $snapshotId) {
        $this->fileLogger->info('Snapshot download requested', array('snapshot_id' => $snapshotId));

        $this->logger->logPluginAction(
            ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Success->value,
            array('snapshot_id' => $snapshotId, 'phase' => 'initiated')
        );

        $exporter = SnapshotExporter::getInstance($this->fileLogger, $this->db);
        $result = $exporter->getOrBuildZip($snapshotId);
        $isDownloadFailed = BooleanHelpers::isResultFailed($result);

        if ($isDownloadFailed) {
            $this->logger->logPluginAction(
                ActionType::SnapshotZipDownload->value, 'snapshot', StatusType::Failed->value,
                array('snapshot_id' => $snapshotId),
                $result['error'] ?? 'Download failed'
            );

            return $this->errorResponse($result['error'] ?? 'Export failed', HttpStatusType::BadRequest->value);
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

        return EnvelopeBuilder::success()
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
