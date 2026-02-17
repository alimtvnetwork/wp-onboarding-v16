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
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait StatusOpsTrait {

    /**
     * Handle OpenAPI specification request.
     */
    public function handleOpenapi(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('OpenAPI endpoint called');

        $spec = $this->loadOpenApiSpec();
        if ($spec instanceof WP_REST_Response) {
            return $spec;
        }

        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, HttpStatusType::Ok->value);
    }

    /**
     * Load and validate the OpenAPI spec from disk.
     */
    private function loadOpenApiSpec(): array|WP_REST_Response {
        $spec_file = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value . '/data/openapi.json';

        if (PathHelper::isFileMissing($spec_file)) {
            return $this->buildSpecError('OpenAPI specification file not found', $spec_file);
        }

        return $this->parseSpecFile($spec_file);
    }

    /** Build an error response for missing spec file. */
    private function buildSpecError(string $message, string $path): WP_REST_Response {
        $this->fileLogger->error($message, array('path' => $path));

        return new WP_REST_Response(array('success' => false, 'error' => $message), HttpStatusType::NotFound->value);
    }

    /** Read and parse the spec JSON file. */
    private function parseSpecFile(string $spec_file): array|WP_REST_Response {
        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->fileLogger->error('Failed to read OpenAPI spec file');

            return new WP_REST_Response(array('success' => false, 'error' => 'Failed to read OpenAPI specification'), HttpStatusType::ServerError->value);
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->fileLogger->error('Invalid JSON in OpenAPI spec file');

            return new WP_REST_Response(array('success' => false, 'error' => 'Invalid OpenAPI specification format'), HttpStatusType::ServerError->value);
        }

        return $spec;
    }

    /**
     * Handle OPcache reset request.
     */
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

    /** Build the base OPcache result with reset execution. */
    private function buildOpcacheResult(): array {
        $result = array(
            'success'           => true,
            'opcache_available' => function_exists('opcache_reset'),
            'opcache_reset'     => false,
            'files_invalidated' => 0,
            'timestamp'         => gmdate('c'),
        );

        if (function_exists('opcache_reset')) {
            $result['opcache_reset'] = opcache_reset();
            $this->fileLogger->info('OPcache reset executed', array('result' => $result['opcache_reset']));
        }

        return $result;
    }

    /**
     * Invalidate OPcache for critical plugin files.
     */
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