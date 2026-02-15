<?php
/**
 * SnapshotCrudListTrait — snapshot list, get, and info handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotCrudListTrait {

    /**
     * Handle listing snapshots.
     */
    public function handleListSnapshots(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
            $snapshots = $manager->listSnapshots($limit, $offset);

            return new WP_REST_Response(array(
                'success'   => true,
                'snapshots' => $snapshots['snapshots'],
                'total'     => $snapshots['total'],
            ), 200);
        }, 'list_snapshots');
    }

    /**
     * Handle getting a single snapshot.
     */
    public function handleGetSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
            $provider = $manager->getProvider();
            if (!$provider) {
                return $this->errorResponse('No snapshot provider available', 500);
            }

            $snapshot = $provider->getSnapshot($id);
            if (!$snapshot) {
                return $this->errorResponse('Snapshot not found', 404);
            }

            return new WP_REST_Response(array(
                'success'  => true,
                'snapshot' => $snapshot,
            ), 200);
        }, 'get_snapshot');
    }

    /**
     * Alias for handleGetSnapshot.
     */
    public function handleSnapshotInfo(WP_REST_Request $request): WP_REST_Response {
        return $this->handleGetSnapshot($request);
    }
}
