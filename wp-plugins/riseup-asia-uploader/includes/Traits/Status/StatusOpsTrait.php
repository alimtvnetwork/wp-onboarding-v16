<?php
/**
 * StatusOpsTrait — OpenAPI and OPcache reset handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait StatusOpsTrait {

    /**
     * Handle OpenAPI specification request.
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
     */
    private function loadOpenApiSpec() {
        $spec_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/data/openapi.json';

        if (RiseupBooleanHelpers::is_file_missing($spec_file)) {
            return $this->buildSpecError('OpenAPI specification file not found', $spec_file);
        }

        return $this->parseSpecFile($spec_file);
    }

    /** Build an error response for missing spec file. */
    private function buildSpecError(string $message, string $path): WP_REST_Response {
        $this->file_logger->error($message, array('path' => $path));

        return new WP_REST_Response(array('success' => false, 'error' => $message), HTTP_NOT_FOUND);
    }

    /** Read and parse the spec JSON file. */
    private function parseSpecFile(string $spec_file) {
        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->file_logger->error('Failed to read OpenAPI spec file');
            return new WP_REST_Response(array('success' => false, 'error' => 'Failed to read OpenAPI specification'), HTTP_SERVER_ERROR);
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->file_logger->error('Invalid JSON in OpenAPI spec file');
            return new WP_REST_Response(array('success' => false, 'error' => 'Invalid OpenAPI specification format'), HTTP_SERVER_ERROR);
        }

        return $spec;
    }

    /**
     * Handle OPcache reset request.
     */
    public function handle_opcache_reset($request) {
        $this->file_logger->info('OPcache reset endpoint called');

        $result = $this->buildOpcacheResult();
        $result['files_invalidated'] = $this->invalidatePluginFiles();
        wp_cache_delete('plugins', 'plugins');

        return RiseupEnvelopeBuilder::success('OPcache reset complete')
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_OPCACHE_RESET)
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
            $this->file_logger->info('OPcache reset executed', array('result' => $result['opcache_reset']));
        }

        return $result;
    }

    /**
     * Invalidate OPcache for critical plugin files.
     */
    private function invalidatePluginFiles(): int {
        if (RiseupBooleanHelpers::is_func_missing('opcache_invalidate')) {
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
