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
        $stats = $this->buildEmptyStorageStats();

        try {
            $this->populateDbStats($stats);
            $this->populateDiskFreeStats($stats);
        } catch (Throwable $e) {
            $this->logError($e, 'Failed to get storage stats');
        }

        return $stats;
    }

    private function buildEmptyStorageStats(): array {
        return array(
            ResponseKeyType::TotalSnapshots->value     => 0,
            ResponseKeyType::TotalSizeBytes->value     => 0,
            ResponseKeyType::TotalSizeFormatted->value => '0 B',
            ResponseKeyType::OldestTimestamp->value    => null,
            ResponseKeyType::NewestTimestamp->value    => null,
            ResponseKeyType::DiskFreeBytes->value      => 0,
            ResponseKeyType::DiskFreeFormatted->value  => '0 B',
        );
    }

    private function populateDbStats(array &$stats): void {
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
            $this->applyDbStatsToResult($stats, $dbStats);
        }
    }

    private function applyDbStatsToResult(array &$stats, array $dbStats): void {
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

    private function populateDiskFreeStats(array &$stats): void {
        $snapshotsDir = PathHelper::getSnapshotsDir();

        if (PathHelper::isDirMissing($snapshotsDir)) {
            return;
        }

        $free = PathHelper::getFreeSpace($snapshotsDir);
        $hasFreeSpace = ($free !== false);

        if ($hasFreeSpace) {
            $stats[ResponseKeyType::DiskFreeBytes->value]     = $free;
            $stats[ResponseKeyType::DiskFreeFormatted->value] = PathHelper::formatBytes($free);
        }
    }

    public function estimateCleanup(array $settings): array {
        $estimate = $this->buildEmptyEstimate();

        try {
            $snapshots = $this->getRetentionCandidates($settings);
            $this->applyEstimateCounts($estimate, $snapshots);
        } catch (Throwable $e) {
            $this->logError($e, 'Failed to estimate cleanup');
        }

        return $estimate;
    }

    private function buildEmptyEstimate(): array {
        return array(
            ResponseKeyType::SnapshotsCount->value => 0,
            ResponseKeyType::Bytes->value          => 0,
            ResponseKeyType::BytesFormatted->value => '0 B',
        );
    }

    private function getRetentionCandidates(array $settings): array {
        $snapshots = array();

        if ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Days->value) {
            $snapshots = $this->getSnapshotsOlderThan($settings[SettingsKeyType::RetentionDays->value]);
        } elseif ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Count->value) {
            $snapshots = $this->getSnapshotsBeyondCount($settings[SettingsKeyType::RetentionCount->value]);
        }

        return array_filter($snapshots, function($s) {
            $isOrdinarySnapshot = ($this->isMasterSnapshot($s) === false);

            return $isOrdinarySnapshot;
        });
    }

    private function applyEstimateCounts(array &$estimate, array $snapshots): void {
        $estimate[ResponseKeyType::SnapshotsCount->value] = count($snapshots);
        $estimate[ResponseKeyType::Bytes->value] = array_sum(array_column($snapshots, 'size'));
        $estimate[ResponseKeyType::BytesFormatted->value] = PathHelper::formatBytes($estimate[ResponseKeyType::Bytes->value]);
    }
}
