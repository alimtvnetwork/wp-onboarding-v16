<?php
/**
 * CleanerRetentionTrait — Retention-policy cleanup logic and master snapshot protection.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\SnapshotStatusType;


trait CleanerRetentionTrait {
    private function cleanByRetention(array $settings, bool $dryRun = false): array {
        $resolved = $this->resolveRetentionSnapshots($settings);

        if (empty($resolved[ResponseKeyType::Snapshots->value])) {
            return [
                ResponseKeyType::Deleted->value       => 0,
                ResponseKeyType::SkippedMaster->value  => 0,
                ResponseKeyType::BytesFreed->value     => 0,
                ResponseKeyType::Details->value        => [],
            ];
        }

        return $this->processRetentionDeletions($resolved[ResponseKeyType::Snapshots->value], $resolved[ResponseKeyType::Reason->value], $dryRun);
    }

    private function resolveRetentionSnapshots(array $settings): array {
        $isDaysRetention = ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Days->value && !empty($settings[SettingsKeyType::RetentionDays->value]));

        if ($isDaysRetention) {
            return [
                ResponseKeyType::Snapshots->value => $this->getSnapshotsOlderThan((int) $settings[SettingsKeyType::RetentionDays->value]),
                ResponseKeyType::Reason->value    => "older than {$settings[SettingsKeyType::RetentionDays->value]} days",
            ];
        }

        $isCountRetention = ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Count->value && !empty($settings[SettingsKeyType::RetentionCount->value]));

        if ($isCountRetention) {
            return [
                ResponseKeyType::Snapshots->value => $this->getSnapshotsBeyondCount((int) $settings[SettingsKeyType::RetentionCount->value]),
                ResponseKeyType::Reason->value    => "exceeds max count of {$settings[SettingsKeyType::RetentionCount->value]}",
            ];
        }

        return [ResponseKeyType::Snapshots->value => [], ResponseKeyType::Reason->value => ''];
    }

    private function processRetentionDeletions(
        array $snapshots,
        string $reason,
        bool $dryRun,
    ): array {
        $result = [
            ResponseKeyType::Deleted->value       => 0,
            ResponseKeyType::SkippedMaster->value  => 0,
            ResponseKeyType::BytesFreed->value     => 0,
            ResponseKeyType::Details->value        => [],
        ];

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result[ResponseKeyType::SkippedMaster->value]++;
                continue;
            }

            $result[ResponseKeyType::Details->value][] = [
                ResponseKeyType::Id->value               => $snapshot['Id'],
                ResponseKeyType::Filename->value         => $snapshot['Filename'] ?? '',
                ResponseKeyType::Reason->value           => $reason,
            ];

            $this->applyRetentionDelete($snapshot, $dryRun, $result);
        }

        return $result;
    }

    private function applyRetentionDelete(
        array $snapshot,
        bool $dryRun,
        array &$result,
    ): void {
        if ($dryRun) {
            $result[ResponseKeyType::Deleted->value]++;
            $result[ResponseKeyType::BytesFreed->value] += $snapshot['FileSize'] ?? 0;

            return;
        }

        $deleteResult = $this->deleteSnapshot($snapshot);

        if ($deleteResult[ResponseKeyType::Success->value]) {
            $result[ResponseKeyType::Deleted->value]++;
            $result[ResponseKeyType::BytesFreed->value] += $deleteResult[ResponseKeyType::BytesFreed->value];
        }
    }

    private function isMasterSnapshot(array $snap): bool {
        $resolvedScope = isset($snap['Scope']) ? SnapshotModeType::tryFrom($snap['Scope']) : null;
        $isScopeFull = ($resolvedScope !== null && $resolvedScope->isFull());

        if ($isScopeFull) {
            return true;
        }

        $resolvedType = isset($snap['type']) ? SnapshotModeType::tryFrom($snap['type']) : null;
        $isTypeFull = ($resolvedType !== null && $resolvedType->isFull());

        if ($isTypeFull) {
            return true;
        }

        return false;
    }

    private function getSnapshotsOlderThan(int $days): array {
        $cutoff = date('c', strtotime("-{$days} days"));

        return $this->db->queryAll(
            'SELECT Id, Filepath, Filename, FileSize, Scope FROM ' . TableType::Snapshots->value .
            ' WHERE Status = ? AND CreatedAt < ? ORDER BY CreatedAt ASC',
            [SnapshotStatusType::Complete->value, $cutoff]
        ) ?: [];
    }

    private function getSnapshotsBeyondCount(int $count): array {
        $totalResult = $this->db->querySingle(
            'SELECT COUNT(*) as cnt FROM ' . TableType::Snapshots->value . ' WHERE Status = ?',
            [SnapshotStatusType::Complete->value]
        );

        $isResultMissing = ($totalResult === null || $totalResult === false);
        $isBelowThreshold = ($isResultMissing || $totalResult['cnt'] <= $count);

        if ($isBelowThreshold) {
            return [];
        }

        $toDelete = $totalResult['cnt'] - $count;

        return $this->db->queryAll(
            'SELECT Id, Filepath, Filename, FileSize, Scope FROM ' . TableType::Snapshots->value .
            ' WHERE Status = ? ORDER BY CreatedAt ASC LIMIT ?',
            [SnapshotStatusType::Complete->value, $toDelete]
        ) ?: [];
    }
}
