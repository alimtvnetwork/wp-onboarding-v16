<?php
/**
 * PostHandlerTrait — Post, category, and log query handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PostHandlerTrait
{
    /**
     * Handle list posts.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_list_posts($request) {
        $this->file_logger->debug('List posts endpoint called');

        $result = $this->post_manager->listPosts(array(
            'status' => $request->get_param('status'),
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    /**
     * Handle create post.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_create_post($request) {
        $this->file_logger->info('Create post endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->createPost($data);

        return new WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    /**
     * Handle list categories.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_list_categories($request) {
        $this->file_logger->debug('List categories endpoint called');

        $result = $this->post_manager->listCategories(array(
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    /**
     * Handle create category.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_create_category($request) {
        $this->file_logger->info('Create category endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->createCategory($data);

        return new WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    /**
     * Handle query logs.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_query_logs($request) {
        $this->file_logger->debug('Query logs endpoint called');

        try {
            $this->db->init();
            $filters = $this->buildLogQueryFilters($request);
            $limit  = $request->get_param('limit') ?? DEFAULT_LIMIT;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->query_transactions($filters, $limit, $offset);
            $total = $result['total'];
            $per_page = (int) $limit;

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS)
                ->setResults($result['logs'])
                ->setPagination($total, $per_page, $per_page > 0 ? (int) floor($offset / $per_page) + 1 : 1)
                ->toResponse();
        } catch (Throwable $e) {
            return $this->error_response('Failed to query logs: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Build log query filters from the request.
     *
     * @param WP_REST_Request $request Request object.
     * @return array Filter parameters.
     */
    private function buildLogQueryFilters($request): array {
        return array(
            'plugin' => $request->get_param('plugin'),
            'action' => $request->get_param('action'),
            'user'   => $request->get_param('user'),
            'status' => $request->get_param('status'),
            'from'   => $request->get_param('from'),
            'to'     => $request->get_param('to'),
        );
    }

    /**
     * Handle logs stats.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_logs_stats($request) {
        $this->file_logger->debug('Logs stats endpoint called');

        try {
            $this->db->init();
            $stats = $this->db->get_stats();

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS_STATS)
                ->setSingleResult($stats)
                ->toResponse();
        } catch (Throwable $e) {
            return $this->error_response('Failed to get stats: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }
}
