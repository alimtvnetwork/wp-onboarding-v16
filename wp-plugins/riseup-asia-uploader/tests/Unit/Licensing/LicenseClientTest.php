<?php

namespace RiseupAsia\Tests\Unit\Licensing;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Licensing\LicenseClient;

class LicenseClientTest extends TestCase
{
    private const BASE_URL = 'https://license.test';
    private const HMAC_SECRET = 'test-client-secret';

    private LicenseClient $client;

    /** @var array[] Captured requests from the remote handler. */
    private array $capturedRequests;

    protected function setUp(): void
    {
        global $_wp_test_remote_handler;

        $this->capturedRequests = [];
        $this->client = new LicenseClient(self::BASE_URL, self::HMAC_SECRET);

        // Default handler — capture request and return valid JSON.
        $_wp_test_remote_handler = function (string $url, array $args) {
            $this->capturedRequests[] = ['url' => $url, 'args' => $args];

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['valid' => true, 'status' => 'active']),
            ];
        };
    }

    protected function tearDown(): void
    {
        global $_wp_test_remote_handler;
        $_wp_test_remote_handler = null;
    }

    // ------------------------------------------------------------------
    // HMAC header injection
    // ------------------------------------------------------------------

    public function testValidateIncludesHmacHeaders(): void
    {
        $this->client->validate('TEST-KEY');

        $this->assertCount(1, $this->capturedRequests);
        $headers = $this->capturedRequests[0]['args']['headers'];

        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertArrayHasKey('X-Timestamp', $headers);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $headers['X-Signature']);
        $this->assertIsNumeric($headers['X-Timestamp']);
    }

    public function testActivateIncludesHmacHeaders(): void
    {
        $this->client->activate('TEST-KEY', 'example.com');

        $headers = $this->capturedRequests[0]['args']['headers'];

        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertArrayHasKey('X-Timestamp', $headers);
    }

    public function testSignatureMatchesExpectedPayload(): void
    {
        $this->client->validate('MY-KEY');

        $headers = $this->capturedRequests[0]['args']['headers'];
        $ts = $headers['X-Timestamp'];

        // GET requests have empty body → payload is "{ts}:"
        $expected = hash_hmac('sha256', $ts . ':', self::HMAC_SECRET);
        $this->assertSame($expected, $headers['X-Signature']);
    }

    public function testPostRequestSignsJsonBody(): void
    {
        $this->client->activate('MY-KEY', 'example.com');

        $req = $this->capturedRequests[0];
        $headers = $req['args']['headers'];
        $body = $req['args']['body'];
        $ts = $headers['X-Timestamp'];

        $expected = hash_hmac('sha256', $ts . ':' . $body, self::HMAC_SECRET);
        $this->assertSame($expected, $headers['X-Signature']);
    }

    // ------------------------------------------------------------------
    // URL construction
    // ------------------------------------------------------------------

    public function testValidateHitsCorrectUrl(): void
    {
        $this->client->validate('ABC-123');

        $this->assertSame(
            'https://license.test/api/v1/licenses/ABC-123/validate',
            $this->capturedRequests[0]['url'],
        );
    }

    public function testActivateHitsCorrectUrl(): void
    {
        $this->client->activate('ABC-123', 'example.com');

        $this->assertSame(
            'https://license.test/api/v1/licenses/ABC-123/activate',
            $this->capturedRequests[0]['url'],
        );
    }

    public function testDeactivateHitsCorrectUrl(): void
    {
        $this->client->deactivate('ABC-123', 'example.com');

        $this->assertSame(
            'https://license.test/api/v1/licenses/ABC-123/deactivate',
            $this->capturedRequests[0]['url'],
        );
    }

    public function testStatusHitsCorrectUrl(): void
    {
        $this->client->status('ABC-123');

        $this->assertSame(
            'https://license.test/api/v1/licenses/ABC-123/status',
            $this->capturedRequests[0]['url'],
        );
    }

    public function testBaseUrlTrailingSlashStripped(): void
    {
        $client = new LicenseClient('https://license.test/', self::HMAC_SECRET);
        $client->validate('KEY');

        $this->assertStringStartsWith('https://license.test/api/', $this->capturedRequests[0]['url']);
        $this->assertStringNotContainsString('//api', $this->capturedRequests[0]['url']);
    }

    // ------------------------------------------------------------------
    // HTTP method
    // ------------------------------------------------------------------

    public function testValidateUsesGetMethod(): void
    {
        $this->client->validate('KEY');

        $this->assertSame('GET', $this->capturedRequests[0]['args']['method']);
    }

    public function testActivateUsesPostMethod(): void
    {
        $this->client->activate('KEY', 'example.com');

        $this->assertSame('POST', $this->capturedRequests[0]['args']['method']);
    }

    public function testGetRequestOmitsBody(): void
    {
        $this->client->validate('KEY');

        $this->assertArrayNotHasKey('body', $this->capturedRequests[0]['args']);
    }

    public function testPostRequestIncludesJsonBody(): void
    {
        $this->client->activate('KEY', 'example.com');

        $body = $this->capturedRequests[0]['args']['body'];
        $decoded = json_decode($body, true);

        $this->assertSame('example.com', $decoded['domain']);
    }

    // ------------------------------------------------------------------
    // JSON decoding
    // ------------------------------------------------------------------

    public function testValidResponseDecodedAsArray(): void
    {
        $result = $this->client->validate('KEY');

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertSame('active', $result['status']);
    }

    public function testHttpStatusInjectedIntoResponse(): void
    {
        $result = $this->client->validate('KEY');

        $this->assertArrayHasKey('_http_status', $result);
        $this->assertSame(200, $result['_http_status']);
    }

    public function testInvalidJsonReturnsNull(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => 'not-valid-json{{{',
        ];

        $result = $this->client->validate('KEY');

        $this->assertNull($result);
    }

    public function testEmptyBodyReturnsNull(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => '',
        ];

        $result = $this->client->validate('KEY');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // WP_Error handling
    // ------------------------------------------------------------------

    public function testWpErrorReturnsNull(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => new \WP_Error('http_request_failed', 'Connection timed out');

        $result = $this->client->validate('KEY');

        $this->assertNull($result);
    }

    public function testWpErrorOnActivateReturnsNull(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => new \WP_Error('timeout', 'Request timed out');

        $result = $this->client->activate('KEY', 'example.com');

        $this->assertNull($result);
    }

    public function testWpErrorOnDeactivateReturnsNull(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => new \WP_Error('dns_error', 'Could not resolve host');

        $result = $this->client->deactivate('KEY', 'example.com');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Non-200 status codes
    // ------------------------------------------------------------------

    public function testNon200StatusStillReturnsDecodedBody(): void
    {
        global $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 404],
            'body' => json_encode(['error' => 'not_found']),
        ];

        $result = $this->client->validate('BAD-KEY');

        $this->assertIsArray($result);
        $this->assertSame(404, $result['_http_status']);
        $this->assertSame('not_found', $result['error']);
    }

    // ------------------------------------------------------------------
    // Content-Type header
    // ------------------------------------------------------------------

    public function testContentTypeHeaderIsJson(): void
    {
        $this->client->validate('KEY');

        $headers = $this->capturedRequests[0]['args']['headers'];
        $this->assertSame('application/json', $headers['Content-Type']);
    }
}
