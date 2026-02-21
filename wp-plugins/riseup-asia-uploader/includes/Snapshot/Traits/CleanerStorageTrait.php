<?php
/**
 * CleanerStorageTrait — Storage statistics and cleanup estimation.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

trait CleanerStorageTrait {

    public function getStorageStats(): array {
        $stats = array(
            ResponseKeyType::TotalSnapshots->value => 0,
            ResponseKeyType::TotalSizeBytes->value => 0,
            'total_size_formatted' => '0 B',
            'oldest_timestamp'     => null,
            'newest_timestamp'     => null,
            'disk_free_bytes'      => 0,
            'disk_free_formatted'  => '0 B',
        );

        try {
            $db_stats = $this->db->querySingle(
                'SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(FileSize), 0) as total_size,
                    MIN(CreatedAt) as oldest,
                    MAX(CreatedAt) as newest
                FROM ' . TableType::Snapshots->value .
                ' WHERE Status = ?',
                array(SnapshotStatusType::Complete->value)
            );

            if ($db_stats) {
                $stats[ResponseKeyType::TotalSnapshots->value]  = intval($db_stats[ResponseKeyType::Count->value]);
                $stats[ResponseKeyType::TotalSizeBytes->value]  = intval($db_stats['total_size']);
                $stats['total_size_formatted'] = PathHelper::formatBytes($stats[ResponseKeyType::TotalSizeBytes->value]);

                if ($db_stats['oldest']) {
                    $stats['oldest_timestamp'] = strtotime($db_stats['oldest']);
                }
                if ($db_stats['newest']) {
                    $stats['newest_timestamp'] = strtotime($db_stats['newest']);
                }
            }

            $snapshots_dir = PathHelper::getSnapshotsDir();
            if (PathHelper::dirExists($snapshots_dir)) {
                $free = PathHelper::getFreeSpace($snapshots_dir);
                if ($free !== false) {
                    $stats['disk_free_bytes']     = $free;
                    $stats['disk_free_formatted'] = PathHelper::formatBytes($free);
                }
            }

        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to get storage stats', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $stats;
    }

    public function estimateCleanup(array $settings): array {
        $estimate = array(
            'snapshots_count' => 0,
            'bytes'           => 0,
            'bytes_formatted' => '0 B',
        );

        try {
            $snapshots = array();

            if ($settings['retention_type'] === RetentionType::Days->value) {
                $snapshots = $this->getSnapshotsOlderThan($settings['retention_days']);
            } elseif ($settings['retention_type'] === RetentionType::Count->value) {
                $snapshots = $this->getSnapshotsBeyondCount($settings['retention_count']);
            }

            $snapshots = array_filter($snapshots, function($s) {
                $isOrdinarySnapshot = ($this->isMasterSnapshot($s) === false);
                return $isOrdinarySnapshot;
            });

            $estimate['snapshots_count'] = count($snapshots);
            $estimate[ResponseKeyType::Bytes->value] = array_sum(array_column($snapshots, 'size'));
            $estimate['bytes_formatted'] = PathHelper::formatBytes($estimate[ResponseKeyType::Bytes->value]);

        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to estimate cleanup', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $estimate;
    }
}
