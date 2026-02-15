<?php
/**
 * ImportExecutionFileTrait — File validation, directory copy, and size utilities.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait ImportExecutionFileTrait {

    private function validateTableFiles(string $snapshotRoot, array $tables): void {
        foreach ($tables as $table) {
            $sqlitePath = RiseupPathUtils::join($snapshotRoot, $table['sqlite_file']);
            if (!RiseupPathUtils::fileExists($sqlitePath)) {
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
            $incDir = RiseupPathUtils::join($snapshotRoot, $inc['relative_path']);
            if (!RiseupPathUtils::dirExists($incDir)) {
                $this->log(LogLevelType::Warn->value, 'Incremental directory missing, skipping', array('folder' => $inc['folder_name']));
                continue;
            }
            $incFiles = glob(RiseupPathUtils::join($incDir, '*.sqlite'));
            foreach ($incFiles as $incFile) {
                $this->validateSqliteFile($incFile, basename($incFile));
            }
        }
    }

    private function validatePluginFiles(string $snapshotRoot, array $plugins): void {
        foreach ($plugins as $plugin) {
            $zipPath = RiseupPathUtils::join($snapshotRoot, $plugin['zip_file']);
            if (!RiseupPathUtils::fileExists($zipPath)) {
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
        if (!RiseupPathUtils::ensureDir($dest, false)) {
            throw new Exception("Failed to create directory: {$dest}");
        }

        $entries = scandir($src);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $srcPath = RiseupPathUtils::join($src, $entry);
            $destPath = RiseupPathUtils::join($dest, $entry);
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                if (RiseupBooleanHelpers::isCopyFailed($srcPath, $destPath)) {
                    throw new Exception("Failed to copy file: {$entry}");
                }
            }
        }
    }

    private function deleteDirectory(string $dir): void {
        if (RiseupBooleanHelpers::isDirMissing($dir)) return;
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = RiseupPathUtils::join($dir, $entry);
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
