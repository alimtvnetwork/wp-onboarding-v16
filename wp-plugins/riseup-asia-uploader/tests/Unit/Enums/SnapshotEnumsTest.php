<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Enums\SnapshotPhaseType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\RestoreStrategyType;
use RiseupAsia\Enums\RetentionType;

final class SnapshotEnumsTest extends TestCase
{
    // ── SnapshotModeType ────────────────────────────────────────────

    public function testSnapshotModeHasTwoCases(): void
    {
        $this->assertCount(2, SnapshotModeType::cases());
    }

    public function testSnapshotModeIsHelpers(): void
    {
        $this->assertTrue(SnapshotModeType::Full->isFull());
        $this->assertFalse(SnapshotModeType::Full->isIncremental());
        $this->assertTrue(SnapshotModeType::Incremental->isIncremental());
    }

    // ── SnapshotScopeType ───────────────────────────────────────────

    public function testSnapshotScopeAllValuesNonEmpty(): void
    {
        foreach (SnapshotScopeType::cases() as $case) {
            $this->assertNotEmpty($case->value, "{$case->name} has empty value");
        }
    }

    // ── SnapshotFrequencyType ───────────────────────────────────────

    public function testSnapshotFrequencyValuesAreNonEmpty(): void
    {
        foreach (SnapshotFrequencyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotProviderType ────────────────────────────────────────

    public function testSnapshotProviderValuesAreNonEmpty(): void
    {
        foreach (SnapshotProviderType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotTriggerType ─────────────────────────────────────────

    public function testSnapshotTriggerValuesAreNonEmpty(): void
    {
        foreach (SnapshotTriggerType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotPhaseType ───────────────────────────────────────────

    public function testSnapshotPhaseValuesAreNonEmpty(): void
    {
        foreach (SnapshotPhaseType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotErrorType ───────────────────────────────────────────

    public function testSnapshotErrorValuesAreNonEmpty(): void
    {
        foreach (SnapshotErrorType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotJobStatusType ───────────────────────────────────────

    public function testSnapshotJobStatusValuesAreNonEmpty(): void
    {
        foreach (SnapshotJobStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotStatusType ──────────────────────────────────────────

    public function testSnapshotStatusValuesAreNonEmpty(): void
    {
        foreach (SnapshotStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── SnapshotExportStatusType ────────────────────────────────────

    public function testSnapshotExportStatusValuesAreNonEmpty(): void
    {
        foreach (SnapshotExportStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── StorageModeType ─────────────────────────────────────────────

    public function testStorageModeValuesAreNonEmpty(): void
    {
        foreach (StorageModeType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── RestoreModeType ─────────────────────────────────────────────

    public function testRestoreModeValuesAreNonEmpty(): void
    {
        foreach (RestoreModeType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── RestoreStrategyType ─────────────────────────────────────────

    public function testRestoreStrategyValuesAreNonEmpty(): void
    {
        foreach (RestoreStrategyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── RetentionType ───────────────────────────────────────────────

    public function testRetentionTypeValuesAreNonEmpty(): void
    {
        foreach (RetentionType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── Cross-enum: isEqual on all SnapshotModeType ─────────────────

    public function testSnapshotModeIsEqualComparison(): void
    {
        $this->assertTrue(SnapshotModeType::Full->isEqual(SnapshotModeType::Full));
        $this->assertFalse(SnapshotModeType::Full->isEqual(SnapshotModeType::Incremental));
    }

    // ── No duplicate values across all snapshot enums ────────────────

    public function testSnapshotModeNoDuplicateValues(): void
    {
        $this->assertNoDuplicateValues(SnapshotModeType::cases());
    }

    public function testSnapshotStatusNoDuplicateValues(): void
    {
        $this->assertNoDuplicateValues(SnapshotStatusType::cases());
    }

    private function assertNoDuplicateValues(array $cases): void
    {
        $values = array_map(fn($c) => $c->value, $cases);
        $this->assertCount(count($values), array_unique($values));
    }
}
