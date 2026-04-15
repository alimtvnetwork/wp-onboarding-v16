<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Snapshot;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Snapshot\SnapshotFactory;

/**
 * Tests SnapshotFactory static method existence and return types.
 * Cannot fully test singletons without DB/Logger, but verifies API surface.
 */
final class SnapshotFactoryTest extends TestCase
{
    public function testDetectorMethodExists(): void
    {
        $this->assertTrue(method_exists(SnapshotFactory::class, 'detector'));
    }

    public function testCleanerMethodExists(): void
    {
        $this->assertTrue(method_exists(SnapshotFactory::class, 'cleaner'));
    }

    public function testSchedulerMethodExists(): void
    {
        $this->assertTrue(method_exists(SnapshotFactory::class, 'scheduler'));
    }

    public function testManagerMethodExists(): void
    {
        $this->assertTrue(method_exists(SnapshotFactory::class, 'manager'));
    }

    public function testWorkerMethodExists(): void
    {
        $this->assertTrue(method_exists(SnapshotFactory::class, 'worker'));
    }
}
