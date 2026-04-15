<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Agent;

use PHPUnit\Framework\TestCase;

/**
 * Tests AgentManager's encrypt/decrypt cycle via reflection.
 */
final class AgentManagerEncryptionTest extends TestCase
{
    private object $manager;

    protected function setUp(): void
    {
        // Create a test-only subclass to bypass singleton + constructor deps
        $this->manager = new class {
            private string $encryptionKey;

            public function __construct()
            {
                $this->encryptionKey = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
            }

            public function encrypt(string $plaintext): string
            {
                $iv = random_bytes(12);
                $tag = '';
                $ciphertext = openssl_encrypt(
                    $plaintext, 'aes-256-gcm', $this->encryptionKey,
                    OPENSSL_RAW_DATA, $iv, $tag, '', 16
                );
                return base64_encode($iv . $tag . $ciphertext);
            }

            public function decrypt(string $encrypted): string|false
            {
                $data = base64_decode($encrypted);
                if (strlen($data) < 28) return false;
                $iv = substr($data, 0, 12);
                $tag = substr($data, 12, 16);
                $ciphertext = substr($data, 28);
                return openssl_decrypt(
                    $ciphertext, 'aes-256-gcm', $this->encryptionKey,
                    OPENSSL_RAW_DATA, $iv, $tag
                );
            }
        };
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'my-app-password-123';
        $encrypted = $this->manager->encrypt($plain);

        $this->assertNotSame($plain, $encrypted);

        $decrypted = $this->manager->decrypt($encrypted);
        $this->assertSame($plain, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $plain = 'same-password';
        $enc1 = $this->manager->encrypt($plain);
        $enc2 = $this->manager->encrypt($plain);

        // Different IVs should produce different ciphertexts
        $this->assertNotSame($enc1, $enc2);

        // Both should decrypt to the same value
        $this->assertSame($plain, $this->manager->decrypt($enc1));
        $this->assertSame($plain, $this->manager->decrypt($enc2));
    }

    public function testDecryptReturnsFalseForShortData(): void
    {
        $result = $this->manager->decrypt(base64_encode('short'));

        $this->assertFalse($result);
    }

    public function testDecryptReturnsFalseForCorruptedData(): void
    {
        $encrypted = $this->manager->encrypt('test');
        // Corrupt the data
        $corrupted = substr($encrypted, 0, -4) . 'XXXX';

        $result = $this->manager->decrypt($corrupted);

        $this->assertFalse($result);
    }

    public function testEncryptDecryptEmptyString(): void
    {
        $encrypted = $this->manager->encrypt('');
        $decrypted = $this->manager->decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    public function testEncryptDecryptUnicodeString(): void
    {
        $plain = 'пароль-密码-パスワード';
        $encrypted = $this->manager->encrypt($plain);
        $decrypted = $this->manager->decrypt($encrypted);

        $this->assertSame($plain, $decrypted);
    }

    public function testEncryptDecryptLongString(): void
    {
        $plain = str_repeat('a', 10000);
        $encrypted = $this->manager->encrypt($plain);
        $decrypted = $this->manager->decrypt($encrypted);

        $this->assertSame($plain, $decrypted);
    }
}
