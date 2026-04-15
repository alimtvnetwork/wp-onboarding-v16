<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\BackupConfigType;
use RiseupAsia\Enums\BackupScheduleType;
use RiseupAsia\Enums\BackupStatusType;
use RiseupAsia\Enums\BackupStrategyType;
use RiseupAsia\Enums\BackupType;

final class BackupEnumsTest extends TestCase
{
    public function testBackupConfigTypeHasIntValues(): void
    {
        foreach (BackupConfigType::cases() as $case) {
            $this->assertIsInt($case->value);
        }
    }

    public function testBackupScheduleValuesAreNonEmpty(): void
    {
        foreach (BackupScheduleType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testBackupStatusValuesAreNonEmpty(): void
    {
        foreach (BackupStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testBackupStrategyValuesAreNonEmpty(): void
    {
        foreach (BackupStrategyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testBackupTypeValuesAreNonEmpty(): void
    {
        foreach (BackupType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testNoDuplicateValuesInBackupStatus(): void
    {
        $values = array_map(fn($c) => $c->value, BackupStatusType::cases());
        $this->assertCount(count($values), array_unique($values));
    }
}
