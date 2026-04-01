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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Enums\PhpNativeType;

trait StatusPayloadTrait {

    /** Handle status check. */
    public function handleStatus(WP_REST_Request $request): WP_REST_Response {
        $liveVersion = $this->detectLiveVersion();
        $dbAvailable = $this->db !== null;

        $this->fileLogger->info('Status endpoint called', [
            'endpoint'    => 'GET /' . EndpointType::Status->value,
            'namespace'   => PluginConfigType::apiFullNamespace(),
            'version'     => $liveVersion,
            'dbAvailable' => $dbAvailable,
            'requestedAt' => DateHelper::nowIso(),
        ]);

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
        $routes = [];
        $nsPrefix = '/' . PluginConfigType::apiFullNamespace();

        foreach (rest_get_server()->get_routes() as $route => $handlers) {
            $isOtherNamespace = (strpos($route, $nsPrefix) !== 0);

            if ($isOtherNamespace) {
                continue;
            }

            $routes[] = [ResponseKeyType::Route->value => $route, ResponseKeyType::Methods->value => $this->extractRouteMethods($handlers)];
        }

        return $routes;
    }

    /** Extract unique HTTP methods from route handlers. */
    private function extractRouteMethods(array $handlers): array {
        $methods = [];

        foreach ($handlers as $handler) {
            if (BooleanHelpers::isKeyMissing($handler, 'methods')) {
                continue;
            }
            $methods = array_merge($methods, gettype($handler['methods']) === PhpNativeType::PhpArray->value
                ? array_keys($handler['methods'])
                : [$handler['methods']]);
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
        $isReadSuccess = ($content !== false);

        return $isReadSuccess ? json_decode($content, true) : null;
    }

    /**
     * Build the full status payload array.
     */
    private function buildStatusPayload(string $version, bool $dbAvailable): array {
        return array_merge(
            $this->buildCoreStatusFields($version, $dbAvailable),
            [
                'Features'         => $this->buildFeatureFlags($dbAvailable),
                'RegisteredRoutes' => $this->collectRegisteredRoutes(),
                'EndpointsRef'     => $this->loadEndpointsReference(),
            ]
        );
    }

    /** Build core status fields. */
    private function buildCoreStatusFields(string $version, bool $dbAvailable): array {
        return [
            'Plugin'      => PluginConfigType::Name->value,
            'Version'     => $version,
            'Slug'        => PluginConfigType::Slug->value,
            'Api'         => PluginConfigType::apiFullNamespace(),
            'SiteUrl'     => get_site_url(),
            'Wp'          => get_bloginfo('version'),
            'Php'         => PHP_VERSION,
            'IsActive'    => in_array(plugin_basename(__FILE__), get_option(OptionNameType::ActivePlugins->value, []), true),
            'DbAvailable' => $dbAvailable,
            'ServerTime'  => DateHelper::nowIso(),
            'Timezone'    => wp_timezone_string(),
            'UploadMaxFilesize'      => ini_get('upload_max_filesize'),
            'PostMaxSize'            => ini_get('post_max_size'),
            'MemoryLimit'            => ini_get('memory_limit'),
            'UploadMaxFilesizeBytes' => self::phpSizeToBytes(ini_get('upload_max_filesize')),
            'PostMaxSizeBytes'       => self::phpSizeToBytes(ini_get('post_max_size')),
        ];
    }

    /**
     * Convert PHP ini shorthand size (e.g. '128M', '2G') to bytes.
     */
    private static function phpSizeToBytes(string $size): int {
        $size = trim($size);
        $value = (int) $size;
        $unit = strtolower(substr($size, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Build the feature flags sub-section.
     */
    private function buildFeatureFlags(bool $dbAvailable): array {
        return [
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
        ];
    }
}
