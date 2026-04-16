<?php
/**
 * RouteRegistrationCoreTrait — Core route registration orchestrator and utility/post routes.
 *
 * @package RiseupAsia\Traits\Route
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;

trait RouteRegistrationCoreTrait
{
    /**
     * Register REST API routes.
     */
    public function registerRoutes() {
        $namespace = PluginConfigType::apiFullNamespace();
        $isVerbose = \RiseupAsia\Helpers\InitHelpers::isBootVerbose();

        $registered = 0;
        $failed = 0;

        $safeRegister = function (string $route, array $args) use ($namespace, &$registered, &$failed, $isVerbose): void {
            try {
                register_rest_route($namespace, $route, $args);
                $registered++;

                if ($isVerbose) {
                    $this->fileLogger->debug("[BOOT] Route registered: $route");
                }
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->logException($e, 'Failed to register route: ' . $route);
            }
        };

        $groups = [
            'utility'        => fn() => $this->registerUtilityRoutes($safeRegister),
            'plugin'         => fn() => $this->registerPluginRoutes($safeRegister),
            'post'           => fn() => $this->registerPostRoutes($safeRegister),
            'log'            => fn() => $this->registerLogRoutes($safeRegister),
            'log_management' => fn() => $this->registerLogManagementRoutes($safeRegister),
            'agent'          => fn() => $this->registerAgentRoutes($safeRegister, $failed),
            'snapshot'       => fn() => $this->registerSnapshotRoutes($safeRegister),
            'user'           => fn() => $this->registerUserRoutes($safeRegister),
            'cloud_storage'  => fn() => $this->registerCloudStorageRoutes($safeRegister),
            'site_settings'  => fn() => $this->registerSiteSettingsRoutes($safeRegister),
            'debug'          => fn() => $this->registerDebugRoutes($safeRegister),
            'catch_all'      => fn() => $this->registerCatchAllRoute($safeRegister),
        ];

        $groupsFailed = [];

        foreach ($groups as $groupName => $registrar) {
            try {
                $registrar();
            } catch (Throwable $e) {
                $groupsFailed[] = $groupName;
                $this->fileLogger->logException($e, "Route group '$groupName' failed — remaining groups unaffected");
            }
        }

        $hasGroupFailures = (count($groupsFailed) > 0);
        $groupFailureSuffix = $hasGroupFailures
            ? ', groups failed: ' . implode(', ', $groupsFailed)
            : '';

        $this->fileLogger->info(
            "Routes registered: $registered OK, $failed failed" . $groupFailureSuffix,
            ['namespace' => $namespace],
        );
    }

    /**
     * Register utility routes (status, openapi, opcache-reset).
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerUtilityRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Ping->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handlePing'],
            'permission_callback' => '__return_true',
        ]);

        $safeRegister(EndpointType::Status->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleStatus'],
            'permission_callback' => $this->buildPermissionCallback('status', [$this, 'checkStatusPermission']),
        ]);

        $safeRegister(EndpointType::Openapi->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleOpenapi'],
            'permission_callback' => $this->buildPermissionCallback('openapi', [$this, 'checkStatusPermission']),
        ]);

        $safeRegister(EndpointType::OpcacheReset->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleOpcacheReset'],
            'permission_callback' => $this->buildPermissionCallback('opcache_reset', [$this, 'checkPluginPermission']),
        ]);
    }

    /**
     * Register post and category routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerPostRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Posts->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleListPosts'],
                'permission_callback' => $this->buildPermissionCallback('posts', [$this, 'checkPostPermission']),
            ],
            [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleCreatePost'],
                'permission_callback' => $this->buildPermissionCallback('posts', [$this, 'checkPostPermission']),
            ],
        ]);

        $safeRegister(EndpointType::Categories->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleListCategories'],
                'permission_callback' => $this->buildPermissionCallback('categories', [$this, 'checkPostPermission']),
            ],
            [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleCreateCategory'],
                'permission_callback' => $this->buildPermissionCallback('categories', [$this, 'checkPostPermission']),
            ],
        ]);
    }
}
