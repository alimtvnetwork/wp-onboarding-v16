<?php
/**
 * CloudStorageEncryptionTrait — Token encrypt/decrypt/mask helpers.
 *
 * Uses AES-256-CBC with a key derived from WordPress auth salts.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.15.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CloudStorageProviderType;

trait CloudStorageEncryptionTrait {

    /** Derive a 32-byte encryption key from WordPress auth salts. */
    private function getCloudStorageEncryptionKey(): string
    {
        return substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
    }

    /** Encrypt a plaintext token for database storage. */
    private function encryptToken(string $plain): string
    {
        $key = $this->getCloudStorageEncryptionKey();
        $iv  = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            $key,
            0,
            $iv,
        );

        return base64_encode($iv) . '::' . $encrypted;
    }

    /** Decrypt a stored token back to plaintext. */
    private function decryptToken(string $encrypted): string
    {
        $key = $this->getCloudStorageEncryptionKey();

        $parts    = explode('::', $encrypted, 2);
        $hasParts = (count($parts) === 2);

        if (!$hasParts) {
            return '';
        }

        [$ivB64, $ciphertext] = $parts;
        $iv = base64_decode($ivB64);

        $plain = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $key,
            0,
            $iv,
        );

        $isDecryptFailed = ($plain === false);

        if ($isDecryptFailed) {
            return '';
        }

        return $plain;
    }

    /** Mask a token for safe display (e.g., ghp_****xyz). */
    private function maskToken(string $provider, string $token): string
    {
        $suffix = substr($token, -3);

        $providerType = CloudStorageProviderType::from($provider);

        return match(true) {
            $providerType->isGitHub()      => 'ghp_****' . $suffix,
            $providerType->isGitLab()      => 'glpat-****' . $suffix,
            $providerType->isGoogleDrive() => 'ya29.****' . $suffix,
        };
    }
}
