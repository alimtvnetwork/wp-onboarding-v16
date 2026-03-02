<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Update;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Update\SelfUpdateBackupHelper;
use RiseupAsia\Logging\FileLogger;

final class SelfUpdateBackupHelperTest extends TestCase
{
    private string $fixtureDir;
    private string $upgradeDir;
    private ?SelfUpdateBackupHelper $helper = null;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/riseup-backup-test-' . uniqid();
        $this->upgradeDir = sys_get_temp_dir() . '/wp-content-test/upgrade';

        // Ensure clean state
        $this->recursiveDelete($this->fixtureDir);
        $this->recursiveDelete($this->upgradeDir);

        mkdir($this->fixtureDir, 0755, true);
        mkdir($this->upgradeDir, 0755, true);

        // Create a fake plugin directory that PathHelper::getPluginDir() will resolve to
        $pluginDir = WP_PLUGIN_DIR . '/riseup-asia-uploader';

        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }

        // Write a fake main plugin file with a version header
        file_put_contents(
            $pluginDir . '/riseup-asia-uploader.php',
            "<?php\n/**\n * Plugin Name: Riseup Asia Uploader\n * Version: 2.3.5\n */\n"
        );

        // Write a fake sub-file
        $includesDir = $pluginDir . '/includes';

        if (!is_dir($includesDir)) {
            mkdir($includesDir, 0755, true);
        }

        file_put_contents($includesDir . '/Plugin.php', "<?php\nclass Plugin {}\n");

        $this->helper = new SelfUpdateBackupHelper($this->createStubLogger());
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->fixtureDir);
        $this->recursiveDelete($this->upgradeDir);

        // Clean up the fake plugin dir
        $pluginDir = WP_PLUGIN_DIR . '/riseup-asia-uploader';
        $this->recursiveDelete($pluginDir);
    }

    // ── createBackup ────────────────────────────────────────────────

    public function testCreateBackupReturnsPath(): void
    {
        $backupDir = $this->helper->createBackup();

        $this->assertIsString($backupDir);
        $this->assertDirectoryExists($backupDir);
    }

    public function testCreateBackupCopiesFiles(): void
    {
        $backupDir = $this->helper->createBackup();

        $this->assertFileExists($backupDir . '/riseup-asia-uploader.php');
        $this->assertFileExists($backupDir . '/includes/Plugin.php');
    }

    public function testCreateBackupPreservesContent(): void
    {
        $backupDir = $this->helper->createBackup();

        $content = file_get_contents($backupDir . '/riseup-asia-uploader.php');
        $this->assertStringContainsString('Version: 2.3.5', $content);
    }

    // ── getBackupVersion ────────────────────────────────────────────

    public function testGetBackupVersionReadsVersionHeader(): void
    {
        $backupDir = $this->helper->createBackup();

        $version = $this->helper->getBackupVersion($backupDir);

        $this->assertSame('2.3.5', $version);
    }

    public function testGetBackupVersionReturnsEmptyForMissingDir(): void
    {
        $version = $this->helper->getBackupVersion('/nonexistent/path');

        $this->assertSame('', $version);
    }

    // ── rollback ────────────────────────────────────────────────────

    public function testRollbackRestoresFiles(): void
    {
        $backupDir = $this->helper->createBackup();
        $pluginDir = WP_PLUGIN_DIR . '/riseup-asia-uploader';

        // Simulate a broken update by wiping the plugin dir
        $this->recursiveDelete($pluginDir);
        $this->assertDirectoryDoesNotExist($pluginDir);

        $result = $this->helper->rollback($backupDir);

        $this->assertTrue($result);
        $this->assertDirectoryExists($pluginDir);
        $this->assertFileExists($pluginDir . '/riseup-asia-uploader.php');
        $this->assertFileExists($pluginDir . '/includes/Plugin.php');
    }

    public function testRollbackRestoresCorrectContent(): void
    {
        $backupDir = $this->helper->createBackup();
        $pluginDir = WP_PLUGIN_DIR . '/riseup-asia-uploader';

        $this->recursiveDelete($pluginDir);
        $this->helper->rollback($backupDir);

        $content = file_get_contents($pluginDir . '/riseup-asia-uploader.php');
        $this->assertStringContainsString('Version: 2.3.5', $content);
    }

    public function testRollbackCleansUpBackupDir(): void
    {
        $backupDir = $this->helper->createBackup();
        $pluginDir = WP_PLUGIN_DIR . '/riseup-asia-uploader';

        $this->recursiveDelete($pluginDir);
        $this->helper->rollback($backupDir);

        // Backup should be deleted after successful rollback
        $this->assertDirectoryDoesNotExist($backupDir);
    }

    public function testRollbackReturnsFalseForMissingBackup(): void
    {
        $result = $this->helper->rollback('/nonexistent/backup/path');

        $this->assertFalse($result);
    }

    // ── cleanup ─────────────────────────────────────────────────────

    public function testCleanupRemovesBackupDir(): void
    {
        $backupDir = $this->helper->createBackup();
        $this->assertDirectoryExists($backupDir);

        $this->helper->cleanup($backupDir);

        $this->assertDirectoryDoesNotExist($backupDir);
    }

    public function testCleanupHandlesMissingDirGracefully(): void
    {
        // Should not throw
        $this->helper->cleanup('/nonexistent/dir');
        $this->assertTrue(true);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function createStubLogger(): FileLogger
    {
        // FileLogger is a singleton with side effects; create a stub
        $stub = $this->createStub(FileLogger::class);
        $stub->method('info')->willReturn(null);
        $stub->method('warn')->willReturn(null);
        $stub->method('error')->willReturn(null);

        return $stub;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
