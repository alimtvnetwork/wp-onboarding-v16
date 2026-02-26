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
        $specFile = PathHelper::getOpenApiJsonPath();
        if (PathHelper::isFileMissing($specFile)) {
            return $this->buildSpecError('OpenAPI specification file not found', $specFile);
        }

        return $this->parseSpecFile($specFile);
    }

    private function buildSpecError(string $message, string $path): WP_REST_Response {
        $this->fileLogger->error($message, array('path' => $path));

        return new WP_REST_Response(
            ResultHelper::error($message),
            HttpStatusType::NotFound->value,
        );
    }

    private function parseSpecFile(string $specFile): array|WP_REST_Response {
        $specContent = file_get_contents($specFile);

        if ($specContent === false) {
            $this->fileLogger->error('Failed to read OpenAPI spec file');

            return new WP_REST_Response(
                ResultHelper::error('Failed to read OpenAPI specification'),
                HttpStatusType::ServerError->value,
            );
        }

        $spec = json_decode($specContent, true);

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
        $result[ResponseKeyType::FilesInvalidated->value] = $this->invalidatePluginFiles();
        wp_cache_delete('plugins', 'plugins');

        return EnvelopeBuilder::success('OPcache reset complete')
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::OpcacheReset->value)
            ->setSingleResult($result)
            ->toResponse();
    }

    private function buildOpcacheResult(): array {
        $result = ResultHelper::ok(array(
            ResponseKeyType::OpcacheAvailable->value => function_exists('opcache_reset'),
            ResponseKeyType::OpcacheReset->value     => false,
            ResponseKeyType::FilesInvalidated->value => 0,
            ResponseKeyType::Timestamp->value        => DateHelper::nowIso(),
        ));

        if (function_exists('opcache_reset')) {
            $result[ResponseKeyType::OpcacheReset->value] = opcache_reset();
            $this->fileLogger->info('OPcache reset executed', array('result' => $result[ResponseKeyType::OpcacheReset->value]));
        }

        return $result;
    }

    private function invalidatePluginFiles(): int {
        if (BooleanHelpers::isFuncMissing('opcache_invalidate')) {
            return 0;
        }
        $filesToInvalidate = array(
            PathHelper::getPluginMainFile(),
            PathHelper::getConstantsFile(),
        );
        $invalidated = 0;
        foreach ($filesToInvalidate as $file) {
            if (file_exists($file)) {
                clearstatcache(true, $file);
                opcache_invalidate($file, true);
                $invalidated++;
            }
        }

        return $invalidated;
    }
}
