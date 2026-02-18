<?php
/**
 * PluginLifecycleEnableTrait — Enable and disable plugin REST handlers.
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
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait PluginLifecycleEnableTrait {

    /** Handle enable (activate) plugin request. */
    public function handleEnablePlugin($request) {
        $loadError = $this->loadPluginFunctions();
        if ($loadError) {
            return $loadError;
        }

        $resolved = $this->resolvePluginFromRequest($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        if (is_plugin_active($resolved['plugin_file'])) {
            return $this->buildAlreadyActiveResponse($resolved['slug']);
        }

        return $this->tryActivatePlugin($resolved['slug'], $resolved['plugin_file']);
    }

    /** Handle disable (deactivate) plugin request. */
    public function handleDisablePlugin($request) {
        $loadError = $this->loadPluginFunctions();
        if ($loadError) {
            return $loadError;
        }

        $resolved = $this->resolvePluginFromRequest($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $isPluginInactive = (is_plugin_active($resolved['plugin_file']) === false);
        if ($isPluginInactive) {
            return $this->buildAlreadyInactiveResponse($resolved['slug']);
        }

        return $this->tryDeactivatePlugin($resolved['slug'], $resolved['plugin_file']);
    }

    /** Build response for already-active plugin. */
    private function buildAlreadyActiveResponse(string $slug): WP_REST_Response {
        return EnvelopeBuilder::success('Plugin was already active')
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::PluginEnable->route())
            ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
            ->toResponse();
    }

    /** Build response for already-inactive plugin. */
    private function buildAlreadyInactiveResponse(string $slug): WP_REST_Response {
        return EnvelopeBuilder::success('Plugin was already inactive')
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::PluginDisable->route())
            ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
            ->toResponse();
    }

    /** Attempt to activate a plugin. */
    private function tryActivatePlugin(string $slug, string $plugin_file) {
        try {
            $result = activate_plugin($plugin_file);
            if (is_wp_error($result)) {
                $this->logPluginLifecycle(ActionType::Enable->value, $slug, StatusType::Failed->value, array('error' => $result->get_error_message()));

                return $this->errorResponse(ResponseMessageType::ActivationFailed->value . ': ' . $result->get_error_message(), HttpStatusType::ServerError->value);
            }
        } catch (Throwable $e) {
            $this->logPluginLifecycle(ActionType::Enable->value, $slug, StatusType::Failed->value, array('exception' => $e->getMessage()));

            return $this->errorResponse('Exception during activation: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        $this->logPluginLifecycle(ActionType::Enable->value, $slug, StatusType::Success->value);
        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::PluginEnable->route())
            ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
            ->toResponse();
    }

    /** Attempt to deactivate a plugin. */
    private function tryDeactivatePlugin(string $slug, string $plugin_file) {
        try {
            deactivate_plugins($plugin_file);
        } catch (Throwable $e) {
            $this->logPluginLifecycle(ActionType::Disable->value, $slug, StatusType::Failed->value, array('exception' => $e->getMessage()));

            return $this->errorResponse('Exception during deactivation: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        if (is_plugin_active($plugin_file)) {
            $this->logPluginLifecycle(ActionType::Disable->value, $slug, StatusType::Failed->value, array('error' => 'Plugin remained active'));

            return $this->errorResponse(ResponseMessageType::DeactivationFailed->value . ': Plugin remained active', HttpStatusType::ServerError->value);
        }

        $this->logPluginLifecycle(ActionType::Disable->value, $slug, StatusType::Success->value);
        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::PluginDisable->route())
            ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
            ->toResponse();
    }
}
