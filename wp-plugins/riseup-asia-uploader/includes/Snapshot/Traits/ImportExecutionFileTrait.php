<?php
/**
 * ImportExecutionFileTrait — File validation, directory copy, and size utilities.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\PathUtils;
use RiseupAsia\Helpers\BooleanHelpers;
use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

trait ImportExecutionFileTrait {

    private function validateTableFiles(string $snapshotRoot, array $tables): void {
        foreach ($tables as $table) {
            $sqlitePath = PathUtils::join($snapshotRoot, $table['sqlite_file']);
            if (!PathUtils::fileExists($sqlitePath)) {
                throw new Exception("Missing table file: {$table['sqlite_file']}");
            }
            if (!empty($table['checksum_md5'])) {
                $actualMd5 = md5_file($sqlitePath);
                if ($actualMd5 !== $table['checksum_md5']) {
                    throw new Exception("Checksum mismatch for {$table['sqlite_file']}: expected {$table['checksum_md5']}, got {$actualMd5}");
                }
            }
            $this->validateSqliteFile($sqlitePath, $table['sqlite_file']);
        }
    }

    private function validateIncrementalFiles(string $snapshotRoot, array $incrementals): void {
        foreach ($incrementals as $inc) {
            $incDir = PathUtils::join($snapshotRoot, $inc['relative_path']);
            if (!PathUtils::dirExists($incDir)) {
                $this->log(LogLevelType::Warn->value, 'Incremental directory missing, skipping', array('folder' => $inc['folder_name']));
                continue;
            }
            $incFiles = glob(PathUtils::join($incDir, '*.sqlite'));
            foreach ($incFiles as $incFile) {
                $this->validateSqliteFile($incFile, basename($incFile));
            }
        }
    }

    private function validatePluginFiles(string $snapshotRoot, array $plugins): void {
        foreach ($plugins as $plugin) {
            $zipPath = PathUtils::join($snapshotRoot, $plugin['zip_file']);
            if (!PathUtils::fileExists($zipPath)) {
                $this->log(LogLevelType::Warn->value, 'Plugin archive missing, skipping', array('plugin' => $plugin['plugin_slug']));
                continue;
            }
            if (!empty($plugin['checksum_md5'])) {
                $actualMd5 = md5_file($zipPath);
                if ($actualMd5 !== $plugin['checksum_md5']) {
                    throw new Exception("Plugin checksum mismatch for {$plugin['plugin_slug']}");
                }
            }
        }
    }

    private function copyDirectory(string $src, string $dest): void {
        if (!PathUtils::ensureDir($dest, false)) {
            throw new Exception("Failed to create directory: {$dest}");
        }

        $entries = scandir($src);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $srcPath = PathUtils::join($src, $entry);
            $destPath = PathUtils::join($dest, $entry);
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                if (BooleanHelpers::isCopyFailed($srcPath, $destPath)) {
                    throw new Exception("Failed to copy file: {$entry}");
                }
            }
        }
    }

    private function deleteDirectory(string $dir): void {
        if (BooleanHelpers::isDirMissing($dir)) return;
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = PathUtils::join($dir, $entry);
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getDirectorySize(string $dir): int {
        $size = 0;
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($entries as $entry) {
            $size += $entry->getSize();
        }
        return $size;
    }
}
