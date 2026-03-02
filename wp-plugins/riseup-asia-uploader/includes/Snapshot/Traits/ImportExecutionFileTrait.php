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
use RiseupAsia\Helpers\PathHelper;

use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ZipArchive;

trait ImportExecutionFileTrait {
    private function validateTableFiles(string $snapshotRoot, array $tables): void {
        foreach ($tables as $table) {
            $sqlitePath = PathHelper::join($snapshotRoot, $table['sqliteFile']);

            if (PathHelper::isFileMissing($sqlitePath)) {
                throw new Exception("Missing table file: {$table['sqliteFile']}");
            }
            if (!empty($table['checksumMd5'])) {
                $actualMd5 = md5_file($sqlitePath);

                if ($actualMd5 !== $table['checksumMd5']) {
                    throw new Exception("Checksum mismatch for {$table['sqliteFile']}: expected {$table['checksumMd5']}, got {$actualMd5}");
                }
            }
            $this->validateSqliteFile($sqlitePath, $table['sqliteFile']);
        }
    }

    private function validateIncrementalFiles(string $snapshotRoot, array $incrementals): void {
        foreach ($incrementals as $inc) {
            $incDir = PathHelper::join($snapshotRoot, $inc['relative_path']);

            if (PathHelper::isDirMissing($incDir)) {
                $this->log(LogLevelType::Warn->value, 'Incremental directory missing, skipping', array('folder' => $inc['folder_name']));
                continue;
            }
            $incFiles = glob(PathHelper::join($incDir, '*.sqlite'));
            foreach ($incFiles as $incFile) {
                $this->validateSqliteFile($incFile, basename($incFile));
            }
        }
    }

    private function validatePluginFiles(string $snapshotRoot, array $plugins): void {
        foreach ($plugins as $plugin) {
            $zipPath = PathHelper::join($snapshotRoot, $plugin['zip_file']);

            if (PathHelper::isFileMissing($zipPath)) {
                $this->log(LogLevelType::Warn->value, 'Plugin archive missing, skipping', array('plugin' => $plugin['plugin_slug']));
                continue;
            }
            if (!empty($plugin['checksumMd5'])) {
                $actualMd5 = md5_file($zipPath);

                if ($actualMd5 !== $plugin['checksumMd5']) {
                    throw new Exception("Plugin checksum mismatch for {$plugin['pluginSlug']}");
                }
            }
        }
    }

    private function copyDirectory(string $src, string $dest): void {
        $isDirCreationFailed = (PathHelper::makeDirectory($dest, false) === false);

        if ($isDirCreationFailed) {
            throw new Exception("Failed to create directory: {$dest}");
        }

        $entries = scandir($src);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $srcPath = PathHelper::join($src, $entry);
            $destPath = PathHelper::join($dest, $entry);

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                if (PathHelper::isCopyFailed($srcPath, $destPath)) {
                    throw new Exception("Failed to copy file: {$entry}");
                }
            }
        }
    }

    private function deleteDirectory(string $dir): void {
        if (PathHelper::isDirMissing($dir)) {
            return;
        }

        $entries = scandir($dir);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = PathHelper::join($dir, $entry);

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
