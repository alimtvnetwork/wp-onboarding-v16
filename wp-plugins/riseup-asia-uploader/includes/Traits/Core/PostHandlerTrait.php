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
    public function handle_list_posts(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->debug('List posts endpoint called');

        $result = $this->postManager->listPosts(array(
            'status' => $request->get_param('status'),
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new \WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    public function handle_create_post(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->info('Create post endpoint called');

        $data   = $request->get_json_params();
        $result = $this->postManager->createPost($data);

        return new \WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    public function handle_list_categories(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->debug('List categories endpoint called');

        $result = $this->postManager->listCategories(array(
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new \WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    public function handle_create_category(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->info('Create category endpoint called');

        $data   = $request->get_json_params();
        $result = $this->postManager->createCategory($data);

        return new \WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    public function handle_query_logs(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->debug('Query logs endpoint called');

        try {
            $this->db->init();
            $filters = $this->buildLogQueryFilters($request);
            $limit  = $request->get_param('limit') ?? DEFAULT_LIMIT;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->query_transactions($filters, $limit, $offset);
            $total = $result['total'];
            $perPage = (int) $limit;

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS)
                ->setResults($result['logs'])
                ->setPagination($total, $perPage, $perPage > 0 ? (int) floor($offset / $perPage) + 1 : 1)
                ->toResponse();
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Failed to query logs');
        }
    }

    private function buildLogQueryFilters(\WP_REST_Request $request): array {
        return array(
            'plugin' => $request->get_param('plugin'),
            'action' => $request->get_param('action'),
            'user'   => $request->get_param('user'),
            'status' => $request->get_param('status'),
            'from'   => $request->get_param('from'),
            'to'     => $request->get_param('to'),
        );
    }

    public function handle_logs_stats(\WP_REST_Request $request): \WP_REST_Response {
        $this->fileLogger->debug('Logs stats endpoint called');

        try {
            $this->db->init();
            $stats = $this->db->get_stats();

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS_STATS)
                ->setSingleResult($stats)
                ->toResponse();
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Failed to get stats');
        }
    }
}
