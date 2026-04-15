<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Sync;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Traits\Sync\SyncPushTrait;
use RiseupAsia\Enums\SyncActionType;
use RiseupAsia\Enums\SyncEntryStatusType;
use RiseupAsia\Enums\ResponseKeyType;

/**
 * Stub exposing SyncPushTrait private methods for testing.
 */
final class SyncPushStub
{
    use SyncPushTrait {
        guardSyncFile as public;
        isSyncPathTraversal as public;
        updateSyncCounters as public;
        cleanEmptyParentDirs as public;
    }

    public ?object $fileLogger = null;
    public ?object $db = null;

    public function __construct()
    {
        $this->db = new class {
            public function logTransaction(string ...$args): void {}
        };
    }

    public function errorResponse(string $msg, int $code, ?\Throwable $e = null): \WP_REST_Response
    {
        return new \WP_REST_Response(['error' => $msg], $code);
    }
}

final class SyncPushTraitTest extends TestCase
{
    private SyncPushStub $stub;
    private string $testDir;

    protected function setUp(): void
    {
        $this->stub = new SyncPushStub();
        $this->testDir = sys_get_temp_dir() . '/sync-push-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->recursiveDelete($this->testDir);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGuardReturnsNullForValidInput(): void
    {
        $ignore = new class {
            public function shouldIgnore(string $path): bool { return false; }
        };

        $result = $this->stub->guardSyncFile('test.php', SyncActionType::Replace->value, $this->testDir, $ignore);

        $this->assertNull($result);
    }

    public function testGuardReturnsSkippedForMissingPath(): void
    {
        $ignore = new class {
            public function shouldIgnore(string $path): bool { return false; }
        };

        $result = $this->stub->guardSyncFile('', SyncActionType::Replace->value, $this->testDir, $ignore);

        $this->assertNotNull($result);
        $this->assertSame(SyncEntryStatusType::Skipped->value, $result[ResponseKeyType::Status->value]);
    }

    public function testGuardReturnsSkippedForMissingAction(): void
    {
        $ignore = new class {
            public function shouldIgnore(string $path): bool { return false; }
        };

        $result = $this->stub->guardSyncFile('test.php', '', $this->testDir, $ignore);

        $this->assertNotNull($result);
        $this->assertSame(SyncEntryStatusType::Skipped->value, $result[ResponseKeyType::Status->value]);
    }

    public function testGuardReturnsIgnoredForIgnoredFile(): void
    {
        $ignore = new class {
            public function shouldIgnore(string $path): bool { return true; }
        };

        $result = $this->stub->guardSyncFile('vendor/autoload.php', SyncActionType::Replace->value, $this->testDir, $ignore);

        $this->assertNotNull($result);
        $this->assertSame(SyncEntryStatusType::Ignored->value, $result[ResponseKeyType::Status->value]);
    }

    public function testPathTraversalDetectedForReplaceAction(): void
    {
        $isTraversal = $this->stub->isSyncPathTraversal('/etc/passwd', $this->testDir, SyncActionType::Replace->value);

        $this->assertTrue($isTraversal);
    }

    public function testNoPathTraversalForValidPath(): void
    {
        // Create a subdirectory so realpath works
        $subDir = $this->testDir . '/includes';
        mkdir($subDir, 0755, true);

        $isTraversal = $this->stub->isSyncPathTraversal($subDir . '/file.php', $this->testDir, SyncActionType::Replace->value);

        $this->assertFalse($isTraversal);
    }

    public function testUpdateSyncCountersForSuccess(): void
    {
        $counters = [
            ResponseKeyType::FilesUpdated->value => 0,
            ResponseKeyType::FilesDeleted->value => 0,
            ResponseKeyType::FilesIgnored->value => 0,
        ];
        $ignored = [];

        $entry = [
            'status' => SyncEntryStatusType::Success->value,
            'action' => SyncActionType::Replace->value,
            ResponseKeyType::Path->value => 'test.php',
        ];

        $this->stub->updateSyncCounters($entry, $counters, $ignored);

        $this->assertSame(1, $counters[ResponseKeyType::FilesUpdated->value]);
        $this->assertSame(0, $counters[ResponseKeyType::FilesDeleted->value]);
    }

    public function testUpdateSyncCountersForIgnored(): void
    {
        $counters = [
            ResponseKeyType::FilesUpdated->value => 0,
            ResponseKeyType::FilesDeleted->value => 0,
            ResponseKeyType::FilesIgnored->value => 0,
        ];
        $ignored = [];

        $entry = [
            'status' => SyncEntryStatusType::Ignored->value,
            'action' => SyncActionType::Replace->value,
            ResponseKeyType::Path->value => 'vendor/x.php',
        ];

        $this->stub->updateSyncCounters($entry, $counters, $ignored);

        $this->assertSame(1, $counters[ResponseKeyType::FilesIgnored->value]);
        $this->assertContains('vendor/x.php', $ignored);
    }

    public function testUpdateSyncCountersForDelete(): void
    {
        $counters = [
            ResponseKeyType::FilesUpdated->value => 0,
            ResponseKeyType::FilesDeleted->value => 0,
            ResponseKeyType::FilesIgnored->value => 0,
        ];
        $ignored = [];

        $entry = [
            'status' => SyncEntryStatusType::Success->value,
            'action' => SyncActionType::Delete->value,
            ResponseKeyType::Path->value => 'old.php',
        ];

        $this->stub->updateSyncCounters($entry, $counters, $ignored);

        $this->assertSame(1, $counters[ResponseKeyType::FilesDeleted->value]);
    }

    public function testCleanEmptyParentDirs(): void
    {
        $deepDir = $this->testDir . '/a/b/c';
        mkdir($deepDir, 0755, true);
        $file = $deepDir . '/test.txt';
        file_put_contents($file, 'x');

        // Remove the file, then clean up
        unlink($file);
        $this->stub->cleanEmptyParentDirs($file, $this->testDir);

        $this->assertDirectoryDoesNotExist($this->testDir . '/a/b/c');
        $this->assertDirectoryDoesNotExist($this->testDir . '/a/b');
        $this->assertDirectoryDoesNotExist($this->testDir . '/a');
    }

    public function testCleanEmptyParentDirsStopsAtStopDir(): void
    {
        $deepDir = $this->testDir . '/keep/empty';
        mkdir($deepDir, 0755, true);
        $file = $deepDir . '/x.txt';
        file_put_contents($file, 'data');
        unlink($file);

        $this->stub->cleanEmptyParentDirs($file, $this->testDir . '/keep');

        $this->assertDirectoryExists($this->testDir . '/keep');
    }
}
