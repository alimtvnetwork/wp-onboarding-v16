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

use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\FilterKeyType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait PostHandlerTrait
{
    public function handleListPosts(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->debug('List posts endpoint called');

            $result = $this->postManager->listPosts([
                'status' => $request->get_param('status'),
                'limit'  => $request->get_param('limit'),
                'offset' => $request->get_param('offset'),
                'search' => $request->get_param('search'),
            ]);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Ok->value : HttpStatusType::InternalServerError->value);
        }, 'handleListPosts');
    }

    public function handleCreatePost(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->info('Create post endpoint called');

            $data = $this->extractValidBody($request);
            $isBodyInvalid = ($data === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }

            $result = $this->postManager->createPost($data);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::BadRequest->value);
        }, 'handleCreatePost');
    }

    public function handleListCategories(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->debug('List categories endpoint called');

            $result = $this->postManager->listCategories([
                'limit'  => $request->get_param('limit'),
                'offset' => $request->get_param('offset'),
                'search' => $request->get_param('search'),
            ]);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Ok->value : HttpStatusType::InternalServerError->value);
        }, 'handleListCategories');
    }

    public function handleCreateCategory(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->info('Create category endpoint called');

            $data = $this->extractValidBody($request);
            $isBodyInvalid = ($data === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }

            $result = $this->postManager->createCategory($data);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::BadRequest->value);
        }, 'handleCreateCategory');
    }

    public function handleQueryLogs(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->debug('Query logs endpoint called');

            $this->db->init();
            $filters = $this->buildLogQueryFilters($request);
            $limit  = $request->get_param('limit') ?? PaginationConfigType::DefaultLimit->value;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->queryTransactions($filters, $limit, $offset);
            $total = $result[ResponseKeyType::Total->value];
            $perPage = (int) $limit;

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::Logs->value)
                ->setResults($result[ResponseKeyType::Logs->value])
                ->setPagination($total, $perPage, $perPage > 0 ? (int) floor($offset / $perPage) + 1 : 1)
                ->toResponse();
        }, 'handleQueryLogs');
    }

    private function buildLogQueryFilters(WP_REST_Request $request): array {

        return [
            FilterKeyType::Plugin->value => $request->get_param(FilterKeyType::Plugin->value),
            FilterKeyType::Action->value => $request->get_param(FilterKeyType::Action->value),
            FilterKeyType::User->value   => $request->get_param(FilterKeyType::User->value),
            FilterKeyType::Status->value => $request->get_param(FilterKeyType::Status->value),
            FilterKeyType::From->value   => $request->get_param(FilterKeyType::From->value),
            FilterKeyType::To->value     => $request->get_param(FilterKeyType::To->value),
        ];
    }

    public function handleLogsStats(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () use ($request) {
            $this->fileLogger->debug('Logs stats endpoint called');

            $this->db->init();
            $stats = $this->db->getStats();

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::LogsStats->value)
                ->setSingleResult($stats)
                ->toResponse();
        }, 'handleLogsStats');
    }
}
