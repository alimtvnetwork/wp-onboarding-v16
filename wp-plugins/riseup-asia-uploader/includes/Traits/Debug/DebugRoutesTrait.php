<?php
/**
 * DebugRoutesTrait — /debug/routes diagnostic endpoint.
 *
 * Lists all registered REST API routes for the plugin namespace,
 * grouped by registration category, to aid in diagnosing missing
 * route registration (see issue #42).
 *
 * @package RiseupAsia\Traits\Debug
 * @since   2.32.0
 */

namespace RiseupAsia\Traits\Debug;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\PhpNativeType;

trait DebugRoutesTrait
{
    /**
     * Handle GET /debug/routes — list all registered routes for the plugin namespace.
     */
    public function handleDebugRoutes(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function () {
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
                    'pattern'  => $pattern,
                    'path'     => $relativePath ?: '/',
                    'methods'  => array_values($methods),
                    'category' => $category,
                ];
            }

            usort($routes, function (array $a, array $b): int {
                $catCmp = strcmp($a['category'], $b['category']);

                if ($catCmp !== 0) {
                    return $catCmp;
                }

                return strcmp($a['path'], $b['path']);
            });

            $categories = [];

            foreach ($routes as $route) {
                $cat = $route['category'];
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }

            return new WP_REST_Response([
                ResponseKeyType::Success->value => true,
                'namespace'  => $namespace,
                'totalRoutes' => count($routes),
                'categories' => $categories,
                'routes'     => $routes,
                'version'    => PluginConfigType::Version->value,
            ], HttpStatusType::Ok->value);
        }, 'debug_routes');
    }

    /**
     * Categorize a route path into a logical group.
     */
    private function categorizeRoute(string $path): string {
        $prefixMap = [
            '/snapshots/'       => 'snapshot',
            '/agents'           => 'agent',
            '/plugins/'         => 'plugin',
            '/users'            => 'user',
            '/cloud-storage/'   => 'cloud_storage',
            '/logs/'            => 'log',
            '/logs'             => 'log',
            '/error-'           => 'log',
            '/machines/'        => 'machine',
            '/site-'            => 'site_settings',
            '/debug/'           => 'debug',
            '/status'           => 'utility',
            '/openapi'          => 'utility',
            '/opcache-'         => 'utility',
            '/upload'           => 'core',
            '/posts'            => 'post',
            '/categories'       => 'post',
        ];

        foreach ($prefixMap as $prefix => $category) {
            $isMatch = (strpos($path, $prefix) === 0);

            if ($isMatch) {
                return $category;
            }
        }

        return 'other';
    }
}
