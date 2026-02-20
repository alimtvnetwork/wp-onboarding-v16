<?php
/**
 * SnapshotCrudListTrait — snapshot list, get, and info handlers.
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
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Snapshot\SnapshotManager;

trait SnapshotCrudListTrait {

    /**
     * Handle listing snapshots.
     */
    public function handleListSnapshots(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $snapshots = $manager->listSnapshots($limit, $offset);

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value            => true,
                ResponseKeyType::Snapshots->value          => $snapshots[ResponseKeyType::Snapshots->value],
                ResponseKeyType::Total->value              => $snapshots[ResponseKeyType::Total->value],
            ), HttpStatusType::Ok->value);
        }, 'list_snapshots');
    }

    /**
     * Handle getting a single snapshot.
     */
    public function handleGetSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $provider = $manager->getProvider();
            $isProviderMissing = ($provider === null);
            if ($isProviderMissing) {
                return $this->errorResponse(ResponseMessageType::SnapshotProviderMissing->value, HttpStatusType::ServerError->value);
            }

            $snapshot = $provider->getSnapshot($id);
            $isSnapshotMissing = ($snapshot === null);
            if ($isSnapshotMissing) {
                return $this->errorResponse(ResponseMessageType::SnapshotNotFound->value, HttpStatusType::NotFound->value);
            }

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                'snapshot' => $snapshot,
            ), HttpStatusType::Ok->value);
        }, 'get_snapshot');
    }

    /**
     * Alias for handleGetSnapshot.
     */
    public function handleSnapshotInfo(WP_REST_Request $request): WP_REST_Response {
        return $this->handleGetSnapshot($request);
    }
}
