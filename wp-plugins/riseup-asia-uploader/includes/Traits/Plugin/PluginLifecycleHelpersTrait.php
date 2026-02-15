<?php
/**
 * PluginLifecycleHelpersTrait — shared helpers for plugin lifecycle operations.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseMessageType;

trait PluginLifecycleHelpersTrait {

    public function handlePluginExists(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            return $this->buildPluginExistsResponse($slug);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to check plugin existence: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function buildPluginExistsResponse(string $slug): WP_REST_Response {
        $pluginFile = $this->findPluginFile($slug);
        $exists = (bool) $pluginFile;
        $status = $exists ? (is_plugin_active($pluginFile) ? 'active' : 'inactive') : 'not_installed';

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . EndpointType::PluginExists->value)
            ->setSingleResult(array(
                'plugin_slug' => $slug, 'exists' => $exists, 'status' => $status,
                'plugin_file' => $exists ? $pluginFile : null,
                'requestUrl' => $_SERVER['REQUEST_URI'] ?? '', 'responseUrl' => home_url(),
            ))
            ->toResponse();
    }

    private function loadPluginFunctions(bool $includeFileFunctions = false): ?WP_REST_Response {
        try {
            if (RiseupBooleanHelpers::isFuncMissing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ($includeFileFunctions && RiseupBooleanHelpers::isFuncMissing('delete_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            return null;
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to load WordPress plugin functions: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function resolvePluginFromRequest(WP_REST_Request $request): array|WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            $pluginFile = $this->findPluginFile($slug);

            if (!$pluginFile) {
                return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
            }

            return array('slug' => $slug, 'plugin_file' => $pluginFile);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to locate plugin: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function logPluginLifecycle(string $action, string $slug, string $status, array $extra = array()): void {
        try {
            $this->logger->logPluginAction($action, $slug, $status, $extra);
        } catch (Throwable $e) {
            $this->fileLogger->warn('Failed to log plugin action', array('error' => $e->getMessage()));
        }
    }
}
