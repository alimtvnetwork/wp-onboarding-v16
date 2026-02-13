<?php
/**
 * StatusPayloadTrait — status endpoint, version detection, and payload building.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait StatusPayloadTrait {

    /**
     * Handle status check.
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
     */
    private function detectLiveVersion(): string {
        $main_plugin_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/' . PLUGIN_SLUG . '.php';
        clearstatcache(true, $main_plugin_file);

        if (!file_exists($main_plugin_file)) {
            return PLUGIN_VERSION;
        }

        $this->invalidateVersionCaches($main_plugin_file);

        return $this->parseVersionFromHeader($main_plugin_file);
    }

    /** Invalidate OPcache for plugin file and constants. */
    private function invalidateVersionCaches(string $main_plugin_file) {
        if (!function_exists('opcache_invalidate')) {
            return;
        }

        opcache_invalidate($main_plugin_file, true);
        $constants_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/includes/constants.php';
        if (file_exists($constants_file)) {
            opcache_invalidate($constants_file, true);
        }
    }

    /** Parse the Version header from a plugin file. */
    private function parseVersionFromHeader(string $file): string {
        $header = file_get_contents($file, false, null, 0, 8192);
        if ($header !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $header, $m)) {
            return $m[1];
        }

        return PLUGIN_VERSION;
    }

    /**
     * Collect all registered REST routes for the plugin namespace.
     */
    private function collectRegisteredRoutes(): array {
        $routes = array();
        $ns_prefix = '/' . API_FULL_NAMESPACE;

        foreach (rest_get_server()->get_routes() as $route => $handlers) {
            if (strpos($route, $ns_prefix) !== 0) {
                continue;
            }

            $routes[] = array('route' => $route, 'methods' => $this->extractRouteMethods($handlers));
        }

        return $routes;
    }

    /** Extract unique HTTP methods from route handlers. */
    private function extractRouteMethods(array $handlers): array {
        $methods = array();
        foreach ($handlers as $handler) {
            if (!isset($handler['methods'])) {
                continue;
            }
            $methods = array_merge($methods, is_array($handler['methods'])
                ? array_keys($handler['methods'])
                : array($handler['methods']));
        }

        return array_values(array_unique($methods));
    }

    /**
     * Load the endpoints.json reference file.
     */
    private function loadEndpointsReference(): ?array {
        $path = plugin_dir_path(__FILE__) . '../data/endpoints.json';
        if (!file_exists($path)) {
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
     */
    private function buildStatusPayload(string $version, bool $dbAvailable): array {
        return array_merge(
            $this->buildCoreStatusFields($version, $dbAvailable),
            array(
                'Features'         => $this->buildFeatureFlags($dbAvailable),
                'RegisteredRoutes' => $this->collectRegisteredRoutes(),
                'EndpointsRef'     => $this->loadEndpointsReference(),
            )
        );
    }

    /** Build core status fields. */
    private function buildCoreStatusFields(string $version, bool $dbAvailable): array {
        return array(
            'Plugin'      => PLUGIN_NAME,
            'Version'     => $version,
            'Slug'        => PLUGIN_SLUG,
            'Api'         => API_FULL_NAMESPACE,
            'SiteUrl'     => get_site_url(),
            'Wp'          => get_bloginfo('version'),
            'Php'         => PHP_VERSION,
            'IsActive'    => in_array(plugin_basename(__FILE__), get_option('active_plugins', array()), true),
            'DbAvailable' => $dbAvailable,
            'ServerTime'  => gmdate('c'),
            'Timezone'    => wp_timezone_string(),
        );
    }

    /**
     * Build the feature flags sub-section.
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
}
