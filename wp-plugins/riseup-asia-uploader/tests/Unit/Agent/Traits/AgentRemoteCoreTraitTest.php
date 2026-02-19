<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Agent\Traits;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Agent\AgentSite;
use RiseupAsia\Agent\Traits\AgentRemoteCoreTrait;

/**
 * Exposes private/protected methods from AgentRemoteCoreTrait for testing.
 */
final class RemoteCoreStub {
    use AgentRemoteCoreTrait;

    public function exposeNormalizeUrl(string $url): string {
        return $this->normalizeUrl($url);
    }

    public function exposeBuildAuthHeader(AgentSite $agent): string {
        return $this->buildAuthHeader($agent);
    }

    public function exposeBuildAgentRequestArgs(
        AgentSite $agent,
        string $method,
        array $body,
    ): array {
        return $this->buildAgentRequestArgs($agent, $method, $body);
    }

    public function exposeResolveAgentBaseUrl(AgentSite $agent, string $endpoint): string {
        return $this->resolveAgentBaseUrl($agent, $endpoint);
    }

    // Stubs for dependencies referenced by resolveAgentBaseUrl
    private function resolveRedirectUrl(AgentSite $agent): string|\WP_Error {
        return $agent->redirectResolved ?? new \WP_Error('no_resolve', 'Cannot resolve');
    }
}

final class AgentRemoteCoreTraitTest extends TestCase {

    private RemoteCoreStub $stub;

    protected function setUp(): void {
        $this->stub = new RemoteCoreStub();
    }

    private function makeAgent(array $overrides = []): AgentSite {
        return AgentSite::fromRow(array_merge([
            'id'         => '1',
            'name'       => 'Test',
            'url'        => 'https://example.com',
            'username'   => 'admin',
            'created_at' => '2026-01-01T00:00:00Z',
        ], $overrides), $overrides['_password'] ?? 'app_pass_123');
    }

    // --- normalizeUrl ---

    public function testNormalizeUrlStripsTrailingSlash(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('https://example.com/'));
    }

    public function testNormalizeUrlStripsWpAdmin(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('https://example.com/wp-admin'));
    }

    public function testNormalizeUrlStripsWpJson(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('https://example.com/wp-json'));
    }

    public function testNormalizeUrlStripsWpLoginPhp(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('https://example.com/wp-login.php'));
    }

    public function testNormalizeUrlForcesHttps(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('http://example.com'));
    }

    public function testNormalizeUrlCombinedSuffixAndHttp(): void {
        $this->assertSame('https://example.com', $this->stub->exposeNormalizeUrl('http://example.com/wp-admin'));
    }

    // --- buildAuthHeader ---

    public function testBuildAuthHeaderUsesTypedProperties(): void {
        $agent = $this->makeAgent(['username' => 'myuser']);
        $header = $this->stub->exposeBuildAuthHeader($agent);

        $this->assertStringStartsWith('Basic ', $header);
        $decoded = base64_decode(substr($header, 6));
        $this->assertSame('myuser:app_pass_123', $decoded);
    }

    // --- buildAgentRequestArgs ---

    public function testBuildAgentRequestArgsGet(): void {
        $agent = $this->makeAgent();
        $args = $this->stub->exposeBuildAgentRequestArgs($agent, 'GET', []);

        $this->assertSame('GET', $args['method']);
        $this->assertSame(30, $args['timeout']);
        $this->assertTrue($args['sslverify']);
        $this->assertArrayHasKey('Authorization', $args['headers']);
        $this->assertArrayNotHasKey('body', $args);
    }

    public function testBuildAgentRequestArgsPostWithBody(): void {
        $agent = $this->makeAgent();
        $body = ['key' => 'value'];
        $args = $this->stub->exposeBuildAgentRequestArgs($agent, 'POST', $body);

        $this->assertSame('POST', $args['method']);
        $this->assertSame('{"key":"value"}', $args['body']);
    }

    public function testBuildAgentRequestArgsGetIgnoresBody(): void {
        $agent = $this->makeAgent();
        $args = $this->stub->exposeBuildAgentRequestArgs($agent, 'GET', ['ignored' => true]);

        $this->assertArrayNotHasKey('body', $args);
    }

    // --- resolveAgentBaseUrl ---

    public function testResolveAgentBaseUrlWithoutRedirect(): void {
        $agent = $this->makeAgent();
        $url = $this->stub->exposeResolveAgentBaseUrl($agent, 'riseup/v1/status');

        $this->assertSame('https://example.com/wp-json/riseup/v1/status', $url);
    }

    public function testResolveAgentBaseUrlWithRedirect(): void {
        $agent = $this->makeAgent([
            'redirect_url'      => 'https://old.example.com',
            'redirect_resolved' => 'https://new.example.com',
        ]);
        $url = $this->stub->exposeResolveAgentBaseUrl($agent, 'riseup/v1/status');

        $this->assertSame('https://new.example.com/wp-json/riseup/v1/status', $url);
    }
}
