<?php
/**
 * CleanerOrphanTrait — Orphan file cleanup and stuck/failed snapshot handling.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\PathHelper;

trait CleanerOrphanTrait {
    private function cleanupOrphanFiles(bool $dryRun = false): array {
        $result = array(
            ResponseKeyType::Removed->value => 0,
            ResponseKeyType::Errors->value  => array(),
        );

        $files = $this->db->queryAll('SELECT Filepath, Filename FROM ' . TableType::Snapshots->value) ?: array();
        $knownPaths = array_map(function ($f) { return $f['Filepath']; }, $files);
        $scanDir = PathHelper::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');

        if (PathHelper::isDirMissing($scanDir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', $item->getPathname());

            if (in_array($path, $knownPaths)) {
                continue;
            }

            if ($item->isDir()) {
                continue;
            }

            $isLiveRun = ($dryRun === false);

            if ($isLiveRun) {
                try {
                    if (@unlink($path)) {
                        $result[ResponseKeyType::Removed->value]++;
                    } else {
                        $result[ResponseKeyType::Errors->value][] = "Failed to delete orphan file: {$path}";
                        $this->log(LogLevelType::Error->value, 'Failed to delete orphan file', array(ResponseKeyType::Path->value => $path));
                    }
                } catch (Throwable $e) {
                    $result[ResponseKeyType::Errors->value][] = "Exception deleting orphan file: {$path} - " . $e->getMessage();
                    $this->log(LogLevelType::Error->value, 'Exception deleting orphan file', array(
                        ResponseKeyType::Path->value  => $path,
                        ResponseKeyType::Error->value => $e->getMessage(),
                    ));
                }
            } else {
                $result[ResponseKeyType::Removed->value]++;
            }
        }

        return $result;
    }

    private function cleanupOrphanSqliteFiles(bool $dryRun = false): array {
        $result = array(
            ResponseKeyType::Removed->value => 0,
            ResponseKeyType::Errors->value  => array(),
        );

        $files = $this->db->queryAll('SELECT Filepath, Filename FROM ' . TableType::Snapshots->value) ?: array();
        $knownFiles = array_map(function ($f) { return $f['Filename']; }, $files);
        $scanDir = PathHelper::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');

        if (PathHelper::isDirMissing($scanDir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $filename = basename($path);

            if (substr($filename, -7) !== '.sqlite3') {
                continue;
            }

            if (in_array($filename, $knownFiles)) {
                continue;
            }

            $isLiveRun = ($dryRun === false);

            if ($isLiveRun) {
                try {
                    if (@unlink($path)) {
                        $result[ResponseKeyType::Removed->value]++;
                    } else {
                        $result[ResponseKeyType::Errors->value][] = "Failed to delete orphan SQLite file: {$path}";
                        $this->log(LogLevelType::Error->value, 'Failed to delete orphan SQLite file', array(ResponseKeyType::Path->value => $path));
                    }
                } catch (Throwable $e) {
                    $result[ResponseKeyType::Errors->value][] = "Exception deleting orphan SQLite file: {$path} - " . $e->getMessage();
                    $this->log(LogLevelType::Error->value, 'Exception deleting orphan SQLite file', array(
                        ResponseKeyType::Path->value  => $path,
                        ResponseKeyType::Error->value => $e->getMessage(),
                    ));
                }
            } else {
                $result[ResponseKeyType::Removed->value]++;
            }
        }

        return $result;
    }

    private function cleanupOrphanDirectories(bool $dryRun = false): array {
        $result = array(
            ResponseKeyType::Removed->value => 0,
            ResponseKeyType::Errors->value  => array(),
        );

        $files = $this->db->queryAll('SELECT Filepath, Filename FROM ' . TableType::Snapshots->value) ?: array();
        $knownPaths = array_map(function ($f) { return dirname($f['Filepath']); }, $files);
        $knownPaths = array_unique($knownPaths);
        $scanDir = PathHelper::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');

        if (PathHelper::isDirMissing($scanDir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $dirs = array();

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dirs[] = str_replace('\\', '/', $item->getPathname());
            }
        }

        $dirs = array_reverse($dirs);

        foreach ($dirs as $dir) {
            if (in_array($dir, $knownPaths)) {
                continue;
            }

            if (PathHelper::isDirEmpty($dir)) {
                $isLiveRun = ($dryRun === false);

                if ($isLiveRun) {
                    try {
                        if (@rmdir($dir)) {
                            $result[ResponseKeyType::Removed->value]++;
                        } else {
                            $result[ResponseKeyType::Errors->value][] = "Failed to delete orphan directory: {$dir}";
                            $this->log(LogLevelType::Error->value, 'Failed to delete orphan directory', array(ResponseKeyType::Path->value => $dir));
                        }
                    } catch (Throwable $e) {
                        $result[ResponseKeyType::Errors->value][] = "Exception deleting orphan directory: {$dir} - " . $e->getMessage();
                        $this->log(LogLevelType::Error->value, 'Exception deleting orphan directory', array(
                            ResponseKeyType::Path->value  => $dir,
                            ResponseKeyType::Error->value => $e->getMessage(),
                        ));
                    }
                } else {
                    $result[ResponseKeyType::Removed->value]++;
                }
            }
        }

        return $result;
    }

    private function cleanupStuckSnapshots(bool $dryRun = false): array {
        $result = array(
            ResponseKeyType::Cleaned->value => 0,
            ResponseKeyType::Ids->value     => array(),
        );

        $stuckHours = SnapshotConfigType::StuckHours->value;
        $cutoff = date('c', strtotime("-{$stuckHours} hours"));

        $stuck = $this->db->queryAll(
            'SELECT Id, Filepath, Filename, Status FROM ' . TableType::Snapshots->value .
            ' WHERE Status IN (?, ?, ?) AND CreatedAt < ?',
            array(
                SnapshotStatusType::Pending->value,
                SnapshotStatusType::Running->value,
                SnapshotStatusType::Failed->value,
                $cutoff,
            )
        ) ?: array();

        foreach ($stuck as $snapshot) {
            $result[ResponseKeyType::Ids->value][] = (int) $snapshot['Id'];
            $isLiveRun = ($dryRun === false);

            if ($isLiveRun) {
                $this->db->execute(
                    'UPDATE ' . TableType::Snapshots->value . ' SET Status = ?, Error = ? WHERE Id = ?',
                    array(
                        SnapshotStatusType::Failed->value,
                        "Auto-cleaned: stuck for >{$stuckHours} hours",
                        $snapshot['Id'],
                    )
                );

                $this->log(LogLevelType::Warn->value, 'Stuck snapshot marked as failed', array(
                    'id'     => $snapshot['Id'],
                    'status' => $snapshot['Status'],
                ));
            }

            $result[ResponseKeyType::Cleaned->value]++;
        }

        return $result;
    }
}
