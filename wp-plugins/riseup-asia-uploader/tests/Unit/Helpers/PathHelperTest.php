<?php
/**
 * PathHelperTest — Tests for PathHelper utility class.
 *
 * @package RiseupAsia\Tests\Unit\Helpers
 */

namespace RiseupAsia\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Helpers\PathHelper;

class PathHelperTest extends TestCase
{
    public function testJoinCombinesPaths(): void
    {
        $result = PathHelper::join('/var/www', 'plugins', 'my-plugin');

        $this->assertSame('/var/www/plugins/my-plugin', $result);
    }

    public function testJoinHandlesTrailingSlashes(): void
    {
        $result = PathHelper::join('/var/www/', '/plugins/', '/my-plugin');

        $this->assertSame('/var/www/plugins/my-plugin', $result);
    }

    public function testIsFileMissingWithNonexistentFile(): void
    {
        $this->assertTrue(PathHelper::isFileMissing('/nonexistent/file.txt'));
    }

    public function testIsFileMissingWithExistingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test');

        $this->assertFalse(PathHelper::isFileMissing($tempFile));

        @unlink($tempFile);
    }

    public function testIsDirMissingWithNonexistentDir(): void
    {
        $this->assertTrue(PathHelper::isDirMissing('/nonexistent/dir'));
    }

    public function testIsDirMissingWithExistingDir(): void
    {
        $this->assertFalse(PathHelper::isDirMissing(sys_get_temp_dir()));
    }

    public function testGetPluginMainFileReturnsPath(): void
    {
        $result = PathHelper::getPluginMainFile();

        $this->assertNotEmpty($result);
        $this->assertStringEndsWith('.php', $result);
    }
}
