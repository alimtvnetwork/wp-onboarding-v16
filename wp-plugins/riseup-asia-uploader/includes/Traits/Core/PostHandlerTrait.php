<?php
/**
 * PostHandlerTrait — Post, category, and log query handlers.
 *
 * @package RiseupAsia\Traits\Core
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\ErrorHandling\ErrorResponse;

trait PostHandlerTrait
{
    public function handleListPosts(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->debug('List posts endpoint called');

        $result = $this->postManager->listPosts(array(
            'status' => $request->get_param('status'),
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::ServerError->value);
    }

    public function handleCreatePost(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Create post endpoint called');

        $data   = $request->get_json_params();
        $result = $this->postManager->createPost($data);

        return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Created->value : HttpStatusType::BadRequest->value);
    }

    public function handleListCategories(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->debug('List categories endpoint called');

        $result = $this->postManager->listCategories(array(
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::ServerError->value);
    }

    public function handleCreateCategory(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Create category endpoint called');

        $data   = $request->get_json_params();
        $result = $this->postManager->createCategory($data);

        return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Created->value : HttpStatusType::BadRequest->value);
    }

    public function handleQueryLogs(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->debug('Query logs endpoint called');

        try {
            $this->db->init();
            $filters = $this->buildLogQueryFilters($request);
            $limit  = $request->get_param('limit') ?? PaginationConfigType::DefaultLimit->value;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->queryTransactions($filters, $limit, $offset);
            $total = $result['total'];
            $perPage = (int) $limit;

            return \RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::Logs->value)
                ->setResults($result['logs'])
                ->setPagination($total, $perPage, $perPage > 0 ? (int) floor($offset / $perPage) + 1 : 1)
                ->toResponse();
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Failed to query logs');
        }
    }

    private function buildLogQueryFilters(WP_REST_Request $request): array {
        return array(
            'plugin' => $request->get_param('plugin'),
            'action' => $request->get_param('action'),
            'user'   => $request->get_param('user'),
            'status' => $request->get_param('status'),
            'from'   => $request->get_param('from'),
            'to'     => $request->get_param('to'),
        );
    }

    public function handleLogsStats(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->debug('Logs stats endpoint called');

        try {
            $this->db->init();
            $stats = $this->db->getStats();

            return \RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::LogsStats->value)
                ->setSingleResult($stats)
                ->toResponse();
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Failed to get stats');
        }
    }
}