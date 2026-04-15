<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\StatusType;

final class StatusTypeTest extends TestCase
{
    public function testHasExactlyTwoCases(): void
    {
        $this->assertCount(2, StatusType::cases());
    }

    public function testSuccessValue(): void
    {
        $this->assertSame('Success', StatusType::Success->value);
    }

    public function testFailedValue(): void
    {
        $this->assertSame('Failed', StatusType::Failed->value);
    }

    public function testIsHelpers(): void
    {
        $this->assertTrue(StatusType::Success->isSuccess());
        $this->assertFalse(StatusType::Success->isFailed());
        $this->assertTrue(StatusType::Failed->isFailed());
        $this->assertFalse(StatusType::Failed->isSuccess());
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(StatusType::Success->isEqual(StatusType::Success));
        $this->assertFalse(StatusType::Success->isEqual(StatusType::Failed));
    }
}
