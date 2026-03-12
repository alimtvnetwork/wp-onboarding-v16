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
        $this->fileLogger->info('Registering REST API routes', ['namespace' => $namespace]);

        $registered = 0;
        $failed = 0;

        $safeRegister = function (string $route, array $args) use ($namespace, &$registered, &$failed): void {
            try {
                register_rest_route($namespace, $route, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->logException($e, 'Failed to register route: ' . $route);

                throw $e;
            }
        };

        $this->registerCoreRoutes($safeRegister);

        $this->fileLogger->info("Route registration complete: $registered registered, $failed failed");
    }

    private function registerCoreRoutes(callable $safeRegister): void {
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
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleActivate'],
            'permission_callback' => [$this, 'checkPluginPermission'],
        ]);
    }
}
