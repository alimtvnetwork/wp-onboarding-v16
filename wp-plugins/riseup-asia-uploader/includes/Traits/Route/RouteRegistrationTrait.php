<?php
/**
 * RouteRegistrationTrait — REST API route registration orchestrator.
 *
 * Contains registerRoutes and the utility/post/log/catch-all sub-registrars.
 * Plugin-specific routes (plugins, agents, snapshots) live in PluginRoutesTrait.
 *
 * @package RiseupAsia\Traits\Route
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;

trait RouteRegistrationTrait
{
    /**
     * Register REST API routes.
     */
    public function registerRoutes() {
        $this->fileLogger->info('Registering REST API routes', array('namespace' => PluginConfigType::apiFullNamespace()));

        $registered = 0;
        $failed = 0;

        $safeRegister = function (string $route, array $args) use (&$registered, &$failed): void {
            try {
                register_rest_route(PluginConfigType::apiFullNamespace(), $route, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->error('Failed to register route: ' . $route . ' - ' . $e->getMessage());
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
    private function registerUtilityRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Status->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleStatus'),
            'permission_callback' => $this->buildPermissionCallback('status', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::Openapi->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleOpenapi'),
            'permission_callback' => $this->buildPermissionCallback('openapi', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::OpcacheReset->route(), array(
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
    private function registerPostRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Posts->route(), array(
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

        $safeRegister(EndpointType::Categories->route(), array(
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
    private function registerLogRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Logs->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleQueryLogs'),
            'permission_callback' => $this->buildPermissionCallback('logs', array($this, 'checkLogsPermission')),
        ));

        $safeRegister(EndpointType::LogsStats->route(), array(
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
    private function registerCatchAllRoute(callable $safeRegister): void {
        $safeRegister('/(?P<invalid_path>.+)', array(
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