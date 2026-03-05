<?php
/**
 * ActivateHandlerTrait — Activate endpoint handler for QUpload.
 *
 * @package QUpload\Traits\Activate
 * @since   1.0.0
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

trait ActivateHandlerTrait
{
    /** Handle POST /activate endpoint. */
    public function handleActivate(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Activate endpoint called');

        try {
            return $this->executeActivation($request);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Activate failed');

            return $this->errorResponse('Activate failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function executeActivation(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();
        $slug = sanitize_file_name($data['slug'] ?? '');

        if (empty($slug)) {
            $this->fileLogger->warn('Activate called without slug');

            return $this->errorResponse('slug is required', HttpStatusType::BadRequest->value);
        }

        $this->fileLogger->info('Activating plugin', ['slug' => $slug]);

        $pluginFile = $this->findPluginFile($slug);

        if (empty($pluginFile)) {
            $this->fileLogger->error('Plugin not found', ['slug' => $slug]);

            return $this->errorResponse('Plugin not found: ' . $slug, HttpStatusType::NotFound->value);
        }

        return $this->performActivation($pluginFile, $slug);
    }

    private function performActivation(string $pluginFile, string $slug): WP_REST_Response {
        try {
            $result = activate_plugin($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Activation exception for ' . $slug);

            return $this->errorResponse('Activation failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        if (is_wp_error($result)) {
            $this->fileLogger->error('Activation returned WP_Error', ['slug' => $slug, 'error' => $result->get_error_message()]);

            return $this->errorResponse('Activation failed: ' . $result->get_error_message(), HttpStatusType::ServerError->value);
        }

        $version = $this->detectInstalledVersion($pluginFile);
        $this->fileLogger->info('Plugin activated successfully', ['slug' => $slug, 'version' => $version]);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Activate->route())
            ->setSingleResult([
                ResponseKeyType::PluginSlug->value    => $slug,
                ResponseKeyType::Activated->value     => true,
                ResponseKeyType::PluginVersion->value => $version,
            ])
            ->toResponse();
    }
}
