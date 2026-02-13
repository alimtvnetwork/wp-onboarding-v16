<?php
/**
 * Agent Remote Operations Trait
 *
 * Remote API calls, redirect resolution, connection testing, and plugin sync.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentRemoteTrait {

    /**
     * Normalize a WordPress URL.
     */
    private function normalizeUrl($url) {
        $url = rtrim($url, '/');

        $suffixes = array('/wp-admin', '/wp-login.php', '/wp-json', '/xmlrpc.php');
        foreach ($suffixes as $suffix) {
            if (substr($url, -strlen($suffix)) === $suffix) {
                $url = substr($url, 0, -strlen($suffix));
            }
        }

        if (strpos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }

    /**
     * Build authorization header for an agent.
     */
    private function buildAuthHeader($agent) {
        $credentials = $agent['username'] . ':' . $agent['app_password'];
        return 'Basic ' . base64_encode($credentials);
    }

    /**
     * Make an API request to an agent site.
     *
     * @param int    $agent_id Agent ID.
     * @param string $method   HTTP method.
     * @param string $endpoint API endpoint (relative to /wp-json/).
     * @param array  $body     Request body (for POST/PUT).
     * @return array|WP_Error Response data or error.
     */
    public function apiRequest($agent_id, $method, $endpoint, $body = array()) {
        $agent = $this->getAgent($agent_id, true);
        if (!$agent) {
            return new WP_Error('not_found', 'Agent site not found');
        }

        $url = $this->resolveAgentBaseUrl($agent, $endpoint);
        $args = $this->buildAgentRequestArgs($agent, $method, $body);

        $this->file_logger->debug('Agent API request', array(
            'agent_id' => $agent_id, 'method' => $method, 'url' => $url,
        ));

        $response = wp_remote_request($url, $args);

        return $this->parseAgentResponse($response, $agent_id);
    }

    /**
     * Resolve the full API URL for an agent request.
     */
    private function resolveAgentBaseUrl(array $agent, string $endpoint): string {
        $base_url = $agent['url'];
        if (!empty($agent['redirect_url'])) {
            $resolved = $this->resolveRedirectUrl($agent);
            if (!is_wp_error($resolved)) {
                $base_url = $resolved;
            }
        }

        return trailingslashit($base_url) . 'wp-json/' . ltrim($endpoint, '/');
    }

    /**
     * Build request arguments for an agent API call.
     */
    private function buildAgentRequestArgs(array $agent, string $method, array $body): array {
        $args = array(
            'method'    => strtoupper($method),
            'timeout'   => 30,
            'headers'   => array(
                'Authorization' => $this->buildAuthHeader($agent),
                'Content-Type'  => 'application/json',
            ),
            'sslverify' => true,
        );

        if (!empty($body) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($body);
        }

        return $args;
    }

    /**
     * Parse the HTTP response from an agent API call.
     */
    private function parseAgentResponse($response, int $agent_id) {
        if (is_wp_error($response)) {
            $this->logAction($agent_id, 'api_error', null, 'failed', null, $response->get_error_message());

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_json = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $error_msg = isset($body_json['error']['message']) ? $body_json['error']['message'] : "HTTP {$status_code}";

            return new WP_Error('api_error', $error_msg, array('status' => $status_code, 'response' => $body_json));
        }

        return $body_json;
    }

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
            $this->updateAgent($agent_id, array(
                'status'     => 'error',
                'last_error' => $result->get_error_message(),
            ));

            $this->logAction($agent_id, ACTION_AGENT_TEST, null, STATUS_FAILED, null, $result->get_error_message());

            return array('success' => false, 'message' => $result->get_error_message());
        }

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
