<?php
/**
 * Token Encryption class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardTokenEncryption
 *
 * Handles token encryption and decryption.
 */
class OnboardTokenEncryption {

    /**
     * Encryption method.
     */
    const CIPHER_METHOD = 'aes-256-cbc';

    /**
     * Encrypt a string.
     *
     * @param string $plaintext Text to encrypt.
     * @return string Encrypted text.
     */
    public static function encrypt($plaintext) {
        // Use constant directly as fail-safe (config may not be initialized yet).
        $encryption_enabled = defined('ONBOARD_TOKEN_ENCRYPTION') ? ONBOARD_TOKEN_ENCRYPTION : true;
        if (!$encryption_enabled) {
            return $plaintext;
        }

        $key = self::get_encryption_key();
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string.
     *
     * @param string $ciphertext Encrypted text.
     * @return string Decrypted text.
     */
    public static function decrypt($ciphertext) {
        // Use constant directly as fail-safe (config may not be initialized yet).
        $encryption_enabled = defined('ONBOARD_TOKEN_ENCRYPTION') ? ONBOARD_TOKEN_ENCRYPTION : true;
        if (!$encryption_enabled) {
            return $ciphertext;
        }

        $key = self::get_encryption_key();
        $data = base64_decode($ciphertext);
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        return openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Get encryption key.
     *
     * @return string
     */
    private static function get_encryption_key() {
        // Use WordPress AUTH_KEY and site URL to generate a unique key.
        $base = defined('AUTH_KEY') ? AUTH_KEY : 'onboard-default-key';
        $fallback_url = defined('ONBOARD_DEMO_WEBSITE') ? ONBOARD_DEMO_WEBSITE : 'https://riseup-asia.com';
        $site_url = get_option('siteurl', $fallback_url);
        
        return hash_hmac('sha256', $site_url, $base, true);
    }

    /**
     * Generate a secure random token.
     *
     * @param int $length Byte length (default 32).
     * @return string Hex-encoded token.
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash a password or secret.
     *
     * @param string $password Password to hash.
     * @return string Hashed password.
     */
    public static function hash_secret($password) {
        return password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
    }

    /**
     * Verify a password against a hash.
     *
     * @param string $password Password to verify.
     * @param string $hash     Hash to verify against.
     * @return bool
     */
    public static function verify_secret($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate a JWT token.
     *
     * @param array $payload Token payload.
     * @param int   $ttl     Time to live in seconds.
     * @return string JWT token.
     */
    public static function generate_jwt($payload, $ttl = 3600) {
        $header = array(
            'typ' => 'JWT',
            'alg' => 'HS256',
        );

        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;

        $header_encoded = self::base64_url_encode(json_encode($header));
        $payload_encoded = self::base64_url_encode(json_encode($payload));

        $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, self::get_jwt_key(), true);
        $signature_encoded = self::base64_url_encode($signature);

        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    /**
     * Verify and decode a JWT token.
     *
     * @param string $token JWT token.
     * @return array|false Decoded payload or false.
     */
    public static function verify_jwt($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

        $expected_signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, self::get_jwt_key(), true);
        $expected_signature_encoded = self::base64_url_encode($expected_signature);

        if (!hash_equals($expected_signature_encoded, $signature_encoded)) {
            return false;
        }

        $payload = json_decode(self::base64_url_decode($payload_encoded), true);

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /**
     * Get JWT signing key.
     *
     * @return string
     */
    private static function get_jwt_key() {
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : 'onboard-jwt-key';
        $secure_auth_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'onboard-secure-key';
        $fallback_url = defined('ONBOARD_DEMO_WEBSITE') ? ONBOARD_DEMO_WEBSITE : 'https://riseup-asia.com';
        $site_url = get_option('siteurl', $fallback_url);
        
        return hash_hmac('sha256', $site_url, $auth_key . $secure_auth_key, true);
    }

    /**
     * Base64 URL-safe encode.
     *
     * @param string $data Data to encode.
     * @return string
     */
    private static function base64_url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode.
     *
     * @param string $data Data to decode.
     * @return string
     */
    private static function base64_url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
