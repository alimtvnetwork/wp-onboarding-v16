<?php
/**
 * RouteRegistrationTrait — REST API route registration for QUpload.
 *
 * @package QUpload\Traits\Route
 * @since   1.0.0
 */

namespace QUpload\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use QUpload\Enums\EndpointType;
use QUpload\Enums\HttpMethodType;
use QUpload\Enums\PluginConfigType;

trait RouteRegistrationTrait
{
    /** Register all REST API routes. */
    public function registerRoutes(): void {
        $namespace = PluginConfigType::apiFullNamespace();
        $isVerbose = \QUpload\Core\Plugin::isBootVerbose();

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
                $this->fileLogger->logCriticalException($e, 'Failed to register route: ' . $route);
            }
        };

        $groups = [
            'core'              => fn() => $this->registerCoreRoutes($safeRegister),
            'machine_management' => fn() => $this->registerMachineManagementRoutes($safeRegister),
            'log_management'    => fn() => $this->registerLogManagementRoutes($safeRegister),
            'debug'             => fn() => $this->registerDebugRoutes($safeRegister),
        ];

        $groupsFailed = [];

        foreach ($groups as $groupName => $registrar) {
            try {
                $registrar();
            } catch (Throwable $e) {
                $groupsFailed[] = $groupName;
                $this->fileLogger->logCriticalException($e, "Route group '$groupName' failed — remaining groups unaffected");
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

    private function registerMachineManagementRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::MachinesApprove->route(), [
            'methods'             => HttpMethodType::Put->value,
            'callback'            => [$this, 'handleApproveMachine'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);
    }

    private function registerLogManagementRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::LogsStatus->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsStatus'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsRotationStatus->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsRotationStatus'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsClear->route(), [
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleLogsClearRequest'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsConfirm->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleLogsClearConfirm'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsEmail->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleLogsEmail'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsRetrieve->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsRetrieve'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::LogsDedupRegistry->route(), [
            'methods'             => HttpMethodType::Get->value . ', ' . HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleLogsDedupRegistry'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);
    }

    private function registerCoreRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Ping->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handlePing'],
            'permission_callback' => '__return_true',
        ]);

        $safeRegister(EndpointType::Status->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleStatus'],
            'permission_callback' => [$this, 'checkStatusPermission'],
        ]);

        $safeRegister(EndpointType::Upload->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleUpload'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::Activate->route(), [
            'methods'             => HttpMethodType::Put->value,
            'callback'            => [$this, 'handleActivate'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::Deactivate->route(), [
            'methods'             => HttpMethodType::Put->value,
            'callback'            => [$this, 'handleDeactivatePlugin'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);

        $safeRegister(EndpointType::Plugins->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handlePlugins'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);
    }

    private function registerDebugRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::DebugRoutes->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleDebugRoutes'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);
    }
}
