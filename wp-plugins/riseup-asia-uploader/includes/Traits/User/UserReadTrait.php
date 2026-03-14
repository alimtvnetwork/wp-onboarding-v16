<?php
/**
 * UserReadTrait — GET /users and GET /users/{id} handlers.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UserReadTrait {

    /**
     * Handle GET /users — list users with pagination.
     */
    public function handleListUsers(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'GET /users'));

        return $this->safeExecute(function () use ($request) {
            $page    = (int) ($request->get_param('page') ?: 1);
            $perPage = (int) ($request->get_param('per_page') ?: 20);
            $role    = $request->get_param('role') ?: '';
            $search  = $request->get_param('search') ?: '';

            $isPerPageTooHigh = ($perPage > 100);

            if ($isPerPageTooHigh) {
                $perPage = 100;
            }

            $queryArgs = array(
                'number'  => $perPage,
                'paged'   => $page,
                'orderby' => 'ID',
                'order'   => 'ASC',
            );

            $hasRole = !empty($role);

            if ($hasRole) {
                $queryArgs['role'] = sanitize_text_field($role);
            }

            $hasSearch = !empty($search);

            if ($hasSearch) {
                $queryArgs['search'] = '*' . sanitize_text_field($search) . '*';
                $queryArgs['search_columns'] = array('user_login', 'user_email', 'display_name');
            }

            $userQuery = new WP_User_Query($queryArgs);
            $users = $userQuery->get_results();
            $total = $userQuery->get_total();

            $results = array_map(
                fn($user) => $this->mapUserToSummary($user),
                $users,
            );

            $this->fileLogger->info('Users listed', array(
                'total' => $total,
                'page'  => $page,
                'count' => count($results),
            ));

            return EnvelopeBuilder::success('Users retrieved')
                ->setResults($results)
                ->setPagination($total, $perPage, $page)
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleListUsers');
    }

    /**
     * Handle GET /users/{id} — get single user with all fields.
     */
    public function handleGetUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $this->fileLogger->info('User endpoint accessed', array('endpoint' => 'GET /users/{id}', 'userId' => $userId));

        return $this->safeExecute(function () use ($userId) {
            $user = get_userdata($userId);
            $isUserFound = ($user !== false);

            if (!$isUserFound) {
                $this->fileLogger->warn('User not found', array('userId' => $userId));

                return EnvelopeBuilder::error('User not found', 404)
                    ->autoDetectRequestedAt()
                    ->setDelegatedAt(home_url())
                    ->toResponse();
            }

            $result = $this->mapUserToResponse($user);

            return EnvelopeBuilder::success('User retrieved')
                ->setSingleResult($result)
                ->autoDetectRequestedAt()
                ->setDelegatedAt(home_url())
                ->toResponse();
        }, 'handleGetUser');
    }
}
