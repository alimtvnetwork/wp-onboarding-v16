<?php
/**
 * RouteRegistrationTrait — REST API route registration orchestrator.
 *
 * Contains registerRoutes and the utility/post/log/catch-all sub-registrars.
 * Plugin-specific routes (plugins, agents, snapshots) live in PluginRoutesTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait RouteRegistrationTrait
{
    /**
     * Register REST API routes.
     */
    public function registerRoutes() {
        $this->fileLogger->info('Registering REST API routes', array('namespace' => API_FULL_NAMESPACE));

        $registered = 0;
        $failed = 0;

        $safeRegister = function ($endpointConst, $args) use (&$registered, &$failed) {
            try {
                register_rest_route(API_FULL_NAMESPACE, '/' . $endpointConst, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->error('Failed to register route: ' . $endpointConst . ' - ' . $e->getMessage());
            }
        };

        $this->registerUtilityRoutes($safeRegister);
        $this->registerPluginRoutes($safeRegister);
        $this->registerPostRoutes($safeRegister);
        $this->registerLogRoutes($safeRegister);
        $this->registerAgentRoutes($safeRegister, $failed);
        $this->registerSnapshotRoutes($safeRegister);
        $this->registerCatchAllRoute($safeRegister);

        $this->fileLogger->info("REST API route registration complete: $registered registered, $failed failed");
    }

    /**
     * Register utility routes (status, openapi, opcache-reset).
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerUtilityRoutes($safeRegister) {
        $safeRegister(EndpointType::Status->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleStatus'),
            'permission_callback' => $this->buildPermissionCallback('status', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::Openapi->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleOpenapi'),
            'permission_callback' => $this->buildPermissionCallback('openapi', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::OpcacheReset->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleOpcacheReset'),
            'permission_callback' => $this->buildPermissionCallback('opcache_reset', array($this, 'checkPluginPermission')),
        ));
    }

    /**
     * Register post and category routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerPostRoutes($safeRegister) {
        $safeRegister(EndpointType::Posts->value, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleListPosts'),
                'permission_callback' => $this->buildPermissionCallback('posts', array($this, 'checkPostPermission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreatePost'),
                'permission_callback' => $this->buildPermissionCallback('posts', array($this, 'checkPostPermission')),
            ),
        ));

        $safeRegister(EndpointType::Categories->value, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleListCategories'),
                'permission_callback' => $this->buildPermissionCallback('categories', array($this, 'checkPostPermission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreateCategory'),
                'permission_callback' => $this->buildPermissionCallback('categories', array($this, 'checkPostPermission')),
            ),
        ));
    }

    /**
     * Register log query and stats routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerLogRoutes($safeRegister) {
        $safeRegister(EndpointType::Logs->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleQueryLogs'),
            'permission_callback' => $this->buildPermissionCallback('logs', array($this, 'checkLogsPermission')),
        ));

        $safeRegister(EndpointType::LogsStats->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleLogsStats'),
            'permission_callback' => $this->buildPermissionCallback('logs', array($this, 'checkLogsPermission')),
        ));
    }

    /**
     * Register catch-all route for invalid paths.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerCatchAllRoute($safeRegister) {
        $safeRegister('(?P<invalid_path>.+)', array(
            'methods'             => array(
                HttpMethodType::Get->value,
                HttpMethodType::Post->value,
                HttpMethodType::Put->value,
                HttpMethodType::Patch->value,
                HttpMethodType::Delete->value,
            ),
            'callback'            => array($this, 'handleInvalidRoute'),
            'permission_callback' => '__return_true',
        ));
    }
}
