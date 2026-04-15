<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Database\DbResult;
use RuntimeException;

/**
 * Tests DbResult — single-item query result wrapper.
 */
final class DbResultTest extends TestCase
{
    // ── of() — successful result ────────────────────────────

    public function testOfCreatesDefined(): void
    {
        $result = DbResult::of('hello');

        $this->assertTrue($result->isDefined());
        $this->assertFalse($result->isEmpty());
        $this->assertFalse($result->hasError());
        $this->assertTrue($result->isSafe());
        $this->assertSame('hello', $result->value());
    }

    public function testOfWithNullValueIsStillDefined(): void
    {
        $result = DbResult::of(null);

        $this->assertTrue($result->isDefined());
        $this->assertTrue($result->isSafe());
        $this->assertNull($result->value());
    }

    public function testOfWithArrayValue(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $result = DbResult::of($data);

        $this->assertSame($data, $result->value());
    }

    // ── empty() — no row found ──────────────────────────────

    public function testEmptyIsNotDefined(): void
    {
        $result = DbResult::empty();

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->isDefined());
        $this->assertFalse($result->hasError());
        $this->assertFalse($result->isSafe());
        $this->assertNull($result->value());
    }

    // ── error() — query failed ──────────────────────────────

    public function testErrorHasErrorState(): void
    {
        $exception = new RuntimeException('DB connection failed');
        $result = DbResult::error($exception);

        $this->assertTrue($result->hasError());
        $this->assertFalse($result->isDefined());
        $this->assertFalse($result->isSafe());
        $this->assertSame($exception, $result->getError());
        $this->assertNotEmpty($result->stackTrace());
    }

    public function testErrorValueIsNull(): void
    {
        $result = DbResult::error(new RuntimeException('fail'));

        $this->assertNull($result->value());
    }

    // ── Edge cases ──────────────────────────────────────────

    public function testOfWithIntegerZero(): void
    {
        $result = DbResult::of(0);

        $this->assertTrue($result->isDefined());
        $this->assertTrue($result->isSafe());
        $this->assertSame(0, $result->value());
    }

    public function testOfWithEmptyString(): void
    {
        $result = DbResult::of('');

        $this->assertTrue($result->isDefined());
        $this->assertSame('', $result->value());
    }

    public function testOfWithBooleanFalse(): void
    {
        $result = DbResult::of(false);

        $this->assertTrue($result->isDefined());
        $this->assertFalse($result->value());
    }
}
