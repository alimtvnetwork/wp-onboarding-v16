<?php
/**
 * AdminMenuEnqueueAgentsTrait — Agent Sites page asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\AgentStatusType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;

trait AdminMenuEnqueueAgentsTrait {

    /** Enqueue Agent Sites page assets. */
    private function enqueueAgentsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-shared', plugins_url('assets/css/admin-shared.css', $pluginFile), [], $version);
        wp_enqueue_style('riseup-admin-agents', plugins_url('assets/css/admin-agents.css', $pluginFile), ['riseup-admin-shared'], $version);
        wp_enqueue_script('riseup-admin-agents', plugins_url('assets/js/admin-agents.js', $pluginFile), ['jquery'], $version, true);

        wp_localize_script('riseup-admin-agents', 'RiseupAgents', [
            'apiBase'       => esc_url(rest_url(PluginConfigType::apiFullNamespace())),
            'nonce'         => wp_create_nonce(NonceType::WpRest->value),
            'endpoints'     => [
                'agents'        => EndpointType::Agents->value,
                'agentsAdd'     => EndpointType::AgentsAdd->value,
                'agentsRemove'  => EndpointType::AgentsRemove->value,
                'agentsTest'    => EndpointType::AgentsTest->value,
                'agentsSync'    => EndpointType::AgentsSync->value,
                'agentsPlugins' => EndpointType::AgentsPlugins->value,
                'agentAction'   => EndpointType::AgentAction->value,
                'agentHistory'  => EndpointType::AgentHistory->value,
            ],
            'agentStatus'   => [
                'pending'   => AgentStatusType::Pending->value,
                'connected' => AgentStatusType::Connected->value,
                'error'     => AgentStatusType::Error->value,
            ],
            'status'        => [
                'success' => StatusType::Success->value,
            ],
            'responseKeys'  => [
                'agents'  => ResponseKeyType::Agents->value,
                'actions' => ResponseKeyType::Actions->value,
                'plugins' => ResponseKeyType::Plugins->value,
                'count'   => ResponseKeyType::Count->value,
                'message' => ResponseKeyType::Message->value,
                'success' => ResponseKeyType::Success->value,
                'error'   => ResponseKeyType::Error->value,
            ],
            'pluginStatus'  => [
                'active' => __('active', $pluginSlug),
            ],
            'pluginActions' => [
                'enable'  => strtolower(ActionType::Enable->value),
                'disable' => strtolower(ActionType::Disable->value),
                'delete_' => strtolower(ActionType::Delete->value),
            ],
            'i18n'          => [
                'active'              => __('Active', $pluginSlug),
                'inactive'            => __('Inactive', $pluginSlug),
                'enable'              => __('Enable', $pluginSlug),
                'disable'             => __('Disable', $pluginSlug),
                'deleteBtn'           => __('Delete', $pluginSlug),
                'noPluginsFound'      => __('No plugins found', $pluginSlug),
                'failedLoadPlugins'   => __('Failed to load plugins', $pluginSlug),
                'confirmDeletePlugin' => __('Are you sure you want to delete this plugin from the remote site?', $pluginSlug),
                'noActionHistory'     => __('No action history', $pluginSlug),
                'failedLoadHistory'   => __('Failed to load history', $pluginSlug),
                'confirmRemoveAgent'  => __('Remove agent site "%s"? This cannot be undone.', $pluginSlug),
                'connectionSuccess'   => __('Connection successful!', $pluginSlug),
                'connectionFailed'    => __('Connection failed:', $pluginSlug),
                'testFailed'          => __('Test failed:', $pluginSlug),
                'synced'              => __('Synced %d plugins', $pluginSlug),
                'syncFailed'          => __('Sync failed:', $pluginSlug),
                'actionFailed'        => __('Action failed:', $pluginSlug),
                'failedToRemove'      => __('Failed to remove:', $pluginSlug),
                'failedToLoadAgents'  => __('Failed to load agents:', $pluginSlug),
                'failedToAddAgent'    => __('Failed to add agent', $pluginSlug),
                'unknownError'        => __('Unknown error', $pluginSlug),
                'pluginsSuffix'       => __('Plugins', $pluginSlug),
                'historySuffix'       => __('Action History', $pluginSlug),
                'noAgentsYet'         => __('No agent sites registered yet.', $pluginSlug),
            ],
        ]);
    }
}
