<?php
/**
 * Riseup Asia Uploader - Snapshot Cleaner
 *
 * Handles retention policy enforcement, orphan file cleanup,
 * and storage management for database snapshots.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Cleaner class.
 * 
 * Manages cleanup of old snapshots based on retention policies,
 * removes orphan files, and provides storage statistics.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotCleaner {

    /**
     * Logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Constructor.
     *
     * @param Riseup_File_Logger $logger Logger instance.
     * @param Riseup_Database    $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Run full cleanup based on retention settings.
     *
     * @param array $settings Snapshot settings from detector.
     * @return array {
     *     Cleanup result.
     *     @type int   $deleted_by_policy   Snapshots deleted by retention policy.
     *     @type int   $deleted_orphans     Orphan files deleted.
     *     @type int   $deleted_failed      Failed/stuck snapshots deleted.
     *     @type int   $space_freed_bytes   Bytes freed.
     *     @type array $errors              Any errors encountered.
     * }
     */
    public function runCleanup($settings) {
        $this->log('INFO', 'Starting cleanup', array(
            'retention_type' => $settings['retention_type'],
        ));

        $result = array(
            'deleted_by_policy' => 0,
            'deleted_orphans' => 0,
            'deleted_failed' => 0,
            'space_freed_bytes' => 0,
            'errors' => array(),
        );

        // Skip if retention is 'none'
        if ($settings['retention_type'] === 'none') {
            $this->log('DEBUG', 'Retention policy is "none" - skipping policy cleanup');
        } else {
            // Run policy-based cleanup
            $policy_result = $this->cleanupByPolicy($settings);
            $result['deleted_by_policy'] = $policy_result['deleted'];
            $result['space_freed_bytes'] += $policy_result['bytes_freed'];
            if (!empty($policy_result['error'])) {
                $result['errors'][] = $policy_result['error'];
            }
        }

        // Clean up orphan files (files without database records)
        $orphan_result = $this->cleanupOrphanFiles();
        $result['deleted_orphans'] = $orphan_result['deleted'];
        $result['space_freed_bytes'] += $orphan_result['bytes_freed'];
        if (!empty($orphan_result['error'])) {
            $result['errors'][] = $orphan_result['error'];
        }

        // Clean up failed/stuck snapshots older than 24 hours
        $failed_result = $this->cleanupFailedSnapshots();
        $result['deleted_failed'] = $failed_result['deleted'];
        if (!empty($failed_result['error'])) {
            $result['errors'][] = $failed_result['error'];
        }

        $this->log('INFO', 'Cleanup complete', array(
            'deleted_total' => $result['deleted_by_policy'] + $result['deleted_orphans'] + $result['deleted_failed'],
            'space_freed' => RiseupPathUtils::formatBytes($result['space_freed_bytes']),
        ));

        return $result;
    }

    /**
     * Cleanup by retention policy (days or count).
     *
     * @param array $settings Snapshot settings.
     * @return array {
     *     @type int    $deleted     Number deleted.
     *     @type int    $bytes_freed Bytes freed.
     *     @type string $error       Error message if any.
     * }
     */
    private function cleanupByPolicy($settings) {
        $result = array(
            'deleted' => 0,
            'bytes_freed' => 0,
            'error' => null,
        );

        try {
            if ($settings['retention_type'] === 'days') {
                $snapshots = $this->getSnapshotsOlderThan($settings['retention_days']);
            } elseif ($settings['retention_type'] === 'count') {
                $snapshots = $this->getSnapshotsBeyondCount($settings['retention_count']);
            } else {
                return $result;
            }

            if (empty($snapshots)) {
                return $result;
            }

            foreach ($snapshots as $snapshot) {
                $delete_result = $this->deleteSnapshot($snapshot);
                if ($delete_result['success']) {
                    $result['deleted']++;
                    $result['bytes_freed'] += $delete_result['bytes_freed'];
                }
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->log('ERROR', 'Policy cleanup failed', array('error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Get snapshots older than N days.
     *
     * @param int $days Retention days.
     * @return array Snapshot records.
     */
    private function getSnapshotsOlderThan($days) {
        $cutoff = date('c', strtotime("-{$days} days"));

        return $this->db->query_all(
            'SELECT id, filepath, filename, size FROM ' . RISEUP_TABLE_SNAPSHOTS .
            ' WHERE status = ? AND created_at < ? ORDER BY created_at ASC',
            array(RISEUP_SNAPSHOT_STATUS_COMPLETE, $cutoff)
        ) ?: array();
    }

    /**
     * Get snapshots beyond the count limit.
     *
     * @param int $count Number to keep.
     * @return array Snapshot records to delete.
     */
    private function getSnapshotsBeyondCount($count) {
        $total_result = $this->db->query_single(
            'SELECT COUNT(*) as cnt FROM ' . RISEUP_TABLE_SNAPSHOTS . ' WHERE status = ?',
            array(RISEUP_SNAPSHOT_STATUS_COMPLETE)
        );

        if (!$total_result || $total_result['cnt'] <= $count) {
            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->query_all(
            'SELECT id, filepath, filename, size FROM ' . RISEUP_TABLE_SNAPSHOTS .
            ' WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            array(RISEUP_SNAPSHOT_STATUS_COMPLETE, $to_delete)
        ) ?: array();
    }

    /**
     * Delete a single snapshot (file + database record).
     * If the snapshot is a full snapshot with an incremental/ subdirectory,
     * cascade-delete all incremental children (files + DB records).
     *
     * @param array $snapshot Snapshot record.
     * @return array {
     *     @type bool $success     Whether deletion succeeded.
     *     @type int  $bytes_freed Bytes freed.
     * }
     */
    private function deleteSnapshot($snapshot) {
        $bytes_freed = 0;

        $filepath = $snapshot['filepath'];
        $is_directory = is_dir($filepath);

        // --- Cascade delete: if this is a full snapshot directory, remove incrementals first ---
        if ($is_directory) {
            $incremental_dir = $filepath . '/incremental';
            if (is_dir($incremental_dir)) {
                $inc_size = $this->getDirectorySize($incremental_dir);
                $this->deleteDirectoryRecursive($incremental_dir);
                $bytes_freed += $inc_size;

                // Delete all incremental DB records whose filepath starts with this directory
                $this->cascadeDeleteIncrementalRecords($filepath);

                $this->log('INFO', 'Cascade-deleted incremental children', array(
                    'parent_id'    => $snapshot['id'],
                    'parent_dir'   => basename($filepath),
                    'bytes_freed'  => RiseupPathUtils::formatBytes($inc_size),
                ));
            }

            // Delete the full snapshot directory
            $dir_size = $this->getDirectorySize($filepath);
            $this->deleteDirectoryRecursive($filepath);
            $bytes_freed += $dir_size;
        } else {
            // Single-file snapshot (legacy .sqlite format)
            if (RiseupPathUtils::fileExists($filepath)) {
                $bytes_freed = filesize($filepath);
                if (!RiseupPathUtils::deleteFile($filepath)) {
                    $this->log('WARN', 'Failed to delete snapshot file', array('filepath' => $filepath));
                    return array('success' => false, 'bytes_freed' => 0);
                }
            }

            // Delete ZIP if exists
            $zip_path = $this->getZipPath($filepath);
            if (RiseupPathUtils::fileExists($zip_path)) {
                $bytes_freed += filesize($zip_path);
                RiseupPathUtils::deleteFile($zip_path);
            }
        }

        // Delete database record
        $this->db->delete(RISEUP_TABLE_SNAPSHOTS, array('id' => $snapshot['id']));

        // Delete progress records
        $this->db->execute(
            'DELETE FROM ' . RISEUP_TABLE_SNAPSHOT_PROGRESS . ' WHERE snapshot_id = ?',
            array($snapshot['id'])
        );

        $this->log('DEBUG', 'Deleted snapshot', array(
            'id' => $snapshot['id'],
            'filename' => $snapshot['filename'],
            'bytes_freed' => RiseupPathUtils::formatBytes($bytes_freed),
        ));

        return array('success' => true, 'bytes_freed' => $bytes_freed);
    }

    /**
     * Cascade-delete all incremental snapshot DB records whose filepath
     * is a child of the given parent directory.
     *
     * @param string $parent_dir Parent full snapshot directory path.
     */
    private function cascadeDeleteIncrementalRecords($parent_dir) {
        try {
            // Find all incremental records under this parent
            $incrementals = $this->db->query_all(
                'SELECT id FROM ' . RISEUP_TABLE_SNAPSHOTS .
                " WHERE scope = 'incremental' AND filepath LIKE ?",
                array($parent_dir . '/incremental/%')
            ) ?: array();

            foreach ($incrementals as $inc) {
                $this->db->delete(RISEUP_TABLE_SNAPSHOTS, array('id' => $inc['id']));
                $this->db->execute(
                    'DELETE FROM ' . RISEUP_TABLE_SNAPSHOT_PROGRESS . ' WHERE snapshot_id = ?',
                    array($inc['id'])
                );
            }

            $this->log('DEBUG', 'Deleted incremental DB records', array(
                'count' => count($incrementals),
            ));
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to cascade-delete incremental records', array(
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     */
    private function deleteDirectoryRecursive($dir) {
        if (!is_dir($dir)) return;

        $items = array_diff(scandir($dir), array('.', '..'));
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Get total size of a directory recursively.
     *
     * @param string $dir Directory path.
     * @return int Total size in bytes.
     */
    private function getDirectorySize($dir) {
        $size = 0;
        if (!is_dir($dir)) return 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    /**
     * Cleanup orphan files (files without database records).
     *
     * @return array {
     *     @type int    $deleted     Number deleted.
     *     @type int    $bytes_freed Bytes freed.
     *     @type string $error       Error message if any.
     * }
     */
    private function cleanupOrphanFiles() {
        $result = array(
            'deleted' => 0,
            'bytes_freed' => 0,
            'error' => null,
        );

        try {
            $snapshots_dir = RiseupPathUtils::getSnapshotsDir();

            if (!RiseupPathUtils::dirExists($snapshots_dir)) {
                return $result;
            }

            // Get all .sqlite files in directory
            $files = glob(RiseupPathUtils::join($snapshots_dir, '*.sqlite'));
            if (empty($files)) {
                return $result;
            }

            // Get all filepaths from database
            $db_files = $this->db->query_all(
                'SELECT filepath FROM ' . RISEUP_TABLE_SNAPSHOTS
            ) ?: array();
            $db_filepaths = array_column($db_files, 'filepath');

            // Find orphans
            foreach ($files as $file) {
                if (!in_array($file, $db_filepaths)) {
                    $bytes = filesize($file);
                    if (RiseupPathUtils::deleteFile($file)) {
                        $result['deleted']++;
                        $result['bytes_freed'] += $bytes;

                        $this->log('DEBUG', 'Deleted orphan file', array('file' => basename($file)));

                        // Also delete matching ZIP
                        $zip_path = $this->getZipPath($file);
                        if (RiseupPathUtils::fileExists($zip_path)) {
                            $result['bytes_freed'] += filesize($zip_path);
                            RiseupPathUtils::deleteFile($zip_path);
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->log('ERROR', 'Orphan cleanup failed', array('error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Cleanup failed/stuck snapshots older than 24 hours.
     *
     * @return array {
     *     @type int    $deleted Number deleted.
     *     @type string $error   Error message if any.
     * }
     */
    private function cleanupFailedSnapshots() {
        $result = array(
            'deleted' => 0,
            'error' => null,
        );

        try {
            $cutoff = date('c', strtotime('-24 hours'));

            // Find stuck running or failed snapshots
            $stuck = $this->db->query_all(
                'SELECT id, filepath, filename, status FROM ' . RISEUP_TABLE_SNAPSHOTS .
                ' WHERE status IN (?, ?, ?) AND created_at < ?',
                array(
                    RISEUP_SNAPSHOT_STATUS_PENDING,
                    RISEUP_SNAPSHOT_STATUS_RUNNING,
                    RISEUP_SNAPSHOT_STATUS_FAILED,
                    $cutoff
                )
            ) ?: array();

            foreach ($stuck as $snapshot) {
                // Delete file if exists
                if (!empty($snapshot['filepath']) && RiseupPathUtils::fileExists($snapshot['filepath'])) {
                    RiseupPathUtils::deleteFile($snapshot['filepath']);
                }

                // Delete database record
                $this->db->delete(RISEUP_TABLE_SNAPSHOTS, array('id' => $snapshot['id']));

                // Delete progress records
                $this->db->execute(
                    'DELETE FROM ' . RISEUP_TABLE_SNAPSHOT_PROGRESS . ' WHERE snapshot_id = ?',
                    array($snapshot['id'])
                );

                $result['deleted']++;

                $this->log('DEBUG', 'Deleted stuck/failed snapshot', array(
                    'id' => $snapshot['id'],
                    'status' => $snapshot['status'],
                ));
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->log('ERROR', 'Failed snapshot cleanup error', array('error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Get storage statistics for snapshots.
     *
     * @return array {
     *     @type int    $total_snapshots     Total snapshot count.
     *     @type int    $total_size_bytes    Total size in bytes.
     *     @type string $total_size_formatted Human-readable size.
     *     @type int    $oldest_timestamp    Oldest snapshot timestamp.
     *     @type int    $newest_timestamp    Newest snapshot timestamp.
     *     @type int    $disk_free_bytes     Free disk space.
     *     @type string $disk_free_formatted Human-readable free space.
     * }
     */
    public function getStorageStats() {
        $stats = array(
            'total_snapshots' => 0,
            'total_size_bytes' => 0,
            'total_size_formatted' => '0 B',
            'oldest_timestamp' => null,
            'newest_timestamp' => null,
            'disk_free_bytes' => 0,
            'disk_free_formatted' => '0 B',
        );

        try {
            // Get snapshot stats from database
            $db_stats = $this->db->query_single(
                'SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(size), 0) as total_size,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM ' . RISEUP_TABLE_SNAPSHOTS .
                ' WHERE status = ?',
                array(RISEUP_SNAPSHOT_STATUS_COMPLETE)
            );

            if ($db_stats) {
                $stats['total_snapshots'] = intval($db_stats['count']);
                $stats['total_size_bytes'] = intval($db_stats['total_size']);
                $stats['total_size_formatted'] = RiseupPathUtils::formatBytes($stats['total_size_bytes']);
                
                if ($db_stats['oldest']) {
                    $stats['oldest_timestamp'] = strtotime($db_stats['oldest']);
                }
                if ($db_stats['newest']) {
                    $stats['newest_timestamp'] = strtotime($db_stats['newest']);
                }
            }

            // Get disk free space
            $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
            
            if (RiseupPathUtils::dirExists($snapshots_dir)) {
                $free = RiseupPathUtils::getFreeSpace($snapshots_dir);
                if ($free !== false) {
                    $stats['disk_free_bytes'] = $free;
                    $stats['disk_free_formatted'] = RiseupPathUtils::formatBytes($free);
                }
            }

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get storage stats', array('error' => $e->getMessage()));
        }

        return $stats;
    }

    /**
     * Estimate space that would be freed by cleanup.
     *
     * @param array $settings Snapshot settings.
     * @return array {
     *     @type int    $snapshots_count Number that would be deleted.
     *     @type int    $bytes           Estimated bytes to free.
     *     @type string $bytes_formatted Human-readable size.
     * }
     */
    public function estimateCleanup($settings) {
        $estimate = array(
            'snapshots_count' => 0,
            'bytes' => 0,
            'bytes_formatted' => '0 B',
        );

        try {
            $snapshots = array();

            if ($settings['retention_type'] === 'days') {
                $snapshots = $this->getSnapshotsOlderThan($settings['retention_days']);
            } elseif ($settings['retention_type'] === 'count') {
                $snapshots = $this->getSnapshotsBeyondCount($settings['retention_count']);
            }

            $estimate['snapshots_count'] = count($snapshots);
            $estimate['bytes'] = array_sum(array_column($snapshots, 'size'));
            $estimate['bytes_formatted'] = RiseupPathUtils::formatBytes($estimate['bytes']);

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to estimate cleanup', array('error' => $e->getMessage()));
        }

        return $estimate;
    }

    /**
     * Get ZIP path from SQLite path.
     *
     * @param string $sqlite_path Path to .sqlite file.
     * @return string Path to .zip file.
     */
    private function getZipPath($sqlite_path) {
        return preg_replace('/\.sqlite$/', '.zip', $sqlite_path);
    }

    /**
     * Log a message with cleaner context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Additional context.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [CLEANER]';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case 'DEBUG':
                    $this->logger->debug($full_message);
                    break;
                case 'INFO':
                    $this->logger->info($full_message);
                    break;
                case 'WARN':
                    $this->logger->warn($full_message);
                    break;
                case 'ERROR':
                    $this->logger->error($full_message);
                    break;
                default:
                    $this->logger->info($full_message);
            }
        }
    }
}
