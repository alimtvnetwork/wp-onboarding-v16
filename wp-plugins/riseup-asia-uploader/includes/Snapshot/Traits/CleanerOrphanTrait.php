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

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\PathUtils;
use RiseupAsia\Helpers\BooleanHelpers;

trait CleanerOrphanTrait {

    private function cleanupOrphanFiles(bool $dryRun = false): array {
        $result = array('removed' => 0, 'errors' => array());

        $files = $this->db->queryAll('SELECT filepath, filename FROM ' . TableType::Snapshots->value) ?: array();
        $known_paths = array_map(function ($f) { return $f['filepath']; }, $files);

        $scan_dir = PathUtils::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');
        if (!is_dir($scan_dir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            if (in_array($path, $known_paths)) { continue; }
            if ($item->isDir()) { continue; }

            if (!$dryRun) {
                try {
                    if (@unlink($path)) {
                        $result['removed']++;
                    } else {
                        $result['errors'][] = "Failed to delete orphan file: {$path}";
                        $this->log(LogLevelType::Error->value, 'Failed to delete orphan file', array('path' => $path));
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Exception deleting orphan file: {$path} - " . $e->getMessage();
                    $this->log(LogLevelType::Error->value, 'Exception deleting orphan file', array('path' => $path, 'error' => $e->getMessage()));
                }
            } else {
                $result['removed']++;
            }
        }

        return $result;
    }

    private function cleanupOrphanSqliteFiles(bool $dryRun = false): array {
        $result = array('removed' => 0, 'errors' => array());

        $files = $this->db->queryAll('SELECT filepath, filename FROM ' . TableType::Snapshots->value) ?: array();
        $known_files = array_map(function ($f) { return $f['filename']; }, $files);

        $scan_dir = PathUtils::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');
        if (!is_dir($scan_dir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $filename = basename($path);

            if (substr($filename, -7) !== '.sqlite3') { continue; }
            if (in_array($filename, $known_files)) { continue; }

            if (!$dryRun) {
                try {
                    if (@unlink($path)) {
                        $result['removed']++;
                    } else {
                        $result['errors'][] = "Failed to delete orphan SQLite file: {$path}";
                        $this->log(LogLevelType::Error->value, 'Failed to delete orphan SQLite file', array('path' => $path));
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Exception deleting orphan SQLite file: {$path} - " . $e->getMessage();
                    $this->log(LogLevelType::Error->value, 'Exception deleting orphan SQLite file', array('path' => $path, 'error' => $e->getMessage()));
                }
            } else {
                $result['removed']++;
            }
        }

        return $result;
    }

    private function cleanupOrphanDirectories(bool $dryRun = false): array {
        $result = array('removed' => 0, 'errors' => array());

        $files = $this->db->queryAll('SELECT filepath, filename FROM ' . TableType::Snapshots->value) ?: array();
        $known_paths = array_map(function ($f) { return dirname($f['filepath']); }, $files);
        $known_paths = array_unique($known_paths);

        $scan_dir = PathUtils::trailingslashit(trailingslashit(WP_CONTENT_DIR) . defined('SNAPSHOT_DIR') ? SNAPSHOT_DIR : 'snapshots');
        if (!is_dir($scan_dir)) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan_dir, RecursiveDirectoryIterator::SKIP_DOTS),
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
            if (in_array($dir, $known_paths)) { continue; }
            if (BooleanHelpers::isDirEmpty($dir)) {
                if (!$dryRun) {
                    try {
                        if (@rmdir($dir)) {
                            $result['removed']++;
                        } else {
                            $result['errors'][] = "Failed to delete orphan directory: {$dir}";
                            $this->log(LogLevelType::Error->value, 'Failed to delete orphan directory', array('path' => $dir));
                        }
                    } catch (Throwable $e) {
                        $result['errors'][] = "Exception deleting orphan directory: {$dir} - " . $e->getMessage();
                        $this->log(LogLevelType::Error->value, 'Exception deleting orphan directory', array('path' => $dir, 'error' => $e->getMessage()));
                    }
                } else {
                    $result['removed']++;
                }
            }
        }

        return $result;
    }

    private function cleanupStuckSnapshots(bool $dryRun = false): array {
        $result = array('cleaned' => 0, 'ids' => array());

        $stuck_hours = \RiseupAsia\Enums\SnapshotConfigType::StuckHours->value;
        $cutoff = date('c', strtotime("-{$stuck_hours} hours"));

        $stuck = $this->db->queryAll(
            'SELECT id, filepath, filename, status FROM ' . TableType::Snapshots->value .
            ' WHERE status IN (?, ?, ?) AND created_at < ?',
            array(
                SnapshotStatusType::Pending->value,
                SnapshotStatusType::Running->value,
                SnapshotStatusType::Failed->value,
                $cutoff
            )
        ) ?: array();

        foreach ($stuck as $snapshot) {
            $result['ids'][] = (int) $snapshot['id'];

            if (!$dryRun) {
                $this->db->execute(
                    'UPDATE ' . TableType::Snapshots->value . ' SET status = ?, error = ? WHERE id = ?',
                    array(
                        SnapshotStatusType::Failed->value,
                        "Auto-cleaned: stuck for >{$stuck_hours} hours",
                        $snapshot['id']
                    )
                );

                $this->log(LogLevelType::Warn->value, 'Stuck snapshot marked as failed', array(
                    'id'     => $snapshot['id'],
                    'status' => $snapshot['status'],
                ));
            }

            $result['cleaned']++;
        }

        return $result;
    }
}
