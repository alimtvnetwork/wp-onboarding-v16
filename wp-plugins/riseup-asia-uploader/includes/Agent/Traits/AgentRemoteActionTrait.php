<?php
/**
 * AgentRemoteActionTrait — Redirect resolution, connection testing, and plugin sync.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;
use RiseupAsia\Agent\AgentSite;
use RiseupAsia\Enums\AgentStatusType;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\UpdateConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\ResultHelper;

trait AgentRemoteActionTrait {

    private function resolveRedirectUrl(AgentSite $agent): string|WP_Error {
        if ($this->isRedirectCacheValid($agent)) {
            return $agent->redirectResolved;
        }

        $resolved = $this->followRedirectChain($agent->redirectUrl);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $this->updateAgent($agent->id, array(
            'redirect_resolved'    => $resolved,
            'redirect_resolved_at' => DateHelper::nowUtc(),
        ));

        return $resolved;
    }

    private function isRedirectCacheValid(AgentSite $agent): bool {
        if ($agent->isInvalidRedirect()) {
            return false;
        }

        $resolvedAt = strtotime($agent->redirectResolvedAt);
        $cacheDays = UpdateConfigType::CacheDaysDefault->value;

        return (time() < $resolvedAt + ($cacheDays * DAY_IN_SECONDS));
    }

    private function followRedirectChain(string $url, int $maxRedirects = 5): string|WP_Error {
        for ($i = 0; $i < $maxRedirects; $i++) {
            $response = wp_remote_head($url, HttpConfigType::headRedirectOptions());
            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            if (BooleanHelpers::isAbsentFromList($status, array(
                301,
                302,
                303,
                307,
                308,
            ))) {
                break;
            }

            $location = wp_remote_retrieve_header($response, 'location');
            if (BooleanHelpers::hasValue($location)) {
                $url = $location;
            }
        }

        return $url;
    }

    public function testConnection(int $agentId): array {
        $this->fileLogger->info('Testing agent connection', array('id' => $agentId));
        $result = $this->apiRequest(
            $agentId,
            HttpMethodType::Get->value,
            PluginConfigType::apiFullNamespace() . '/status',
        );

        if (is_wp_error($result)) {
            return $this->handleTestConnectionFailure($agentId, $result);
        }

        return $this->handleTestConnectionSuccess($agentId, $result);
    }

    private function handleTestConnectionFailure(int $agentId, WP_Error $error): array {
        $this->updateAgent($agentId, array(
            'status'     => AgentStatusType::Error->value,
            'last_error' => $error->get_error_message(),
        ));
        $this->logAction(
            $agentId,
            ActionType::AgentTest->value,
            null,
            StatusType::Failed->value,
            null,
            $error->get_error_message(),
        );

        return ResultHelper::failed(array(
            ResponseKeyType::Message->value => $error->get_error_message(),
        ));
    }

    private function handleTestConnectionSuccess(int $agentId, array $result): array {
        $this->updateAgent($agentId, array(
            'status'     => AgentStatusType::Connected->value,
            'last_sync'  => DateHelper::nowUtc(),
            'last_error' => null,
        ));
        $this->logAction(
            $agentId,
            ActionType::AgentTest->value,
            null,
            StatusType::Success->value,
        );

        return ResultHelper::ok(array(
            ResponseKeyType::Message->value => ResponseMessageType::ConnectionSuccessful->value,
            ResponseKeyType::Data->value    => $result,
        ));
    }

    public function syncPlugins(int $agentId): array|WP_Error {
        $this->fileLogger->info('Syncing plugins from agent', array('id' => $agentId));
        $result = $this->apiRequest(
            $agentId,
            HttpMethodType::Get->value,
            PluginConfigType::apiFullNamespace() . '/plugins',
        );

        if (is_wp_error($result)) {
            $this->logAction(
                $agentId,
                ActionType::AgentSync->value,
                null,
                StatusType::Failed->value,
                null,
                $result->get_error_message(),
            );

            return $result;
        }

        $this->updateAgent($agentId, array(
            'status'    => AgentStatusType::Connected->value,
            'last_sync' => DateHelper::nowUtc(),
        ));
        $plugins = isset($result[ResponseKeyType::Plugins->value]) ? $result[ResponseKeyType::Plugins->value] : $result;
        $this->logAction(
            $agentId,
            ActionType::AgentSync->value,
            null,
            StatusType::Success->value,
            array(ResponseKeyType::Count->value => count($plugins)),
        );

        return $plugins;
    }

    public function executePluginAction(
        int $agentId,
        string $action,
        string $slug,
    ): array|WP_Error {
        $this->fileLogger->info('Executing plugin action on agent', array(
            'agent_id' => $agentId,
            'action'   => $action,
            'slug'     => $slug,
        ));
        $endpoint = PluginConfigType::apiFullNamespace() . '/plugins/' . urlencode($slug) . '/' . $action;
        $result = $this->apiRequest(
            $agentId,
            HttpMethodType::Post->value,
            $endpoint,
        );

        if (is_wp_error($result)) {
            $this->logAction(
                $agentId,
                'plugin_' . $action,
                $slug,
                StatusType::Failed->value,
                null,
                $result->get_error_message(),
            );

            return $result;
        }

        $this->logAction(
            $agentId,
            'plugin_' . $action,
            $slug,
            StatusType::Success->value,
        );

        return ResultHelper::ok(array(
            ResponseKeyType::Message->value => ucfirst($action) . ' executed successfully',
            ResponseKeyType::Data->value    => $result,
        ));
    }
}

