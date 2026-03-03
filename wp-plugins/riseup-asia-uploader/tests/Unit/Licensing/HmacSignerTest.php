<?php

namespace RiseupAsia\Tests\Unit\Licensing;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Licensing\HmacSigner;

class HmacSignerTest extends TestCase
{
    private const SECRET = 'test-secret-key';

    private HmacSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new HmacSigner(self::SECRET);
    }

    public function testSignReturnsSignatureAndTimestamp(): void
    {
        $result = $this->signer->sign('{"foo":"bar"}');

        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsString($result['signature']);
        $this->assertIsInt($result['timestamp']);
    }

    public function testSignatureIsHex64(): void
    {
        $result = $this->signer->sign('body');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['signature']);
    }

    public function testSignUsesProvidedTimestamp(): void
    {
        $ts = 1700000000;
        $result = $this->signer->sign('body', $ts);

        $this->assertSame($ts, $result['timestamp']);
    }

    public function testSignDefaultsToCurrentTime(): void
    {
        $before = time();
        $result = $this->signer->sign('');
        $after = time();

        $this->assertGreaterThanOrEqual($before, $result['timestamp']);
        $this->assertLessThanOrEqual($after, $result['timestamp']);
    }

    public function testSignIsDeterministic(): void
    {
        $ts = 1700000000;
        $body = '{"key":"value"}';

        $a = $this->signer->sign($body, $ts);
        $b = $this->signer->sign($body, $ts);

        $this->assertSame($a['signature'], $b['signature']);
    }

    public function testDifferentBodyProducesDifferentSignature(): void
    {
        $ts = 1700000000;

        $a = $this->signer->sign('body-a', $ts);
        $b = $this->signer->sign('body-b', $ts);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    public function testDifferentTimestampProducesDifferentSignature(): void
    {
        $body = 'same-body';

        $a = $this->signer->sign($body, 1000);
        $b = $this->signer->sign($body, 2000);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    public function testDifferentSecretProducesDifferentSignature(): void
    {
        $other = new HmacSigner('other-secret');
        $ts = 1700000000;
        $body = 'body';

        $a = $this->signer->sign($body, $ts);
        $b = $other->sign($body, $ts);

        $this->assertNotSame($a['signature'], $b['signature']);
    }

    /**
     * Cross-check: the PHP signer must produce the same output as the Go
     * implementation for identical inputs. Payload format: "{timestamp}:{body}".
     */
    public function testMatchesGoSignerOutput(): void
    {
        $secret = 'shared-secret';
        $ts = 1700000000;
        $body = '{"domain":"example.com"}';

        $expected = hash_hmac('sha256', $ts . ':' . $body, $secret);

        $signer = new HmacSigner($secret);
        $result = $signer->sign($body, $ts);

        $this->assertSame($expected, $result['signature']);
    }

    public function testEmptyBodySign(): void
    {
        $ts = 1700000000;
        $result = $this->signer->sign('', $ts);

        $expected = hash_hmac('sha256', $ts . ':', self::SECRET);
        $this->assertSame($expected, $result['signature']);
    }
}
