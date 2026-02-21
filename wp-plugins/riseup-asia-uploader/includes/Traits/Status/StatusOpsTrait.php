<?php
/**
 * StatusOpsTrait — OpenAPI and OPcache reset handlers.
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
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\ResultHelper;

trait StatusOpsTrait {

    public function handleOpenapi(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('OpenAPI endpoint called');
        $spec = $this->loadOpenApiSpec();
        if ($spec instanceof WP_REST_Response) {
            return $spec;
        }
        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, HttpStatusType::Ok->value);
    }

    private function loadOpenApiSpec(): array|WP_REST_Response {
        $spec_file = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value . '/data/openapi.json';
        if (PathHelper::isFileMissing($spec_file)) {
            return $this->buildSpecError('OpenAPI specification file not found', $spec_file);
        }

        return $this->parseSpecFile($spec_file);
    }

    private function buildSpecError(string $message, string $path): WP_REST_Response {
        $this->fileLogger->error($message, array('path' => $path));

        return new WP_REST_Response(
            ResultHelper::error($message),
            HttpStatusType::NotFound->value,
        );
    }

    private function parseSpecFile(string $spec_file): array|WP_REST_Response {
        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->fileLogger->error('Failed to read OpenAPI spec file');

            return new WP_REST_Response(
                ResultHelper::error('Failed to read OpenAPI specification'),
                HttpStatusType::ServerError->value,
            );
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->fileLogger->error('Invalid JSON in OpenAPI spec file');

            return new WP_REST_Response(
                ResultHelper::error('Invalid OpenAPI specification format'),
                HttpStatusType::ServerError->value,
            );
        }

        return $spec;
    }

    public function handleOpcacheReset(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('OPcache reset endpoint called');
        $result = $this->buildOpcacheResult();
        $result['files_invalidated'] = $this->invalidatePluginFiles();
        wp_cache_delete('plugins', 'plugins');

        return EnvelopeBuilder::success('OPcache reset complete')
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::OpcacheReset->value)
            ->setSingleResult($result)
            ->toResponse();
    }

    private function buildOpcacheResult(): array {
        $result = ResultHelper::ok(array(
            'opcache_available' => function_exists('opcache_reset'),
            'opcache_reset'     => false,
            'files_invalidated' => 0,
            'timestamp'         => DateHelper::nowIso(),
        ));

        if (function_exists('opcache_reset')) {
            $result['opcache_reset'] = opcache_reset();
            $this->fileLogger->info('OPcache reset executed', array('result' => $result['opcache_reset']));
        }

        return $result;
    }

    private function invalidatePluginFiles(): int {
        if (BooleanHelpers::isFuncMissing('opcache_invalidate')) {
            return 0;
        }
        $plugin_dir = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value;
        $files_to_invalidate = array(
            $plugin_dir . '/' . PluginConfigType::Slug->value . '.php',
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
