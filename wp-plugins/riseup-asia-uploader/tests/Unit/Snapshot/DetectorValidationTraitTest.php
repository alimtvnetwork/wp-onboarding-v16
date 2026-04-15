<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Snapshot;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Snapshot\Traits\DetectorValidationTrait;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\StorageModeType;

final class ValidationStub
{
    use DetectorValidationTrait {
        validateSettings as public;
    }
}

final class DetectorValidationTraitTest extends TestCase
{
    private ValidationStub $stub;

    protected function setUp(): void
    {
        $this->stub = new ValidationStub();
    }

    private function makeSettings(array $overrides = []): array
    {
        $defaults = [
            SettingsKeyType::PreferredProvider->value     => SnapshotProviderType::Auto->value,
            SettingsKeyType::ScheduleFrequency->value     => SnapshotFrequencyType::Daily->value,
            SettingsKeyType::DefaultScope->value          => SnapshotScopeType::WordPress->value,
            SettingsKeyType::RetentionType->value         => RetentionType::Days->value,
            SettingsKeyType::RetentionDays->value         => 30,
            SettingsKeyType::RetentionCount->value        => 10,
            SettingsKeyType::ScheduleDay->value           => 1,
            SettingsKeyType::MaxSnapshotSizeMb->value     => 500,
            SettingsKeyType::BatchSize->value             => 1000,
            SettingsKeyType::WorkerPoolSize->value        => SnapshotConfigType::WorkerPoolDefault->value,
            SettingsKeyType::ScheduleEnabled->value       => true,
            SettingsKeyType::PreRestoreBackup->value      => true,
            SettingsKeyType::RequireRestoreConfirm->value => true,
            SettingsKeyType::ScheduleTime->value          => '03:00',
            SettingsKeyType::CustomTables->value          => [],
            SettingsKeyType::StorageMode->value           => StorageModeType::PerTable->value,
        ];

        return array_merge($defaults, $overrides);
    }

    public function testValidSettingsPassThrough(): void
    {
        $settings = $this->makeSettings();
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(SnapshotProviderType::Auto->value, $result[SettingsKeyType::PreferredProvider->value]);
        $this->assertSame(SnapshotFrequencyType::Daily->value, $result[SettingsKeyType::ScheduleFrequency->value]);
    }

    public function testInvalidProviderResetToDefault(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::PreferredProvider->value => 'invalid_provider']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(SnapshotProviderType::Auto->value, $result[SettingsKeyType::PreferredProvider->value]);
    }

    public function testInvalidFrequencyResetToDefault(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::ScheduleFrequency->value => 'every_second']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(SnapshotFrequencyType::Daily->value, $result[SettingsKeyType::ScheduleFrequency->value]);
    }

    public function testRetentionDaysClampedToMin(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::RetentionDays->value => -5]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(1, $result[SettingsKeyType::RetentionDays->value]);
    }

    public function testRetentionDaysClampedToMax(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::RetentionDays->value => 9999]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(365, $result[SettingsKeyType::RetentionDays->value]);
    }

    public function testRetentionCountClampedToMin(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::RetentionCount->value => 0]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(1, $result[SettingsKeyType::RetentionCount->value]);
    }

    public function testRetentionCountClampedToMax(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::RetentionCount->value => 500]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(100, $result[SettingsKeyType::RetentionCount->value]);
    }

    public function testScheduleDayClampedToRange(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::ScheduleDay->value => 50]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(28, $result[SettingsKeyType::ScheduleDay->value]);
    }

    public function testBooleanFieldsCastCorrectly(): void
    {
        $settings = $this->makeSettings([
            SettingsKeyType::ScheduleEnabled->value => 1,
            SettingsKeyType::PreRestoreBackup->value => 0,
        ]);
        $result = $this->stub->validateSettings($settings);

        $this->assertTrue($result[SettingsKeyType::ScheduleEnabled->value]);
        $this->assertFalse($result[SettingsKeyType::PreRestoreBackup->value]);
    }

    public function testInvalidScheduleTimeResetToDefault(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::ScheduleTime->value => 'not-a-time']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame('03:00', $result[SettingsKeyType::ScheduleTime->value]);
    }

    public function testValidScheduleTimePreserved(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::ScheduleTime->value => '14:30']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame('14:30', $result[SettingsKeyType::ScheduleTime->value]);
    }

    public function testInvalidCustomTablesResetToArray(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::CustomTables->value => 'not-an-array']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame([], $result[SettingsKeyType::CustomTables->value]);
    }

    public function testMaxSnapshotSizeMbClamped(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::MaxSnapshotSizeMb->value => 10]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(50, $result[SettingsKeyType::MaxSnapshotSizeMb->value]);
    }

    public function testBatchSizeClamped(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::BatchSize->value => 50]);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(100, $result[SettingsKeyType::BatchSize->value]);
    }

    public function testInvalidStorageModeResetToDefault(): void
    {
        $settings = $this->makeSettings([SettingsKeyType::StorageMode->value => 'invalid_mode']);
        $result = $this->stub->validateSettings($settings);

        $this->assertSame(StorageModeType::PerTable->value, $result[SettingsKeyType::StorageMode->value]);
    }
}
