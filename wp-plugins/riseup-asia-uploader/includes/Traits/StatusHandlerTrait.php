<?php
/**
 * StatusHandlerTrait — Status, OpenAPI, and OPcache reset handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait StatusHandlerTrait
{
    /**
     * Handle status check.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_status($request) {
        $this->file_logger->info('Status endpoint called');

        $live_version = $this->detectLiveVersion();
        $db_available = $this->db !== null;

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . ENDPOINT_STATUS)
            ->setSingleResult($this->buildStatusPayload($live_version, $db_available))
            ->toResponse();
    }

    /**
     * Detect the live plugin version from the file header on disk.
     *
     * @return string Live version string.
     */
    private function detectLiveVersion(): string {
        $main_plugin_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/' . PLUGIN_SLUG . '.php';
        clearstatcache(true, $main_plugin_file);

        if (!file_exists($main_plugin_file)) {
            return PLUGIN_VERSION;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($main_plugin_file, true);
            $constants_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/includes/constants.php';
            if (file_exists($constants_file)) {
                opcache_invalidate($constants_file, true);
            }
        }

        $header = file_get_contents($main_plugin_file, false, null, 0, 8192);
        if ($header !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $header, $m)) {
            return $m[1];
        }

        return PLUGIN_VERSION;
    }

    /**
     * Collect all registered REST routes for the plugin namespace.
     *
     * @return array Route entries with route path and methods.
     */
    private function collectRegisteredRoutes(): array {
        $routes = array();
        $ns_prefix = '/' . API_FULL_NAMESPACE;

        foreach (rest_get_server()->get_routes() as $route => $handlers) {
            if (strpos($route, $ns_prefix) !== 0) {
                continue;
            }
            $methods = array();
            foreach ($handlers as $handler) {
                if (!isset($handler['methods'])) {
                    continue;
                }
                $methods = array_merge($methods, is_array($handler['methods'])
                    ? array_keys($handler['methods'])
                    : array($handler['methods']));
            }
            $routes[] = array('route' => $route, 'methods' => array_values(array_unique($methods)));
        }

        return $routes;
    }

    /**
     * Load the endpoints.json reference file.
     *
     * @return array|null Decoded endpoints reference or null.
     */
    private function loadEndpointsReference(): ?array {
        $path = plugin_dir_path(__FILE__) . '../data/endpoints.json';
        if (!file_exists($path)) {
            // Fallback to main plugin directory
            $path = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/data/endpoints.json';
            if (!file_exists($path)) {
                return null;
            }
        }
        $content = @file_get_contents($path);
        return ($content !== false) ? json_decode($content, true) : null;
    }

    /**
     * Build the full status payload array.
     *
     * @param string $version     Live plugin version.
     * @param bool   $dbAvailable Whether the database is available.
     * @return array Status payload.
     */
    private function buildStatusPayload(string $version, bool $dbAvailable): array {
        return array(
            'Plugin'           => PLUGIN_NAME,
            'Version'          => $version,
            'Slug'             => PLUGIN_SLUG,
            'Api'              => API_FULL_NAMESPACE,
            'SiteUrl'          => get_site_url(),
            'Wp'               => get_bloginfo('version'),
            'Php'              => PHP_VERSION,
            'IsActive'         => in_array(plugin_basename(__FILE__), get_option('active_plugins', array()), true),
            'DbAvailable'      => $dbAvailable,
            'ServerTime'       => gmdate('c'),
            'Timezone'         => wp_timezone_string(),
            'Features'         => $this->buildFeatureFlags($dbAvailable),
            'RegisteredRoutes' => $this->collectRegisteredRoutes(),
            'EndpointsRef'     => $this->loadEndpointsReference(),
        );
    }

    /**
     * Build the feature flags sub-section of the status payload.
     *
     * @param bool $dbAvailable Whether the database is available.
     * @return array Feature flags.
     */
    private function buildFeatureFlags(bool $dbAvailable): array {
        return array(
            'PluginUpload'   => true,
            'PluginManage'   => true,
            'FileOperations' => true,
            'DeltaSync'      => true,
            'PostPublish'    => true,
            'CategoryManage' => true,
            'TransactionLog' => $dbAvailable,
            'ExportSelf'     => true,
            'Snapshots'      => $dbAvailable,
            'Agents'         => $dbAvailable,
        );
    }

    /**
     * Handle OpenAPI specification request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_openapi($request) {
        $this->file_logger->info('OpenAPI endpoint called');

        $spec = $this->loadOpenApiSpec();
        if ($spec instanceof WP_REST_Response) {
            return $spec;
        }

        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, HTTP_OK);
    }

    /**
     * Load and validate the OpenAPI spec from disk.
     *
     * @return array|WP_REST_Response Parsed spec or error response.
     */
    private function loadOpenApiSpec() {
        $spec_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/data/openapi.json';

        if (RiseupBooleanHelpers::is_file_missing($spec_file)) {
            $this->file_logger->error('OpenAPI spec file not found', array('path' => $spec_file));

            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'OpenAPI specification file not found',
            ), HTTP_NOT_FOUND);
        }

        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->file_logger->error('Failed to read OpenAPI spec file');

            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Failed to read OpenAPI specification',
            ), HTTP_SERVER_ERROR);
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->file_logger->error('Invalid JSON in OpenAPI spec file');

            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Invalid OpenAPI specification format',
            ), HTTP_SERVER_ERROR);
        }

        return $spec;
    }

    /**
     * Handle OPcache reset request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_opcache_reset($request) {
        $this->file_logger->info('OPcache reset endpoint called');

        $result = array(
            'success'           => true,
            'opcache_available' => function_exists('opcache_reset'),
            'opcache_reset'     => false,
            'files_invalidated' => 0,
            'timestamp'         => gmdate('c'),
        );

        if (function_exists('opcache_reset')) {
            $result['opcache_reset'] = opcache_reset();
            $this->file_logger->info('OPcache reset executed', array('result' => $result['opcache_reset']));
        }

        $result['files_invalidated'] = $this->invalidatePluginFiles();

        wp_cache_delete('plugins', 'plugins');

        return RiseupEnvelopeBuilder::success('OPcache reset complete')
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_OPCACHE_RESET)
            ->setSingleResult($result)
            ->toResponse();
    }

    /**
     * Invalidate OPcache for critical plugin files.
     *
     * @return int Number of files invalidated.
     */
    private function invalidatePluginFiles(): int {
        if (!function_exists('opcache_invalidate')) {
            return 0;
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG;
        $files_to_invalidate = array(
            $plugin_dir . '/' . PLUGIN_SLUG . '.php',
            $plugin_dir . '/includes/constants.php',
        );
        $invalidated = 0;

        foreach ($files_to_invalidate as $file) {
            if (file_exists($file)) {
                clearstatcache(true, $file);
                opcache_invalidate($file, true);
                $invalidated++;
            }
        }

        return $invalidated;
    }
}
