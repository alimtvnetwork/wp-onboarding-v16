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
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

trait CleanerStorageTrait {
    public function getStorageStats(): array {
        $stats = array(
            ResponseKeyType::TotalSnapshots->value    => 0,
            ResponseKeyType::TotalSizeBytes->value    => 0,
            ResponseKeyType::TotalSizeFormatted->value => '0 B',
            ResponseKeyType::OldestTimestamp->value    => null,
            ResponseKeyType::NewestTimestamp->value    => null,
            ResponseKeyType::DiskFreeBytes->value      => 0,
            ResponseKeyType::DiskFreeFormatted->value  => '0 B',
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
                $stats[ResponseKeyType::TotalSizeFormatted->value] = PathHelper::formatBytes($stats[ResponseKeyType::TotalSizeBytes->value]);

                if ($dbStats['oldest']) {
                    $stats[ResponseKeyType::OldestTimestamp->value] = strtotime($dbStats['oldest']);
                }

                if ($dbStats['newest']) {
                    $stats[ResponseKeyType::NewestTimestamp->value] = strtotime($dbStats['newest']);
                }
            }

            $snapshotsDir = PathHelper::getSnapshotsDir();

            if (PathHelper::dirExists($snapshotsDir)) {
                $free = PathHelper::getFreeSpace($snapshotsDir);

                if ($free !== false) {
                    $stats[ResponseKeyType::DiskFreeBytes->value]     = $free;
                    $stats[ResponseKeyType::DiskFreeFormatted->value] = PathHelper::formatBytes($free);
                }
            }
        } catch (Throwable $e) {
            $this->logError($e, 'Failed to get storage stats');
        }

        return $stats;
    }

    public function estimateCleanup(array $settings): array {
        $estimate = array(
            ResponseKeyType::SnapshotsCount->value => 0,
            ResponseKeyType::Bytes->value          => 0,
            ResponseKeyType::BytesFormatted->value => '0 B',
        );

        try {
            $snapshots = array();

            if ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Days->value) {
                $snapshots = $this->getSnapshotsOlderThan($settings[SettingsKeyType::RetentionDays->value]);
            } elseif ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Count->value) {
                $snapshots = $this->getSnapshotsBeyondCount($settings[SettingsKeyType::RetentionCount->value]);
            }

            $snapshots = array_filter($snapshots, function($s) {
                $isOrdinarySnapshot = ($this->isMasterSnapshot($s) === false);

                return $isOrdinarySnapshot;
            });

            $estimate[ResponseKeyType::SnapshotsCount->value] = count($snapshots);
            $estimate[ResponseKeyType::Bytes->value] = array_sum(array_column($snapshots, 'size'));
            $estimate[ResponseKeyType::BytesFormatted->value] = PathHelper::formatBytes($estimate[ResponseKeyType::Bytes->value]);
        } catch (Throwable $e) {
            $this->logError($e, 'Failed to estimate cleanup');
        }

        return $estimate;
    }
}
