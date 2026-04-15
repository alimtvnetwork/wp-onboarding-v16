<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Agent\Traits;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Agent\AgentSite;
use RiseupAsia\Agent\Traits\AgentRemoteActionTrait;
use RiseupAsia\Helpers\DateHelper;

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
        $base = [
            'Id'        => '1',
            'Name'      => 'Test',
            'Url'       => 'https://example.com',
            'Username'  => 'admin',
            'CreatedAt' => '2026-01-01T00:00:00Z',
        ];

        foreach ($overrides as $key => $value) {
            $pascalKey = match ($key) {
                'redirect_url'         => 'RedirectUrl',
                'redirect_resolved'    => 'RedirectResolved',
                'redirect_resolved_at' => 'RedirectResolvedAt',
                'status'               => 'Status',
                'username'             => 'Username',
                default                => $key,
            };
            $base[$pascalKey] = $value;
        }

        return AgentSite::fromRow($base);
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
            'redirect_resolved_at' => DateHelper::formatUtc(time() - 3600), // 1 hour ago
        ]);

        $this->assertTrue($this->stub->exposeIsRedirectCacheValid($agent));
    }

    public function testRedirectCacheInvalidWhenExpired(): void {
        $agent = $this->makeAgent([
            'redirect_resolved'    => 'https://resolved.example.com',
            'redirect_resolved_at' => DateHelper::formatUtc(time() - (365 * DAY_IN_SECONDS)), // 1 year ago
        ]);

        $this->assertFalse($this->stub->exposeIsRedirectCacheValid($agent));
    }
}
