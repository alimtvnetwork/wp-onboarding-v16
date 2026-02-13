<?php
/**
 * Riseup Asia Uploader - Snapshot Cleaner
 *
 * Consolidated cleanup engine handling retention policy enforcement,
 * orphan file cleanup, stuck snapshot handling, and storage management.
 * Supports dry-run mode, master snapshot protection, and audit trail logging.
 *
 * Replaces the former RiseupSnapshotCleanup class (class-snapshot-cleanup.php).
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
 * removes orphan files, handles stuck snapshots, and provides storage statistics.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotCleaner {

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase    $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Execute full cleanup with unified response format.
     *
     * This is the primary public entry point used by both the REST handler
     * and the scheduler. Supports dry-run mode, master snapshot protection,
     * and automatic audit trail logging.
     *
     * @param array $options {
     *     Optional overrides.
     *     @type string $retention_type  'days', 'count', or 'none'.
     *     @type int    $retention_days  Days to retain.
     *     @type int    $retention_count Max snapshots to keep.
     *     @type bool   $dry_run         If true, simulate without deleting.
     * }
     * @return array {
     *     Cleanup result summary.
     *     @type bool   $success         Whether cleanup completed without errors.
     *     @type array  $retention       { deleted, skipped_master, details }.
     *     @type array  $orphans         { removed, files }.
     *     @type array  $stuck           { cleaned, ids }.
     *     @type array  $errors          Error messages.
     *     @type bool   $dry_run         Whether this was a dry run.
     *     @type float  $duration        Execution time in seconds.
     *     @type int    $space_freed_bytes Total bytes freed.
     * }
     */
    public function execute($options = array()) {
        $start = microtime(true);
        $dry_run = !empty($options['dry_run']);

        $results = array(
            'success'          => true,
            'retention'        => array('deleted' => 0, 'skipped_master' => 0, 'details' => array()),
            'orphans'          => array('removed' => 0, 'files' => array()),
            'stuck'            => array('cleaned' => 0, 'ids' => array()),
            'errors'           => array(),
            'dry_run'          => $dry_run,
            'space_freed_bytes' => 0,
        );

        $settings = $this->loadSettings($options);

        // 1. Retention-based cleanup
        try {
            if ($settings['retention_type'] === 'none') {
                $this->log('DEBUG', 'Retention policy is "none" - skipping policy cleanup');
            } else {
                $retention = $this->cleanByRetention($settings, $dry_run);
                $results['retention'] = $retention;
                $results['space_freed_bytes'] += $retention['bytes_freed'] ?? 0;
            }
        } catch (Exception $e) {
            $results['errors'][] = 'Retention cleanup: ' . $e->getMessage();
            $this->log('ERROR', 'Retention cleanup failed', array('error' => $e->getMessage()));
        }

        // 2. Orphan file cleanup
        try {
            $orphans = $this->cleanupOrphanFiles($dry_run);
            $results['orphans'] = $orphans;
            $results['space_freed_bytes'] += $orphans['bytes_freed'] ?? 0;
        } catch (Exception $e) {
            $results['errors'][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->log('ERROR', 'Orphan cleanup failed', array('error' => $e->getMessage()));
        }

        // 3. Stuck/failed snapshot cleanup
        try {
            $stuck = $this->cleanupStuckSnapshots($dry_run);
            $results['stuck'] = $stuck;
        } catch (Exception $e) {
            $results['errors'][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->log('ERROR', 'Stuck snapshot cleanup failed', array('error' => $e->getMessage()));
        }

        $results['success'] = empty($results['errors']);
        $results['duration'] = round(microtime(true) - $start, 3);

        $total_deleted = $results['retention']['deleted']
            + $results['orphans']['removed']
            + $results['stuck']['cleaned'];

        $this->log('INFO', 'Cleanup complete', array(
            'deleted_total'    => $total_deleted,
            'space_freed'      => RiseupPathUtils::format_bytes($results['space_freed_bytes']),
            'duration'         => $results['duration'],
            'dry_run'          => $dry_run,
        ));

        // Audit trail
        if (!$dry_run && $total_deleted > 0) {
            $this->logCleanupAudit($results);
        }

        return $results;
    }

    /**
     * Run full cleanup based on retention settings (legacy entry point).
     *
     * Delegates to execute() and maps the response to the legacy format
     * expected by the scheduler.
     *
     * @param array $settings Snapshot settings from detector.
     * @return array {
     *     @type int   $deleted_by_policy   Snapshots deleted by retention policy.
     *     @type int   $deleted_orphans     Orphan files deleted.
     *     @type int   $deleted_failed      Failed/stuck snapshots deleted.
     *     @type int   $space_freed_bytes   Bytes freed.
     *     @type array $errors              Any errors encountered.
     * }
     */
    public function runCleanup($settings) {
        $result = $this->execute($settings);

        // Map to legacy format for scheduler compatibility
        return array(
            'deleted_by_policy' => $result['retention']['deleted'] ?? 0,
            'deleted_orphans'   => $result['orphans']['removed'] ?? 0,
            'deleted_failed'    => $result['stuck']['cleaned'] ?? 0,
            'space_freed_bytes' => $result['space_freed_bytes'] ?? 0,
            'errors'            => $result['errors'] ?? array(),
        );
    }

    // -----------------------------------------------------------------------
    // Retention cleanup
    // -----------------------------------------------------------------------

    /**
     * Cleanup by retention policy (days or count) with master protection.
     *
     * @param array $settings Resolved settings.
     * @param bool  $dry_run  Simulate only.
     * @return array { deleted, skipped_master, bytes_freed, details }.
     */
    private function cleanByRetention($settings, $dry_run = false) {
        $result = array(
            'deleted'        => 0,
            'skipped_master' => 0,
            'bytes_freed'    => 0,
            'details'        => array(),
        );

        $snapshots = array();

        if ($settings['retention_type'] === 'days' && !empty($settings['retention_days'])) {
            $snapshots = $this->getSnapshotsOlderThan((int) $settings['retention_days']);
            $reason = "older than {$settings['retention_days']} days";
        } elseif ($settings['retention_type'] === 'count' && !empty($settings['retention_count'])) {
            $snapshots = $this->getSnapshotsBeyondCount((int) $settings['retention_count']);
            $reason = "exceeds max count of {$settings['retention_count']}";
        }

        if (empty($snapshots)) {
            return $result;
        }

        foreach ($snapshots as $snapshot) {
            // Master snapshot protection: never auto-delete full/master snapshots
            if ($this->isMasterSnapshot($snapshot)) {
                $result['skipped_master']++;
                continue;
            }

            $result['details'][] = array(
                'id'       => $snapshot['id'],
                'filename' => $snapshot['filename'] ?? '',
                'reason'   => $reason,
            );

            if (!$dry_run) {
                $delete_result = $this->deleteSnapshot($snapshot);
                if ($delete_result['success']) {
                    $result['deleted']++;
                    $result['bytes_freed'] += $delete_result['bytes_freed'];
                }
            } else {
                $result['deleted']++;
                $result['bytes_freed'] += $snapshot['size'] ?? 0;
            }
        }

        return $result;
    }

    /**
     * Determine if a snapshot is a master (permanent, never auto-deleted).
     *
     * @param array $snap Snapshot record.
     * @return bool
     */
    private function isMasterSnapshot($snap) {
        if (isset($snap['scope']) && $snap['scope'] === 'full') return true;
        if (isset($snap['type']) && $snap['type'] === 'full') return true;
        if (isset($snap['filename']) && strpos($snap['filename'], '_full_') !== false) return true;
        return false;
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
            'SELECT id, filepath, filename, size, scope, type FROM ' . TABLE_SNAPSHOTS .
            ' WHERE status = ? AND created_at < ? ORDER BY created_at ASC',
            array(SNAPSHOT_STATUS_COMPLETE, $cutoff)
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
            'SELECT COUNT(*) as cnt FROM ' . TABLE_SNAPSHOTS . ' WHERE status = ?',
            array(SNAPSHOT_STATUS_COMPLETE)
        );

        if (!$total_result || $total_result['cnt'] <= $count) {
            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->query_all(
            'SELECT id, filepath, filename, size, scope, type FROM ' . TABLE_SNAPSHOTS .
            ' WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            array(SNAPSHOT_STATUS_COMPLETE, $to_delete)
        ) ?: array();
    }

    // -----------------------------------------------------------------------
    // Snapshot deletion (with cascade)
    // -----------------------------------------------------------------------

    /**
     * Delete a single snapshot (file + database record).
     * If the snapshot is a full snapshot with an incremental/ subdirectory,
     * cascade-delete all incremental children (files + DB records).
     *
     * @param array $snapshot Snapshot record.
     * @return array { success, bytes_freed }.
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
                    'bytes_freed'  => RiseupPathUtils::format_bytes($inc_size),
                ));
            }

            // Delete the full snapshot directory
            $dir_size = $this->getDirectorySize($filepath);
            $this->deleteDirectoryRecursive($filepath);
            $bytes_freed += $dir_size;
        } else {
            // Single-file snapshot (legacy .sqlite format)
            if (RiseupPathUtils::file_exists($filepath)) {
                $bytes_freed = filesize($filepath);
                if (!RiseupPathUtils::delete_file($filepath)) {
                    $this->log('WARN', 'Failed to delete snapshot file', array('filepath' => $filepath));
                    return array('success' => false, 'bytes_freed' => 0);
                }
            }

            // Delete ZIP if exists
            $zip_path = $this->getZipPath($filepath);
            if (RiseupPathUtils::file_exists($zip_path)) {
                $bytes_freed += filesize($zip_path);
                RiseupPathUtils::delete_file($zip_path);
            }
        }

        // Delete database record
        $this->db->delete(TABLE_SNAPSHOTS, array('id' => $snapshot['id']));

        // Delete progress records
        $this->db->execute(
            'DELETE FROM ' . TABLE_SNAPSHOT_PROGRESS . ' WHERE snapshot_id = ?',
            array($snapshot['id'])
        );

        // Feature D: Remove cached ZIP exports for this snapshot
        try {
            require_once dirname(__FILE__) . '/SnapshotExporter.php';
            $exporter = RiseupSnapshotExporter::getInstance($this->logger, $this->db);
            if ($exporter) {
                $exporter->removeExports((int) $snapshot['id']);
            }
        } catch (Exception $e) {
            $this->log('WARN', 'Failed to remove cached ZIP exports during delete', array(
                'snapshot_id' => $snapshot['id'],
                'error'       => $e->getMessage(),
            ));
        }

        $this->log('DEBUG', 'Deleted snapshot', array(
            'id' => $snapshot['id'],
            'filename' => $snapshot['filename'] ?? '',
            'bytes_freed' => RiseupPathUtils::format_bytes($bytes_freed),
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
            $incrementals = $this->db->query_all(
                'SELECT id FROM ' . TABLE_SNAPSHOTS .
                " WHERE scope = 'incremental' AND filepath LIKE ?",
                array($parent_dir . '/incremental/%')
            ) ?: array();

            foreach ($incrementals as $inc) {
                $this->db->delete(TABLE_SNAPSHOTS, array('id' => $inc['id']));
                $this->db->execute(
                    'DELETE FROM ' . TABLE_SNAPSHOT_PROGRESS . ' WHERE snapshot_id = ?',
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

    // -----------------------------------------------------------------------
    // Orphan file cleanup
    // -----------------------------------------------------------------------

    /**
     * Cleanup orphan files (files without database records).
     *
     * @param bool $dry_run Simulate only.
     * @return array { removed, files, bytes_freed }.
     */
    private function cleanupOrphanFiles($dry_run = false) {
        $result = array(
            'removed'     => 0,
            'files'       => array(),
            'bytes_freed' => 0,
        );

        $snapshots_dir = RiseupPathUtils::get_snapshots_dir();
        if (!RiseupPathUtils::dir_exists($snapshots_dir)) {
            return $result;
        }

        // Get all known filenames from database
        $db_files = $this->db->query_all(
            'SELECT filepath, filename FROM ' . TABLE_SNAPSHOTS
        ) ?: array();

        $db_filepaths = array_column($db_files, 'filepath');
        $db_filenames = array();
        foreach ($db_files as $f) {
            if (!empty($f['filename'])) {
                $db_filenames[basename($f['filename'])] = true;
            }
        }

        // Scan filesystem for .sqlite files
        $files = glob(RiseupPathUtils::join($snapshots_dir, '*.sqlite'));
        if (!empty($files)) {
            foreach ($files as $file) {
                if (!in_array($file, $db_filepaths) && !isset($db_filenames[basename($file)])) {
                    $result['files'][] = basename($file);
                    $bytes = filesize($file);

                    if (!$dry_run) {
                        if (RiseupPathUtils::delete_file($file)) {
                            $result['removed']++;
                            $result['bytes_freed'] += $bytes;
                            $this->log('DEBUG', 'Deleted orphan file', array('file' => basename($file)));

                            // Also delete matching ZIP
                            $zip_path = $this->getZipPath($file);
                            if (RiseupPathUtils::file_exists($zip_path)) {
                                $result['bytes_freed'] += filesize($zip_path);
                                RiseupPathUtils::delete_file($zip_path);
                            }
                        }
                    } else {
                        $result['removed']++;
                        $result['bytes_freed'] += $bytes;
                    }
                }
            }
        }

        // Scan for orphan directories
        $entries = scandir($snapshots_dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === 'index.php') continue;
            $full_path = $snapshots_dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($full_path)) continue;
            if (!isset($db_filenames[$entry]) && !in_array($full_path, $db_filepaths)) {
                $result['files'][] = $entry;
                if (!$dry_run) {
                    $dir_size = $this->getDirectorySize($full_path);
                    $this->deleteDirectoryRecursive($full_path);
                    $result['removed']++;
                    $result['bytes_freed'] += $dir_size;
                    $this->log('INFO', 'Orphan snapshot directory removed', array('dir' => $entry));
                } else {
                    $result['removed']++;
                }
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Stuck/failed snapshot cleanup
    // -----------------------------------------------------------------------

    /**
     * Cleanup stuck/failed snapshots older than the configured threshold.
     *
     * Stuck snapshots are marked as failed (preserved for diagnostics)
     * rather than deleted outright.
     *
     * @param bool $dry_run Simulate only.
     * @return array { cleaned, ids }.
     */
    private function cleanupStuckSnapshots($dry_run = false) {
        $result = array(
            'cleaned' => 0,
            'ids'     => array(),
        );

        $stuck_hours = defined('SNAPSHOT_STUCK_HOURS') ? SNAPSHOT_STUCK_HOURS : 24;
        $cutoff = date('c', strtotime("-{$stuck_hours} hours"));

        // Find stuck running, pending, or failed snapshots
        $stuck = $this->db->query_all(
            'SELECT id, filepath, filename, status FROM ' . TABLE_SNAPSHOTS .
            ' WHERE status IN (?, ?, ?) AND created_at < ?',
            array(
                SNAPSHOT_STATUS_PENDING,
                SNAPSHOT_STATUS_RUNNING,
                SNAPSHOT_STATUS_FAILED,
                $cutoff
            )
        ) ?: array();

        foreach ($stuck as $snapshot) {
            $result['ids'][] = (int) $snapshot['id'];

            if (!$dry_run) {
                // Mark as failed (preserve for diagnostics)
                $this->db->execute(
                    'UPDATE ' . TABLE_SNAPSHOTS . ' SET status = ?, error = ? WHERE id = ?',
                    array(
                        SNAPSHOT_STATUS_FAILED,
                        "Auto-cleaned: stuck for >{$stuck_hours} hours",
                        $snapshot['id']
                    )
                );

                $this->log('WARN', 'Stuck snapshot marked as failed', array(
                    'id'     => $snapshot['id'],
                    'status' => $snapshot['status'],
                ));
            }

            $result['cleaned']++;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Storage statistics & estimation
    // -----------------------------------------------------------------------

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
            $db_stats = $this->db->query_single(
                'SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(size), 0) as total_size,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM ' . TABLE_SNAPSHOTS .
                ' WHERE status = ?',
                array(SNAPSHOT_STATUS_COMPLETE)
            );

            if ($db_stats) {
                $stats['total_snapshots'] = intval($db_stats['count']);
                $stats['total_size_bytes'] = intval($db_stats['total_size']);
                $stats['total_size_formatted'] = RiseupPathUtils::format_bytes($stats['total_size_bytes']);

                if ($db_stats['oldest']) {
                    $stats['oldest_timestamp'] = strtotime($db_stats['oldest']);
                }
                if ($db_stats['newest']) {
                    $stats['newest_timestamp'] = strtotime($db_stats['newest']);
                }
            }

            // Get disk free space
            $snapshots_dir = RiseupPathUtils::get_snapshots_dir();

            if (RiseupPathUtils::dir_exists($snapshots_dir)) {
                $free = RiseupPathUtils::get_free_space($snapshots_dir);
                if ($free !== false) {
                    $stats['disk_free_bytes'] = $free;
                    $stats['disk_free_formatted'] = RiseupPathUtils::format_bytes($free);
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

            // Exclude master snapshots from estimate
            $snapshots = array_filter($snapshots, function($s) {
                return !$this->isMasterSnapshot($s);
            });

            $estimate['snapshots_count'] = count($snapshots);
            $estimate['bytes'] = array_sum(array_column($snapshots, 'size'));
            $estimate['bytes_formatted'] = RiseupPathUtils::formatBytes($estimate['bytes']);

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to estimate cleanup', array('error' => $e->getMessage()));
        }

        return $estimate;
    }

    // -----------------------------------------------------------------------
    // Settings & helpers
    // -----------------------------------------------------------------------

    /**
     * Load retention settings from WP options with overrides.
     *
     * @param array $overrides User-provided overrides.
     * @return array Resolved settings.
     */
    private function loadSettings($overrides) {
        $defaults = array(
            'retention_type'  => defined('RETENTION_TYPE_DAYS') ? RETENTION_TYPE_DAYS : 'days',
            'retention_days'  => defined('SNAPSHOT_RETENTION_DAYS_DEFAULT') ? SNAPSHOT_RETENTION_DAYS_DEFAULT : 30,
            'retention_count' => defined('SNAPSHOT_RETENTION_COUNT_DEFAULT') ? SNAPSHOT_RETENTION_COUNT_DEFAULT : 10,
        );

        // Load from WP options
        $saved = get_option(
            defined('OPTION_SNAPSHOT_SETTINGS') ? OPTION_SNAPSHOT_SETTINGS : 'riseup_snapshot_settings',
            array()
        );
        if (is_array($saved)) {
            $defaults = array_merge($defaults, $saved);
        }

        // Apply overrides (filter out nulls)
        return array_merge($defaults, array_filter($overrides, function($v) { return $v !== null; }));
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

    // -----------------------------------------------------------------------
    // Audit trail
    // -----------------------------------------------------------------------

    /**
     * Log cleanup results to the audit trail.
     *
     * @param array $results Cleanup results from execute().
     */
    private function logCleanupAudit($results) {
        try {
            $this->db->logTransaction(
                ACTION_SNAPSHOT_CLEANUP,
                json_encode(array(
                    'retention_deleted'   => $results['retention']['deleted'],
                    'retention_skipped'   => $results['retention']['skipped_master'],
                    'orphans_removed'     => $results['orphans']['removed'],
                    'stuck_cleaned'       => $results['stuck']['cleaned'],
                    'space_freed'         => RiseupPathUtils::formatBytes($results['space_freed_bytes']),
                    'errors'              => count($results['errors']),
                    'duration'            => $results['duration'],
                )),
                empty($results['errors']) ? STATUS_SUCCESS : STATUS_FAILED,
                TRIGGERED_BY_API
            );
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to log cleanup action', array('error' => $e->getMessage()));
        }
    }

    // -----------------------------------------------------------------------
    // Logging
    // -----------------------------------------------------------------------

    /**
     * Log a message with cleaner context prefix.
     *
     * @param string $level   Log level (DEBUG, INFO, WARN, ERROR).
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
