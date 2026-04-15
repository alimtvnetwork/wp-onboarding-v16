<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Snapshot;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Snapshot\Traits\CleanerRetentionTrait;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotModeType;

/**
 * Stub exposing retention logic with in-memory snapshot data.
 */
final class CleanerRetentionStub
{
    use CleanerRetentionTrait {
        cleanByRetention as public;
        isMasterSnapshot as public;
    }

    public array $snapshots = [];

    private object $db;

    public function __construct()
    {
        $this->db = new class {
            public array $store = [];
            public function queryAll(string $sql, array $params = []): array { return $this->store; }
            public function querySingle(string $sql, array $params = []): ?array { return ['cnt' => count($this->store)]; }
        };
    }

    public function setSnapshots(array $snapshots): void
    {
        $this->db->store = $snapshots;
    }

    private function deleteSnapshot(array $snapshot): array
    {
        return [
            ResponseKeyType::Success->value => true,
            ResponseKeyType::BytesFreed->value => $snapshot['FileSize'] ?? 0,
        ];
    }
}

final class CleanerRetentionTraitTest extends TestCase
{
    private CleanerRetentionStub $stub;

    protected function setUp(): void
    {
        $this->stub = new CleanerRetentionStub();
    }

    public function testIsMasterSnapshotReturnsTrueForFullScope(): void
    {
        $snap = ['Scope' => SnapshotModeType::Full->value];
        $this->assertTrue($this->stub->isMasterSnapshot($snap));
    }

    public function testIsMasterSnapshotReturnsFalseForNonFullScope(): void
    {
        $snap = ['Scope' => 'partial'];
        $this->assertFalse($this->stub->isMasterSnapshot($snap));
    }

    public function testIsMasterSnapshotReturnsFalseForEmptySnapshot(): void
    {
        $this->assertFalse($this->stub->isMasterSnapshot([]));
    }

    public function testCleanByRetentionWithNoneRetentionReturnsEmpty(): void
    {
        $settings = [
            SettingsKeyType::RetentionType->value  => RetentionType::None->value,
            SettingsKeyType::RetentionDays->value   => 30,
            SettingsKeyType::RetentionCount->value  => 10,
        ];

        $result = $this->stub->cleanByRetention($settings);

        $this->assertSame(0, $result[ResponseKeyType::Deleted->value]);
        $this->assertSame(0, $result[ResponseKeyType::SkippedMaster->value]);
    }

    public function testCleanByRetentionDryRunCountsButDoesNotDelete(): void
    {
        $this->stub->setSnapshots([
            ['Id' => 1, 'Filepath' => '/tmp/a', 'Filename' => 'a.zip', 'FileSize' => 1024, 'Scope' => 'partial'],
            ['Id' => 2, 'Filepath' => '/tmp/b', 'Filename' => 'b.zip', 'FileSize' => 2048, 'Scope' => 'partial'],
        ]);

        $settings = [
            SettingsKeyType::RetentionType->value  => RetentionType::Days->value,
            SettingsKeyType::RetentionDays->value   => 7,
            SettingsKeyType::RetentionCount->value  => 10,
        ];

        $result = $this->stub->cleanByRetention($settings, true);

        $this->assertSame(2, $result[ResponseKeyType::Deleted->value]);
        $this->assertSame(3072, $result[ResponseKeyType::BytesFreed->value]);
    }

    public function testCleanByRetentionSkipsMasterSnapshots(): void
    {
        $this->stub->setSnapshots([
            ['Id' => 1, 'Filepath' => '/tmp/a', 'Filename' => 'a.zip', 'FileSize' => 1024, 'Scope' => SnapshotModeType::Full->value],
        ]);

        $settings = [
            SettingsKeyType::RetentionType->value  => RetentionType::Days->value,
            SettingsKeyType::RetentionDays->value   => 7,
            SettingsKeyType::RetentionCount->value  => 10,
        ];

        $result = $this->stub->cleanByRetention($settings);

        $this->assertSame(0, $result[ResponseKeyType::Deleted->value]);
        $this->assertSame(1, $result[ResponseKeyType::SkippedMaster->value]);
    }
}
