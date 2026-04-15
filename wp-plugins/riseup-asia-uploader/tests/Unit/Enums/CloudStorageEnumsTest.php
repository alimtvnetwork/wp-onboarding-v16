<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\CloudStorageAccountFieldType;
use RiseupAsia\Enums\CloudStorageBackupStatusType;
use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\CloudStorageProviderType;

final class CloudStorageEnumsTest extends TestCase
{
    public function testCloudStorageAccountFieldValuesAreNonEmpty(): void
    {
        foreach (CloudStorageAccountFieldType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testCloudStorageBackupStatusValuesAreNonEmpty(): void
    {
        foreach (CloudStorageBackupStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testCloudStorageBackupTypeValuesAreNonEmpty(): void
    {
        foreach (CloudStorageBackupType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testCloudStorageProviderValuesAreNonEmpty(): void
    {
        foreach (CloudStorageProviderType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }
}
