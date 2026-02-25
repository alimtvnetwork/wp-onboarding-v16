<?php
/**
 * StatusPayloadTrait — status endpoint, version detection, and payload building.
 *
 * @package RiseupAsia\Traits\Status
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Status;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\DateHelper;

trait StatusPayloadTrait {

    /** Handle status check. */
    public function handleStatus(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Status endpoint called');

        $liveVersion = $this->detectLiveVersion();
        $dbAvailable = $this->db !== null;

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::Status->value)
            ->setSingleResult($this->buildStatusPayload($liveVersion, $dbAvailable))
            ->toResponse();
    }

    /**
     * Detect the live plugin version from the file header on disk.
     */
    private function detectLiveVersion(): string {
        $mainPluginFile = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value . '/' . PluginConfigType::Slug->value . '.php';
        clearstatcache(true, $mainPluginFile);

        if (PathHelper::isFileMissing($mainPluginFile)) {
            return PluginConfigType::Version->value;
        }

        $this->invalidateVersionCaches($mainPluginFile);

        return $this->parseVersionFromHeader($mainPluginFile);
    }

    /** Invalidate OPcache for plugin file and constants. */
    private function invalidateVersionCaches(string $mainPluginFile) {
        if (BooleanHelpers::isFuncMissing('opcache_invalidate')) {
            return;
        }

        opcache_invalidate($mainPluginFile, true);
        $constantsFile = PathHelper::getConstantsFile();
        if (file_exists($constantsFile)) {
            opcache_invalidate($constantsFile, true);
        }
    }

    /** Parse the Version header from a plugin file. */
    private function parseVersionFromHeader(string $file): string {
        $header = file_get_contents($file, false, null, 0, 8192);
        if ($header !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $header, $m)) {
            return $m[1];
        }

        return PluginConfigType::Version->value;
    }

    /**
     * Collect all registered REST routes for the plugin namespace.
     */
    private function collectRegisteredRoutes(): array {
        $routes = array();
        $nsPrefix = '/' . PluginConfigType::apiFullNamespace();

        foreach (rest_get_server()->get_routes() as $route => $handlers) {
            if (strpos($route, $nsPrefix) !== 0) {
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
            if (BooleanHelpers::isKeyMissing($handler, 'methods')) {
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
        $path = PathHelper::getEndpointsJsonPath();
        if (PathHelper::isFileMissing($path)) {
            return null;
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
            'Plugin'      => PluginConfigType::Name->value,
            'Version'     => $version,
            'Slug'        => PluginConfigType::Slug->value,
            'Api'         => PluginConfigType::apiFullNamespace(),
            'SiteUrl'     => get_site_url(),
            'Wp'          => get_bloginfo('version'),
            'Php'         => PHP_VERSION,
            'IsActive'    => in_array(plugin_basename(__FILE__), get_option(OptionNameType::ActivePlugins->value, array()), true),
            'DbAvailable' => $dbAvailable,
            'ServerTime'  => DateHelper::nowIso(),
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
