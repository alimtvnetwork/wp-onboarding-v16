<?php
/**
 * PluginLifecycleDeleteTrait — Delete plugin REST handler.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait PluginLifecycleDeleteTrait {

    public function handleDeletePlugin(WP_REST_Request $request): WP_REST_Response {
        $loadError = $this->loadPluginFunctions(true);

        if ($loadError) {
            return $loadError;
        }

        $resolved = $this->resolvePluginFromRequest($request);

        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $deactivation = $this->deactivateBeforeDelete($resolved[ResponseKeyType::Slug->value], $resolved[ResponseKeyType::PluginFile->value]);

        if ($deactivation instanceof WP_REST_Response) {
            return $deactivation;
        }

        return $this->tryDeletePlugin($resolved[ResponseKeyType::Slug->value], $resolved[ResponseKeyType::PluginFile->value]);
    }

    private function deactivateBeforeDelete(string $slug, string $pluginFile): bool|WP_REST_Response {
        try {
            if (is_plugin_active($pluginFile)) {
                deactivate_plugins($pluginFile);
        }

        return true;
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to deactivate plugin before deletion: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function tryDeletePlugin(string $slug, string $pluginFile): WP_REST_Response {
        try {
            $result = delete_plugins(array($pluginFile));
            $error = $this->checkDeleteResult($result);

            if ($error) {
                $this->logPluginLifecycle(ActionType::Delete->value, $slug, StatusType::Failed->value, array('error' => $error));

                return $this->errorResponse(ResponseMessageType::DeleteFailed->value . ': ' . $error, HttpStatusType::ServerError->value);
            }
        } catch (Throwable $e) {
            $this->logPluginLifecycle(ActionType::Delete->value, $slug, StatusType::Failed->value, array('exception' => $e->getMessage()));

            return $this->errorResponse('Exception during deletion: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        $this->logPluginLifecycle(ActionType::Delete->value, $slug, StatusType::Success->value);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::PluginDelete->route())
            ->setSingleResult(array(ResponseKeyType::PluginSlug->value => $slug, ResponseKeyType::Deleted->value => true))
            ->toResponse();
    }

    private function checkDeleteResult(mixed $result): ?string {
        if (is_wp_error($result)) {
            return $result->get_error_message();
        }
        if ($result === false) {
            return 'delete_plugins returned false';
        }

        return null;
    }
}
