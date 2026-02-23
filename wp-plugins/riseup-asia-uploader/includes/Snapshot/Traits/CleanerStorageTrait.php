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

use Throwable;
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
            'totalSizeFormatted'                   => '0 B',
            'oldestTimestamp'                       => null,
            'newestTimestamp'                       => null,
            'diskFreeBytes'                        => 0,
            'diskFreeFormatted'                    => '0 B',
        );

        try {
            $dbStats = $this->db->querySingle(
                'SELECT
                    COUNT(*) as count,
                    COALESCE(SUM(FileSize), 0) as total_size,
                    MIN(CreatedAt) as oldest,
                    MAX(CreatedAt) as newest
                FROM ' . TableType::Snapshots->value .
                ' WHERE Status = ?',
                array(SnapshotStatusType::Complete->value)
            );

            if ($dbStats) {
                $stats[ResponseKeyType::TotalSnapshots->value] = intval($dbStats[ResponseKeyType::Count->value]);
                $stats[ResponseKeyType::TotalSizeBytes->value] = intval($dbStats['total_size']);
                $stats['totalSizeFormatted'] = PathHelper::formatBytes($stats[ResponseKeyType::TotalSizeBytes->value]);

                if ($dbStats['oldest']) {
                    $stats['oldestTimestamp'] = strtotime($dbStats['oldest']);
                }

                if ($dbStats['newest']) {
                    $stats['newestTimestamp'] = strtotime($dbStats['newest']);
                }
            }

            $snapshotsDir = PathHelper::getSnapshotsDir();

            if (PathHelper::dirExists($snapshotsDir)) {
                $free = PathHelper::getFreeSpace($snapshotsDir);

                if ($free !== false) {
                    $stats['diskFreeBytes']     = $free;
                    $stats['diskFreeFormatted'] = PathHelper::formatBytes($free);
                }
            }
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to get storage stats', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $stats;
    }

    public function estimateCleanup(array $settings): array {
        $estimate = array(
            'snapshotsCount' => 0,
            'bytes'          => 0,
            'bytesFormatted' => '0 B',
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

            $estimate['snapshotsCount'] = count($snapshots);
            $estimate[ResponseKeyType::Bytes->value] = array_sum(array_column($snapshots, 'size'));
            $estimate['bytesFormatted'] = PathHelper::formatBytes($estimate[ResponseKeyType::Bytes->value]);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to estimate cleanup', array(ResponseKeyType::Error->value => $e->getMessage()));
        }

        return $estimate;
    }
}
