<?php
/**
 * LicenseClient — HTTP client for the licensing server API.
 *
 * Handles HMAC-signed requests to validate, activate, and deactivate licenses.
 *
 * @package RiseupAsia\Licensing
 * @since   2.7.0
 */

namespace RiseupAsia\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ContentTypeValueType;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\HttpStatusType;

class LicenseClient
{
    private const REQUEST_TIMEOUT = 15;
    private const HEADER_SIGNATURE = 'X-Signature';
    private const HEADER_TIMESTAMP = 'X-Timestamp';

    private string $baseUrl;
    private HmacSigner $signer;

    public function __construct(string $baseUrl, string $hmacSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->signer = new HmacSigner($hmacSecret);
    }

    /**
     * Validate a license key.
     *
     * @return array{valid: bool, status: string, product: string, type: string, activations: int, maxActivations: int}|null
     */
    public function validate(string $licenseKey): ?array
    {
        return $this->request(
            HttpMethodType::Get,
            '/api/v1/licenses/' . $licenseKey . '/validate',
        );
    }

    /**
     * Activate a license key on a domain.
     *
     * @return array|null Activation record or null on failure.
     */
    public function activate(string $licenseKey, string $domain): ?array
    {
        return $this->request(
            HttpMethodType::Post,
            '/api/v1/licenses/' . $licenseKey . '/activate',
            ['domain' => $domain],
        );
    }

    /**
     * Deactivate a license key from a domain.
     *
     * @return array|null Response or null on failure.
     */
    public function deactivate(string $licenseKey, string $domain): ?array
    {
        return $this->request(
            HttpMethodType::Post,
            '/api/v1/licenses/' . $licenseKey . '/deactivate',
            ['domain' => $domain],
        );
    }

    /**
     * Get full license status with activations.
     *
     * @return array{license: array, activations: array}|null
     */
    public function status(string $licenseKey): ?array
    {
        return $this->request(
            HttpMethodType::Get,
            '/api/v1/licenses/' . $licenseKey . '/status',
        );
    }

    /**
     * Execute an HMAC-signed HTTP request to the licensing server.
     *
     * @param HttpMethodType $method HTTP method.
     * @param string         $path   API path (e.g. /api/v1/licenses/KEY/validate).
     * @param array|null     $body   Request body for POST requests.
     * @return array|null Decoded JSON response or null on failure.
     */
    private function request(HttpMethodType $method, string $path, ?array $body = null): ?array
    {
        $jsonBody = ($body !== null) ? wp_json_encode($body) : '';
        $hmac = $this->signer->sign($jsonBody);

        $args = [
            'method'  => $method->value,
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'Content-Type'         => ContentTypeValueType::Json->value,
                self::HEADER_SIGNATURE => $hmac['signature'],
                self::HEADER_TIMESTAMP => (string) $hmac['timestamp'],
            ],
        ];

        $hasBody = !empty($jsonBody);

        if ($hasBody) {
            $args['body'] = $jsonBody;
        }

        $url = $this->baseUrl . $path;
        $response = wp_remote_request($url, $args);

        $isWpError = is_wp_error($response);

        if ($isWpError) {
            return null;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);

        $isInvalidJson = !is_array($decoded);

        if ($isInvalidJson) {
            return null;
        }

        $decoded['_http_status'] = $statusCode;

        return $decoded;
    }
}
