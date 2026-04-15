<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\PathConfigType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\PathLogFileType;
use RiseupAsia\Enums\PathSubdirType;

final class PathEnumsTest extends TestCase
{
    public function testPathConfigValuesAreNonEmpty(): void
    {
        foreach (PathConfigType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }

    public function testPathDatabaseValuesEndWithDb(): void
    {
        foreach (PathDatabaseType::cases() as $case) {
            $this->assertStringEndsWith(
                '.db',
                $case->value,
                "Database path {$case->name} must end with .db",
            );
        }
    }

    public function testPathLogFileValuesEndWithLogOrTxt(): void
    {
        foreach (PathLogFileType::cases() as $case) {
            $endsWithLog = str_ends_with($case->value, '.log') || str_ends_with($case->value, '.txt');
            $this->assertTrue(
                $endsWithLog,
                "Log file path {$case->name} must end with .log or .txt, got: {$case->value}",
            );
        }
    }

    public function testPathSubdirValuesAreNonEmpty(): void
    {
        foreach (PathSubdirType::cases() as $case) {
            $this->assertNotEmpty($case->value);
        }
    }
}
