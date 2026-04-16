<?php
/**
 * DebugRoutesTrait — /debug/routes diagnostic endpoint.
 *
 * Lists all registered REST API routes for the plugin namespace.
 *
 * @package QUpload\Traits\Debug
 * @since   2.32.0
 */

namespace QUpload\Traits\Debug;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\PhpNativeType;
use QUpload\Enums\ResponseKeyType;

trait DebugRoutesTrait
{
    /**
     * Handle GET /debug/routes — list all registered routes for the plugin namespace.
     */
    public function handleDebugRoutes(WP_REST_Request $request): WP_REST_Response {
        $namespace = PluginConfigType::apiFullNamespace();
        $prefix = '/' . $namespace;

        $server = rest_get_server();
        $allRoutes = $server->get_routes($namespace);

        $routes = [];

        foreach ($allRoutes as $pattern => $handlers) {
            $isPluginRoute = (strpos($pattern, $prefix) === 0);

            if ($isPluginRoute === false) {
                continue;
            }

            $relativePath = substr($pattern, strlen($prefix));
            $methods = [];

            foreach ($handlers as $handler) {
                $handlerMethods = $handler['methods'] ?? [];

                if (gettype($handlerMethods) === PhpNativeType::PhpArray->value) {
                    $methods = array_merge($methods, array_keys($handlerMethods));
                } elseif (gettype($handlerMethods) === PhpNativeType::PhpString->value) {
                    $methods = array_merge($methods, explode(',', $handlerMethods));
                }
            }

            $methods = array_unique(array_map('trim', $methods));
            sort($methods);

            $category = $this->categorizeRoute($relativePath);

            $routes[] = [
                ResponseKeyType::Pattern->value  => $pattern,
                ResponseKeyType::Path->value     => $relativePath ?: '/',
                ResponseKeyType::Methods->value  => array_values($methods),
                ResponseKeyType::Category->value => $category,
            ];
        }

        usort($routes, function (array $a, array $b): int {
            $catKey = ResponseKeyType::Category->value;
            $pathKey = ResponseKeyType::Path->value;
            $catCmp = strcmp($a[$catKey], $b[$catKey]);

            if ($catCmp !== 0) {
                return $catCmp;
            }

            return strcmp($a[$pathKey], $b[$pathKey]);
        });

        $categories = [];

        foreach ($routes as $route) {
            $cat = $route[ResponseKeyType::Category->value];
            $categories[$cat] = ($categories[$cat] ?? 0) + 1;
        }

        return new WP_REST_Response([
            ResponseKeyType::Success->value     => true,
            ResponseKeyType::Namespace->value   => $namespace,
            ResponseKeyType::TotalRoutes->value => count($routes),
            ResponseKeyType::Categories->value  => $categories,
            ResponseKeyType::Routes->value      => $routes,
            ResponseKeyType::Version->value     => PluginConfigType::Version->value,
        ], HttpStatusType::Ok->value);
    }

    /**
     * Categorize a route path into a logical group.
     */
    private function categorizeRoute(string $path): string {
        $prefixMap = [
            '/logs/'     => 'log',
            '/logs'      => 'log',
            '/machines/' => 'machine',
            '/debug/'    => 'debug',
            '/status'    => 'utility',
            '/upload'    => 'core',
            '/activate'  => 'lifecycle',
            '/deactivate' => 'lifecycle',
            '/plugins'   => 'plugin',
        ];

        foreach ($prefixMap as $pfx => $category) {
            if (strpos($path, $pfx) === 0) {
                return $category;
            }
        }

        return 'other';
    }
}
