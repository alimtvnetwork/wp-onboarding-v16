<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\ColorGroupType;
use RiseupAsia\Enums\EndpointType;

use RiseupAsia\Enums\FilterKeyType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\LicenseOptionType;
use RiseupAsia\Enums\LicenseStatusType;
use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\LogColumnType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PostStatusType;
use RiseupAsia\Enums\RequestFieldType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Enums\UpdateConfigType;
use RiseupAsia\Enums\UploadSourceType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Enums\SnapshotWorkerModeType;

final class MiscEnumsTest extends TestCase
{
    // ── Endpoint & Hook ─────────────────────────────────────────────

    public function testEndpointValuesAreNonEmpty(): void
    {
        foreach (EndpointType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testHookValuesAreNonEmpty(): void
    {
        foreach (HookType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testNoDuplicateEndpoints(): void
    {
        $values = array_map(fn($c) => $c->value, EndpointType::cases());
        $this->assertCount(count($values), array_unique($values), 'EndpointType has duplicates');
    }

    public function testNoDuplicateHooks(): void
    {
        $values = array_map(fn($c) => $c->value, HookType::cases());
        $this->assertCount(count($values), array_unique($values), 'HookType has duplicates');
    }

    // ── License ─────────────────────────────────────────────────────

    public function testLicenseOptionValuesAreNonEmpty(): void
    {
        foreach (LicenseOptionType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testLicenseStatusValuesAreNonEmpty(): void
    {
        foreach (LicenseStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── Logging ─────────────────────────────────────────────────────

    public function testLogCategoryValuesAreNonEmpty(): void
    {
        foreach (LogCategoryType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testLogColumnValuesAreNonEmpty(): void
    {
        foreach (LogColumnType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    // ── Config int enums ────────────────────────────────────────────

    public function testPaginationConfigHasPositiveIntValues(): void
    {
        foreach (PaginationConfigType::cases() as $case) {
            $this->assertIsInt($case->value);
            $this->assertGreaterThan(0, $case->value);
        }
    }

    public function testSnapshotConfigHasIntValues(): void
    {
        foreach (SnapshotConfigType::cases() as $case) {
            $this->assertIsInt($case->value);
        }
    }

    public function testUpdateConfigHasIntValues(): void
    {
        foreach (UpdateConfigType::cases() as $case) {
            $this->assertIsInt($case->value);
        }
    }

    // ── Domain ──────────────────────────────────────────────────────

    public function testColorGroupValuesAreNonEmpty(): void
    {
        foreach (ColorGroupType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testFilterKeyValuesAreNonEmpty(): void
    {
        foreach (FilterKeyType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testPostStatusValuesAreNonEmpty(): void
    {
        foreach (PostStatusType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testRequestFieldValuesAreNonEmpty(): void
    {
        foreach (RequestFieldType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testResponseMessageValuesAreNonEmpty(): void
    {
        foreach (ResponseMessageType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testTableTypeValuesAreNonEmpty(): void
    {
        foreach (TableType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testTriggerSourceValuesAreNonEmpty(): void
    {
        foreach (TriggerSourceType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testUploadSourceValuesAreNonEmpty(): void
    {
        foreach (UploadSourceType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testWpErrorCodeValuesAreNonEmpty(): void
    {
        foreach (WpErrorCodeType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testSnapshotWorkerModeValuesAreNonEmpty(): void
    {
        foreach (SnapshotWorkerModeType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }
}
