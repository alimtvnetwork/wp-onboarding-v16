<?php
/**
 * AgentRemoteActionTrait — Redirect resolution, connection testing, and plugin sync.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentRemoteActionTrait {

    /**
     * Resolve a redirect URL for an agent.
     */
    private function resolveRedirectUrl($agent) {
        if ($this->isRedirectCacheValid($agent)) {
            return $agent['redirect_resolved'];
        }

        $resolved = $this->followRedirectChain($agent['redirect_url']);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $this->updateAgent($agent['id'], array(
            'redirect_resolved'    => $resolved,
            'redirect_resolved_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ));

        return $resolved;
    }

    /**
     * Check if the cached redirect URL is still valid.
     */
    private function isRedirectCacheValid(array $agent): bool {
        if (empty($agent['redirect_resolved']) || empty($agent['redirect_resolved_at'])) {
            return false;
        }

        $resolved_at = strtotime($agent['redirect_resolved_at']);
        $cache_days = UPDATE_CACHE_DAYS_DEFAULT;

        return (time() < $resolved_at + ($cache_days * DAY_IN_SECONDS));
    }

    /**
     * Follow a redirect chain to find the final URL.
     */
    private function followRedirectChain(string $url, int $maxRedirects = 5) {
        for ($i = 0; $i < $maxRedirects; $i++) {
            $response = wp_remote_head($url, array(
                'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            if (!in_array($status, array(301, 302, 303, 307, 308))) {
                break;
            }

            $location = wp_remote_retrieve_header($response, 'location');
            if (!empty($location)) {
                $url = $location;
            }
        }

        return $url;
    }

    /**
     * Test connection to an agent site.
     */
    public function testConnection($agent_id) {
        $this->file_logger->info('Testing agent connection', array('id' => $agent_id));

        $result = $this->apiRequest($agent_id, 'GET', API_FULL_NAMESPACE . '/status');

        if (is_wp_error($result)) {
            return $this->handleTestConnectionFailure($agent_id, $result);
        }

        return $this->handleTestConnectionSuccess($agent_id, $result);
    }

    /** Handle a failed connection test. */
    private function handleTestConnectionFailure(int $agent_id, $error): array {
        $this->updateAgent($agent_id, array(
            'status'     => 'error',
            'last_error' => $error->get_error_message(),
        ));
        $this->logAction($agent_id, ACTION_AGENT_TEST, null, STATUS_FAILED, null, $error->get_error_message());

        return array('success' => false, 'message' => $error->get_error_message());
    }

    /** Handle a successful connection test. */
    private function handleTestConnectionSuccess(int $agent_id, $result): array {
        $this->updateAgent($agent_id, array(
            'status'     => 'connected',
            'last_sync'  => gmdate('Y-m-d\TH:i:s\Z'),
            'last_error' => null,
        ));
        $this->logAction($agent_id, ACTION_AGENT_TEST, null, STATUS_SUCCESS);

        return array('success' => true, 'message' => 'Connection successful', 'data' => $result);
    }

    /**
     * Sync plugins from an agent site.
     */
    public function syncPlugins($agent_id) {
        $this->file_logger->info('Syncing plugins from agent', array('id' => $agent_id));

        $result = $this->apiRequest($agent_id, 'GET', API_FULL_NAMESPACE . '/plugins');

        if (is_wp_error($result)) {
            $this->logAction($agent_id, ACTION_AGENT_SYNC, null, STATUS_FAILED, null, $result->get_error_message());
            return $result;
        }

        $this->updateAgent($agent_id, array(
            'status'    => 'connected',
            'last_sync' => gmdate('Y-m-d\TH:i:s\Z'),
        ));

        $plugins = isset($result['plugins']) ? $result['plugins'] : $result;
        $this->logAction($agent_id, ACTION_AGENT_SYNC, null, STATUS_SUCCESS, array('count' => count($plugins)));

        return $plugins;
    }

    /**
     * Execute an action on a plugin at an agent site.
     */
    public function executePluginAction($agent_id, $action, $slug) {
        $this->file_logger->info('Executing plugin action on agent', array(
            'agent_id' => $agent_id, 'action' => $action, 'slug' => $slug,
        ));

        $endpoint = API_FULL_NAMESPACE . '/plugins/' . urlencode($slug) . '/' . $action;
        $result = $this->apiRequest($agent_id, 'POST', $endpoint);

        if (is_wp_error($result)) {
            $this->logAction($agent_id, 'plugin_' . $action, $slug, STATUS_FAILED, null, $result->get_error_message());
            return $result;
        }

        $this->logAction($agent_id, 'plugin_' . $action, $slug, STATUS_SUCCESS);

        return array('success' => true, 'message' => ucfirst($action) . ' executed successfully', 'data' => $result);
    }
}
