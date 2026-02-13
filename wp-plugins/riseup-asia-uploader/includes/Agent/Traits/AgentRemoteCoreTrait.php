<?php
/**
 * AgentRemoteCoreTrait — URL normalization, auth, and API request mechanics.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;

trait AgentRemoteCoreTrait {

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

        if (!empty($body) && in_array($method, array(HttpMethodType::Post->value, HttpMethodType::Put->value, HttpMethodType::Patch->value))) {
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
}
