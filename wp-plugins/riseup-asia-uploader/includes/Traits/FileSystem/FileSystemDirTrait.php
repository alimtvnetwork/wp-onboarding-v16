<?php
/**
 * FileSystemDirTrait — directory operations, ZIP helpers, temp dir.
 *
 * @package RiseupAsia\Traits\FileSystem
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\FileSystem;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use RiseupAsia\Helpers\PathHelper;

trait FileSystemDirTrait {

    private function getTempDir(): string {
        $tempDir = PathHelper::getTempDir();
        PathHelper::ensureDir($tempDir);

        return $tempDir;
    }

    private function detectPluginSlugFromZip(ZipArchive $zip): ?string {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^([^\/]+)\/[^\/]+\.php$/', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if ($content && str_contains($content, 'Plugin Name:')) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    private function deleteDirectory(string $dir): bool {
        if (PathHelper::isDirMissing($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    private function copyDirectory(string $src, string $dst): bool {
    }

    private function copyDirectory(string $src, string $dst): bool {
        if (PathHelper::isDirMissing($src)) {
            return false;
        }

        if (PathHelper::isDirMissing($dst)) {
            wp_mkdir_p($dst);
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        return true;
    }

    private function addDirToZip(
        ZipArchive $zip,
        string $srcDir,
        string $zipDir,
        object $ignore,
    ): void {
        $dir = opendir($srcDir);
        if (!$dir) {
            return;
        }

        $zip->addEmptyDir($zipDir);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->processZipEntry($zip, $srcDir, $zipDir, $file, $ignore);
        }

        closedir($dir);
    }

    private function processZipEntry(
        ZipArchive $zip,
        string $srcDir,
        string $zipDir,
        string $file,
        object $ignore,
    ): void {
        $srcPath = $srcDir . '/' . $file;
        $zipPath = $zipDir . '/' . $file;

        $relative = str_replace($srcDir . '/', '', $srcPath);
        if ($ignore->shouldIgnore($relative)) {
            return;
        }

        if (is_dir($srcPath)) {
            $this->addDirToZip($zip, $srcPath, $zipPath, $ignore);
        } else {
            $zip->addFile($srcPath, $zipPath);
        }
    }
}
