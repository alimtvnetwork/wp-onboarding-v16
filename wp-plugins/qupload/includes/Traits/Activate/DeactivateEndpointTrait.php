<?php
/**
 * DeactivateEndpointTrait — PUT /deactivate REST endpoint handler for QUpload.
 *
 * Deactivates an installed plugin by slug via the WordPress REST API.
 * Uses PUT method per API standards (idempotent state mutation).
 *
 * @package QUpload\Traits\Activate
 * @since   2.12.0
 */

namespace QUpload\Traits\Activate;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;

use QUpload\Enums\EndpointType;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\RequestFieldType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\EnvelopeBuilder;

trait DeactivateEndpointTrait
{
    /** Handle PUT /deactivate endpoint. */
    public function handleDeactivate(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Deactivate endpoint called');

        return $this->safeExecute(
            fn () => $this->executeDeactivation($request),
            'handleDeactivate',
            ['endpoint' => 'deactivate'],
        );
    }

    private function executeDeactivation(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();
        $slug = sanitize_file_name($data[RequestFieldType::Slug->value] ?? '');

        if (empty($slug)) {
            $this->fileLogger->warn('Deactivate called without slug');

            return $this->errorResponse(RequestFieldType::Slug->value . ' is required', HttpStatusType::BadRequest->value);
        }

        $this->fileLogger->info('Deactivating plugin', ['slug' => $slug]);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = $this->findPluginFile($slug);

        if (empty($pluginFile)) {
            $this->fileLogger->error('Plugin not found', ['slug' => $slug]);

            return $this->errorResponse('Plugin not found: ' . $slug, HttpStatusType::NotFound->value);
        }

        if (!is_plugin_active($pluginFile)) {
            $version = $this->detectInstalledVersion($pluginFile);
            $this->fileLogger->info('Plugin already inactive', ['slug' => $slug]);

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Deactivate->route())
                ->setSingleResult([
                    ResponseKeyType::PluginSlug->value    => $slug,
                    ResponseKeyType::Deactivated->value   => true,
                    ResponseKeyType::PluginVersion->value => $version,
                    ResponseKeyType::Message->value       => 'Plugin was already inactive',
                ])
                ->toResponse();
        }

        return $this->performDeactivation($pluginFile, $slug);
    }

    private function performDeactivation(string $pluginFile, string $slug): WP_REST_Response {
        $version = $this->detectInstalledVersion($pluginFile);

        try {
            deactivate_plugins($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Deactivation exception for ' . $slug);

            return $this->errorResponse('Deactivation failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        // Verify deactivation succeeded
        if (is_plugin_active($pluginFile)) {
            $this->fileLogger->error('Plugin still active after deactivation attempt', ['slug' => $slug]);

            return $this->errorResponse('Deactivation failed: plugin is still active', HttpStatusType::ServerError->value);
        }

        $this->fileLogger->info('Plugin deactivated successfully', ['slug' => $slug, 'version' => $version]);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Deactivate->route())
            ->setSingleResult([
                ResponseKeyType::PluginSlug->value    => $slug,
                ResponseKeyType::Deactivated->value   => true,
                ResponseKeyType::PluginVersion->value => $version,
            ])
            ->toResponse();
    }
}
