<?php
/**
 * RouteRegistrationTrait — REST API route registration orchestrator.
 *
 * Contains register_routes and the utility/post/log/catch-all sub-registrars.
 * Plugin-specific routes (plugins, agents, snapshots) live in PluginRoutesTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;

trait RouteRegistrationTrait
{
    /**
     * Register REST API routes.
     */
    public function register_routes() {
        $this->file_logger->info('Registering REST API routes', array('namespace' => API_FULL_NAMESPACE));

        $registered = 0;
        $failed = 0;

        $safe_register = function ($endpoint_const, $args) use (&$registered, &$failed) {
            try {
                register_rest_route(API_FULL_NAMESPACE, '/' . $endpoint_const, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->file_logger->error('Failed to register route: ' . $endpoint_const . ' - ' . $e->getMessage());
            }
        };

        $this->register_utility_routes($safe_register);
        $this->register_plugin_routes($safe_register);
        $this->register_post_routes($safe_register);
        $this->register_log_routes($safe_register);
        $this->register_agent_routes($safe_register, $failed);
        $this->register_snapshot_routes($safe_register);
        $this->register_catch_all_route($safe_register);

        $this->file_logger->info("REST API route registration complete: $registered registered, $failed failed");
    }

    /**
     * Register utility routes (status, openapi, opcache-reset).
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_utility_routes($safe_register) {
        $safe_register(ENDPOINT_STATUS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_status'),
            'permission_callback' => $this->build_permission_callback('status', array($this, 'check_status_permission')),
        ));

        $safe_register(ENDPOINT_OPENAPI, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_openapi'),
            'permission_callback' => $this->build_permission_callback('openapi', array($this, 'check_status_permission')),
        ));

        $safe_register(ENDPOINT_OPCACHE_RESET, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_opcache_reset'),
            'permission_callback' => $this->build_permission_callback('opcache_reset', array($this, 'check_plugin_permission')),
        ));
    }

    /**
     * Register post and category routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_post_routes($safe_register) {
        $safe_register(ENDPOINT_POSTS, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handle_list_posts'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handle_create_post'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
        ));

        $safe_register(ENDPOINT_CATEGORIES, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handle_list_categories'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handle_create_category'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
        ));
    }

    /**
     * Register log query and stats routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_log_routes($safe_register) {
        $safe_register(ENDPOINT_LOGS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_query_logs'),
            'permission_callback' => $this->build_permission_callback('logs', array($this, 'check_logs_permission')),
        ));

        $safe_register(ENDPOINT_LOGS_STATS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_logs_stats'),
            'permission_callback' => $this->build_permission_callback('logs', array($this, 'check_logs_permission')),
        ));
    }

    /**
     * Register catch-all route for invalid paths.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_catch_all_route($safe_register) {
        $safe_register('(?P<invalid_path>.+)', array(
            'methods'             => array(
                HttpMethodType::Get->value,
                HttpMethodType::Post->value,
                HttpMethodType::Put->value,
                HttpMethodType::Patch->value,
                HttpMethodType::Delete->value,
            ),
            'callback'            => array($this, 'handle_invalid_route'),
            'permission_callback' => '__return_true',
        ));
    }
}
