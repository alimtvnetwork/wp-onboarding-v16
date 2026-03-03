<?php
/**
 * HmacSigner — HMAC-SHA256 request signing for licensing API authentication.
 *
 * Mirrors the Go server's pkg/hmac/Signer.go signature format:
 * payload = "{timestamp}:{body}", signed with SHA256.
 *
 * @package RiseupAsia\Licensing
 * @since   2.7.0
 */

namespace RiseupAsia\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

class HmacSigner
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Sign a request body with the current timestamp.
     *
     * @param string $body    The JSON request body (empty string for GET requests).
     * @param int    $timestamp Unix timestamp (defaults to current time).
     * @return array{signature: string, timestamp: int}
     */
    public function sign(string $body = '', int $timestamp = 0): array
    {
        $ts = ($timestamp > 0) ? $timestamp : time();
        $payload = $ts . ':' . $body;
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return [
            'signature' => $signature,
            'timestamp' => $ts,
        ];
    }
}
