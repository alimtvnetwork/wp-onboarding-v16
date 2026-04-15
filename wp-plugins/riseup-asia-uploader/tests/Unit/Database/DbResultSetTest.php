<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Database\DbResultSet;
use RuntimeException;

/**
 * Tests DbResultSet — multi-row query result wrapper.
 */
final class DbResultSetTest extends TestCase
{
    // ── of() — successful result set ────────────────────────

    public function testOfWithItems(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $result = DbResultSet::of($items);

        $this->assertFalse($result->isEmpty());
        $this->assertTrue($result->hasAny());
        $this->assertSame(2, $result->count());
        $this->assertTrue($result->isSafe());
        $this->assertFalse($result->hasError());
        $this->assertSame($items, $result->items());
    }

    public function testOfWithEmptyArray(): void
    {
        $result = DbResultSet::of([]);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->hasAny());
        $this->assertSame(0, $result->count());
        $this->assertTrue($result->isSafe());
    }

    // ── error() — query failed ──────────────────────────────

    public function testErrorState(): void
    {
        $exception = new RuntimeException('Query timeout');
        $result = DbResultSet::error($exception);

        $this->assertTrue($result->hasError());
        $this->assertFalse($result->isSafe());
        $this->assertTrue($result->isEmpty());
        $this->assertSame($exception, $result->getError());
        $this->assertNotEmpty($result->stackTrace());
    }

    // ── first() — DbResult delegation ──────────────────────

    public function testFirstReturnDefinedDbResult(): void
    {
        $result = DbResultSet::of([['id' => 42], ['id' => 99]]);
        $first = $result->first();

        $this->assertTrue($first->isDefined());
        $this->assertSame(['id' => 42], $first->value());
    }

    public function testFirstOnEmptyReturnsEmptyDbResult(): void
    {
        $result = DbResultSet::of([]);
        $first = $result->first();

        $this->assertTrue($first->isEmpty());
        $this->assertFalse($first->isDefined());
    }

    public function testFirstOnErrorReturnsErrorDbResult(): void
    {
        $exception = new RuntimeException('fail');
        $result = DbResultSet::error($exception);
        $first = $result->first();

        $this->assertTrue($first->hasError());
        $this->assertSame($exception, $first->getError());
    }
}
