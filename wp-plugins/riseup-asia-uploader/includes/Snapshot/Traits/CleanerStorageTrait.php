<?php
/**
 * Cleaner Storage Trait
 *
 * Storage statistics and cleanup estimation.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait CleanerStorageTrait {

    /**
     * Get storage statistics for snapshots.
     *
     * @return array Storage stats with counts, sizes, and disk info.
     */
    public function getStorageStats() {
        $stats = array(
            'total_snapshots'      => 0,
            'total_size_bytes'     => 0,
            'total_size_formatted' => '0 B',
            'oldest_timestamp'     => null,
            'newest_timestamp'     => null,
            'disk_free_bytes'      => 0,
            'disk_free_formatted'  => '0 B',
        );

        try {
            $db_stats = $this->db->query_single(
                'SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(size), 0) as total_size,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM ' . TableType::Snapshots->value .
                ' WHERE status = ?',
                array(SNAPSHOT_STATUS_COMPLETE)
            );

            if ($db_stats) {
                $stats['total_snapshots']      = intval($db_stats['count']);
                $stats['total_size_bytes']     = intval($db_stats['total_size']);
                $stats['total_size_formatted'] = RiseupPathUtils::formatBytes($stats['total_size_bytes']);

                if ($db_stats['oldest']) {
                    $stats['oldest_timestamp'] = strtotime($db_stats['oldest']);
                }
                if ($db_stats['newest']) {
                    $stats['newest_timestamp'] = strtotime($db_stats['newest']);
                }
            }

            $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
            if (RiseupPathUtils::dirExists($snapshots_dir)) {
                $free = RiseupPathUtils::getFreeSpace($snapshots_dir);
                if ($free !== false) {
                    $stats['disk_free_bytes']     = $free;
                    $stats['disk_free_formatted'] = RiseupPathUtils::formatBytes($free);
                }
            }

        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to get storage stats', array('error' => $e->getMessage()));
        }

        return $stats;
    }

    /**
     * Estimate space that would be freed by cleanup.
     *
     * @param array $settings Snapshot settings.
     * @return array { snapshots_count, bytes, bytes_formatted }.
     */
    public function estimateCleanup($settings) {
        $estimate = array(
            'snapshots_count' => 0,
            'bytes'           => 0,
            'bytes_formatted' => '0 B',
        );

        try {
            $snapshots = array();

            if ($settings['retention_type'] === 'days') {
                $snapshots = $this->getSnapshotsOlderThan($settings['retention_days']);
            } elseif ($settings['retention_type'] === 'count') {
                $snapshots = $this->getSnapshotsBeyondCount($settings['retention_count']);
            }

            $snapshots = array_filter($snapshots, function($s) {
                return !$this->isMasterSnapshot($s);
            });

            $estimate['snapshots_count'] = count($snapshots);
            $estimate['bytes']           = array_sum(array_column($snapshots, 'size'));
            $estimate['bytes_formatted'] = RiseupPathUtils::formatBytes($estimate['bytes']);

        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to estimate cleanup', array('error' => $e->getMessage()));
        }

        return $estimate;
    }
}
