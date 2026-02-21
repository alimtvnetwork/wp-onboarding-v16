<?php
/**
 * AgentRemoteCoreTrait — URL normalization, auth, and API request mechanics.
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
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AgentRemoteCoreTrait {

    private function normalizeUrl(string $url): string {
        $url = rtrim($url, '/');

        $suffixes = array(
            '/wp-admin',
            '/wp-login.php',
            '/wp-json',
            '/xmlrpc.php',
        );

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

    private function buildAuthHeader(AgentSite $agent): string {
        return 'Basic ' . base64_encode($agent->username . ':' . $agent->appPassword);
    }

    /** Make an API request to an agent site. */
    public function apiRequest(
        int $agentId,
        string $method,
        string $endpoint,
        array $body = array(),
    ): array|WP_Error {
        $agent = $this->getAgentModel($agentId, true);

        if ($agent === null) {
            return new WP_Error(WpErrorCodeType::NotFound->value, 'Agent site not found');
        }

        $url = $this->resolveAgentBaseUrl($agent, $endpoint);
        $args = $this->buildAgentRequestArgs($agent, $method, $body);

        $this->fileLogger->debug('Agent API request', array(
            'agent_id' => $agentId,
            'method' => $method,
            'url' => $url,
        ));

        $response = wp_remote_request($url, $args);

        return $this->parseAgentResponse($response, $agentId);
    }

    private function resolveAgentBaseUrl(AgentSite $agent, string $endpoint): string {
        $baseUrl = $agent->url;

        if (BooleanHelpers::hasValue($agent->redirectUrl)) {
            $resolved = $this->resolveRedirectUrl($agent);
            $isResolved = is_wp_error($resolved) === false;

            if ($isResolved) {
                $baseUrl = $resolved;
            }
        }

        return trailingslashit($baseUrl) . EndpointType::WpJson->value . ltrim($endpoint, '/');
    }

    private function buildAgentRequestArgs(
        AgentSite $agent,
        string $method,
        array $body,
    ): array {
        $args = HttpConfigType::authenticatedOptions($method, $this->buildAuthHeader($agent));

        $hasBody = BooleanHelpers::hasValue($body);
        $isBodyMethod = in_array($method, array(
            HttpMethodType::Post->value,
            HttpMethodType::Put->value,
            HttpMethodType::Patch->value,
        ));

        if ($hasBody && $isBodyMethod) {
            $args['body'] = json_encode($body);
        }

        return $args;
    }

    private function parseAgentResponse(array|WP_Error $response, int $agentId): array|WP_Error {
        if (is_wp_error($response)) {
            $this->logAction(
                $agentId,
                ActionType::AgentApiError->value,
                null,
                StatusType::Failed->value,
                null,
                $response->get_error_message(),
            );

            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $bodyJson = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 400) {
            $errorMsg = isset($bodyJson[ResponseKeyType::Error->value][ResponseKeyType::Message->value])
                ? $bodyJson[ResponseKeyType::Error->value][ResponseKeyType::Message->value]
                : "HTTP {$statusCode}";

            return new WP_Error(
                WpErrorCodeType::ApiError->value,
                $errorMsg,
                array('status' => $statusCode, 'response' => $bodyJson),
            );
        }

        return $bodyJson;
    }
}
