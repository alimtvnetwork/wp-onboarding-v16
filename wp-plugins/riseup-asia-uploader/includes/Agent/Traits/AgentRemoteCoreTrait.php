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

    private function normalizeUrl(string $url): string {
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

    private function buildAuthHeader(array $agent): string {
        $credentials = $agent['username'] . ':' . $agent['app_password'];
        return 'Basic ' . base64_encode($credentials);
    }

    /** Make an API request to an agent site. */
    public function apiRequest(int $agentId, string $method, string $endpoint, array $body = array()): array|\WP_Error {
        $agent = $this->getAgent($agentId, true);
        if (!$agent) {
            return new \WP_Error('not_found', 'Agent site not found');
        }

        $url = $this->resolveAgentBaseUrl($agent, $endpoint);
        $args = $this->buildAgentRequestArgs($agent, $method, $body);

        $this->fileLogger->debug('Agent API request', array(
            'agent_id' => $agentId, 'method' => $method, 'url' => $url,
        ));

        $response = wp_remote_request($url, $args);

        return $this->parseAgentResponse($response, $agentId);
    }

    private function resolveAgentBaseUrl(array $agent, string $endpoint): string {
        $baseUrl = $agent['url'];
        if (!empty($agent['redirect_url'])) {
            $resolved = $this->resolveRedirectUrl($agent);
            if (!is_wp_error($resolved)) {
                $baseUrl = $resolved;
            }
        }

        return trailingslashit($baseUrl) . 'wp-json/' . ltrim($endpoint, '/');
    }

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

    private function parseAgentResponse(array|\WP_Error $response, int $agentId): array|\WP_Error {
        if (is_wp_error($response)) {
            $this->logAction($agentId, 'api_error', null, 'failed', null, $response->get_error_message());
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $bodyJson = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 400) {
            $errorMsg = isset($bodyJson['error']['message']) ? $bodyJson['error']['message'] : "HTTP {$statusCode}";
            return new \WP_Error('api_error', $errorMsg, array('status' => $statusCode, 'response' => $bodyJson));
        }

        return $bodyJson;
    }
}
