<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Snapshot;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Snapshot\Traits\SchedulerTimingTrait;
use RiseupAsia\Enums\SnapshotFrequencyType;

final class TimingStub
{
    use SchedulerTimingTrait {
        calculateNextRunTime as public;
        getDayName as public;
        mapFrequencyToRecurrence as public;
    }
}

final class SchedulerTimingTraitTest extends TestCase
{
    private TimingStub $stub;

    protected function setUp(): void
    {
        $this->stub = new TimingStub();
    }

    public function testGetDayNameReturnsCorrectDays(): void
    {
        $this->assertSame('Monday', $this->stub->getDayName(1));
        $this->assertSame('Friday', $this->stub->getDayName(5));
        $this->assertSame('Sunday', $this->stub->getDayName(7));
    }

    public function testGetDayNameDefaultsToMondayForInvalid(): void
    {
        $this->assertSame('Monday', $this->stub->getDayName(0));
        $this->assertSame('Monday', $this->stub->getDayName(99));
    }

    public function testMapFrequencyToRecurrence(): void
    {
        $this->assertSame(SnapshotFrequencyType::Hourly->value, $this->stub->mapFrequencyToRecurrence(SnapshotFrequencyType::Hourly->value));
        $this->assertSame('daily', $this->stub->mapFrequencyToRecurrence(SnapshotFrequencyType::Daily->value));
        $this->assertSame(SnapshotFrequencyType::Weekly->value, $this->stub->mapFrequencyToRecurrence(SnapshotFrequencyType::Weekly->value));
        $this->assertSame(SnapshotFrequencyType::Monthly->value, $this->stub->mapFrequencyToRecurrence(SnapshotFrequencyType::Monthly->value));
    }

    public function testMapFrequencyDefaultsToDaily(): void
    {
        $this->assertSame('daily', $this->stub->mapFrequencyToRecurrence('unknown'));
    }

    public function testCalculateNextRunTimeReturnsTimestamp(): void
    {
        $result = $this->stub->calculateNextRunTime(SnapshotFrequencyType::Daily->value, '04:00', 1);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testCalculateNextRunTimeHourly(): void
    {
        $result = $this->stub->calculateNextRunTime(SnapshotFrequencyType::Hourly->value, '00:00', 1);

        $this->assertIsInt($result);
        $this->assertGreaterThan(time() - 10, $result);
    }

    public function testCalculateNextRunTimeMonthlyClampsDayTo28(): void
    {
        $result = $this->stub->calculateNextRunTime(SnapshotFrequencyType::Monthly->value, '03:00', 31);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
}
