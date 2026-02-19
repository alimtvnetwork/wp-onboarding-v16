<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Agent\Traits;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Agent\AgentSite;
use RiseupAsia\Agent\Traits\AgentRemoteActionTrait;

/**
 * Exposes isRedirectCacheValid for testing typed property access.
 */
final class RemoteActionStub {
    use AgentRemoteActionTrait {
        isRedirectCacheValid as public exposeIsRedirectCacheValid;
    }

    // Stub dependencies not under test.
    private function updateAgent(int $id, array $data): void {}
    private function followRedirectChain(string $url, int $max = 5): string|\WP_Error {
        return $url;
    }
}

final class AgentRemoteActionTraitTest extends TestCase {

    private RemoteActionStub $stub;

    protected function setUp(): void {
        $this->stub = new RemoteActionStub();
    }

    private function makeAgent(array $overrides = []): AgentSite {
        return AgentSite::fromRow(array_merge([
            'id'         => '1',
            'name'       => 'Test',
            'url'        => 'https://example.com',
            'username'   => 'admin',
            'created_at' => '2026-01-01T00:00:00Z',
        ], $overrides));
    }

    public function testRedirectCacheInvalidWhenNoResolved(): void {
        $agent = $this->makeAgent();

        $this->assertFalse($this->stub->exposeIsRedirectCacheValid($agent));
    }

    public function testRedirectCacheInvalidWhenNoResolvedAt(): void {
        $agent = $this->makeAgent([
            'redirect_resolved' => 'https://resolved.example.com',
        ]);

        $this->assertFalse($this->stub->exposeIsRedirectCacheValid($agent));
    }

    public function testRedirectCacheValidWhenRecent(): void {
        $agent = $this->makeAgent([
            'redirect_resolved'    => 'https://resolved.example.com',
            'redirect_resolved_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600), // 1 hour ago
        ]);

        $this->assertTrue($this->stub->exposeIsRedirectCacheValid($agent));
    }

    public function testRedirectCacheInvalidWhenExpired(): void {
        $agent = $this->makeAgent([
            'redirect_resolved'    => 'https://resolved.example.com',
            'redirect_resolved_at' => gmdate('Y-m-d\TH:i:s\Z', time() - (365 * DAY_IN_SECONDS)), // 1 year ago
        ]);

        $this->assertFalse($this->stub->exposeIsRedirectCacheValid($agent));
    }
}
