<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Database\DbExecResult;
use RuntimeException;

/**
 * Tests DbExecResult — mutation (INSERT/UPDATE/DELETE) result wrapper.
 */
final class DbExecResultTest extends TestCase
{
    // ── of() — successful mutation ──────────────────────────

    public function testOfWithAffectedRows(): void
    {
        $result = DbExecResult::of(3, 10);

        $this->assertTrue($result->isSafe());
        $this->assertFalse($result->hasError());
        $this->assertFalse($result->isEmpty());
        $this->assertSame(3, $result->affectedRows());
        $this->assertSame(10, $result->lastInsertId());
        $this->assertNull($result->getError());
        $this->assertSame('', $result->stackTrace());
    }

    public function testOfWithZeroAffectedRowsIsEmpty(): void
    {
        $result = DbExecResult::of(0);

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($result->isSafe());
        $this->assertSame(0, $result->affectedRows());
        $this->assertSame(0, $result->lastInsertId());
    }

    // ── error() — failed mutation ───────────────────────────

    public function testErrorState(): void
    {
        $exception = new RuntimeException('UNIQUE constraint failed');
        $result = DbExecResult::error($exception);

        $this->assertTrue($result->hasError());
        $this->assertFalse($result->isSafe());
        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->affectedRows());
        $this->assertSame($exception, $result->getError());
        $this->assertNotEmpty($result->stackTrace());
    }

    // ── Edge: of(1) without lastInsertId ────────────────────

    public function testOfDefaultsLastInsertIdToZero(): void
    {
        $result = DbExecResult::of(1);

        $this->assertSame(0, $result->lastInsertId());
    }
}
