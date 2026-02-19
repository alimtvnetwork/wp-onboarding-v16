<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Agent;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Agent\AgentSite;

final class AgentSiteTest extends TestCase {

    private function sampleRow(): array {
        return [
            'id'                   => '42',
            'name'                 => 'Test Site',
            'url'                  => 'https://example.com',
            'username'             => 'admin',
            'redirect_url'         => 'https://redirect.example.com',
            'redirect_resolved'    => 'https://resolved.example.com',
            'redirect_resolved_at' => '2026-01-15T10:00:00Z',
            'status'               => 'connected',
            'last_sync'            => '2026-01-15T12:00:00Z',
            'last_error'           => null,
            'created_at'           => '2026-01-01T00:00:00Z',
            'updated_at'           => '2026-01-10T00:00:00Z',
        ];
    }

    public function testFromRowMapsAllProperties(): void {
        $site = AgentSite::fromRow($this->sampleRow(), 'secret123');

        $this->assertSame(42, $site->id);
        $this->assertSame('Test Site', $site->name);
        $this->assertSame('https://example.com', $site->url);
        $this->assertSame('admin', $site->username);
        $this->assertSame('https://redirect.example.com', $site->redirectUrl);
        $this->assertSame('https://resolved.example.com', $site->redirectResolved);
        $this->assertSame('2026-01-15T10:00:00Z', $site->redirectResolvedAt);
        $this->assertSame('connected', $site->status);
        $this->assertSame('2026-01-15T12:00:00Z', $site->lastSync);
        $this->assertNull($site->lastError);
        $this->assertSame('2026-01-01T00:00:00Z', $site->createdAt);
        $this->assertSame('2026-01-10T00:00:00Z', $site->updatedAt);
        $this->assertSame('secret123', $site->appPassword);
    }

    public function testFromRowHandlesMinimalRow(): void {
        $row = [
            'id'         => '1',
            'name'       => 'Minimal',
            'url'        => 'https://min.test',
            'username'   => 'user',
            'created_at' => '2026-02-01T00:00:00Z',
        ];

        $site = AgentSite::fromRow($row);

        $this->assertSame(1, $site->id);
        $this->assertNull($site->redirectUrl);
        $this->assertNull($site->redirectResolved);
        $this->assertNull($site->redirectResolvedAt);
        $this->assertSame('pending', $site->status);
        $this->assertNull($site->lastSync);
        $this->assertNull($site->lastError);
        $this->assertNull($site->updatedAt);
        $this->assertNull($site->appPassword);
    }

    public function testFromRowCastsIdToInt(): void {
        $row = $this->sampleRow();
        $row['id'] = '99';

        $site = AgentSite::fromRow($row);

        $this->assertSame(99, $site->id);
    }

    public function testToArrayExcludesPasswordWhenNull(): void {
        $site = AgentSite::fromRow($this->sampleRow());

        $arr = $site->toArray();

        $this->assertArrayNotHasKey('app_password', $arr);
        $this->assertSame(42, $arr['id']);
        $this->assertSame('https://example.com', $arr['url']);
        $this->assertSame('connected', $arr['status']);
    }

    public function testToArrayIncludesPasswordWhenSet(): void {
        $site = AgentSite::fromRow($this->sampleRow(), 'pw123');

        $arr = $site->toArray();

        $this->assertArrayHasKey('app_password', $arr);
        $this->assertSame('pw123', $arr['app_password']);
    }

    public function testToArrayUsesSnakeCaseKeys(): void {
        $site = AgentSite::fromRow($this->sampleRow());
        $arr = $site->toArray();

        $expected = ['id', 'name', 'url', 'username', 'redirect_url', 'status', 'last_sync', 'last_error', 'created_at', 'updated_at'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: {$key}");
        }
    }

    public function testToArrayOmitsRedirectResolvedFields(): void {
        $site = AgentSite::fromRow($this->sampleRow());
        $arr = $site->toArray();

        $this->assertArrayNotHasKey('redirect_resolved', $arr);
        $this->assertArrayNotHasKey('redirect_resolved_at', $arr);
    }
}
