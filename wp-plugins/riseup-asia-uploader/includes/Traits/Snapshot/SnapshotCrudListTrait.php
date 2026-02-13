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
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshots($request) {
        return $this->safe_execute(function() use ($request) {
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
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
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $provider = $manager->getProvider();
            if (!$provider) {
                return $this->error_response('No snapshot provider available', 500);
            }

            $snapshot = $provider->getSnapshot($id);
            if (!$snapshot) {
                return $this->error_response('Snapshot not found', 404);
            }

            return new WP_REST_Response(array(
                'success'  => true,
                'snapshot' => $snapshot,
            ), 200);
        }, 'get_snapshot');
    }

    /**
     * Alias for handle_get_snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_info($request) {
        return $this->handle_get_snapshot($request);
    }
}
