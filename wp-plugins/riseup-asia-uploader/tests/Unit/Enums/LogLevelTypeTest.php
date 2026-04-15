<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\LogLevelType;

final class LogLevelTypeTest extends TestCase
{
    public function testAllLevelsExist(): void
    {
        $expected = ['Debug', 'Info', 'Warn', 'Error'];
        $actual = array_map(fn($c) => $c->value, LogLevelType::cases());

        $this->assertSame($expected, $actual);
    }

    public function testIsHelperMethods(): void
    {
        $this->assertTrue(LogLevelType::Error->isError());
        $this->assertTrue(LogLevelType::Warn->isWarn());
        $this->assertTrue(LogLevelType::Info->isInfo());
        $this->assertTrue(LogLevelType::Debug->isDebug());

        $this->assertFalse(LogLevelType::Info->isError());
        $this->assertFalse(LogLevelType::Error->isDebug());
    }

    public function testIsErrorOrWarnGroupHelper(): void
    {
        $this->assertTrue(LogLevelType::Error->isErrorOrWarn());
        $this->assertTrue(LogLevelType::Warn->isErrorOrWarn());
        $this->assertFalse(LogLevelType::Info->isErrorOrWarn());
        $this->assertFalse(LogLevelType::Debug->isErrorOrWarn());
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(LogLevelType::Error->isEqual(LogLevelType::Error));
        $this->assertFalse(LogLevelType::Error->isEqual(LogLevelType::Warn));
    }

    public function testIsOtherThanComparison(): void
    {
        $this->assertTrue(LogLevelType::Error->isOtherThan(LogLevelType::Info));
        $this->assertFalse(LogLevelType::Error->isOtherThan(LogLevelType::Error));
    }
}
