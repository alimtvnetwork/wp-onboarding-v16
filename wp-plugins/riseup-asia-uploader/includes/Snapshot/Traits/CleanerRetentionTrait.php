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
use RiseupAsia\Helpers\BooleanHelpers;

trait CleanerRetentionTrait {
    private function cleanByRetention(array $settings, bool $dryRun = false): array {
        $resolved = $this->resolveRetentionSnapshots($settings);

        if (empty($resolved[ResponseKeyType::Snapshots->value])) {
            return array(
                ResponseKeyType::Deleted->value       => 0,
                ResponseKeyType::SkippedMaster->value  => 0,
                ResponseKeyType::BytesFreed->value     => 0,
                ResponseKeyType::Details->value        => array(),
            );
        }

        return $this->processRetentionDeletions($resolved[ResponseKeyType::Snapshots->value], $resolved[ResponseKeyType::Reason->value], $dryRun);
    }

    private function resolveRetentionSnapshots(array $settings): array {
        $isDaysRetention = ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Days->value && BooleanHelpers::hasValue($settings[SettingsKeyType::RetentionDays->value]));

        if ($isDaysRetention) {
            return array(
                ResponseKeyType::Snapshots->value => $this->getSnapshotsOlderThan((int) $settings[SettingsKeyType::RetentionDays->value]),
                ResponseKeyType::Reason->value    => "older than {$settings[SettingsKeyType::RetentionDays->value]} days",
            );
        }

        $isCountRetention = ($settings[SettingsKeyType::RetentionType->value] === RetentionType::Count->value && BooleanHelpers::hasValue($settings[SettingsKeyType::RetentionCount->value]));

        if ($isCountRetention) {
            return array(
                ResponseKeyType::Snapshots->value => $this->getSnapshotsBeyondCount((int) $settings[SettingsKeyType::RetentionCount->value]),
                ResponseKeyType::Reason->value    => "exceeds max count of {$settings[SettingsKeyType::RetentionCount->value]}",
            );
        }

        return array(ResponseKeyType::Snapshots->value => array(), ResponseKeyType::Reason->value => '');
    }

    private function processRetentionDeletions(
        array $snapshots,
        string $reason,
        bool $dryRun,
    ): array {
        $result = array(
            ResponseKeyType::Deleted->value       => 0,
            ResponseKeyType::SkippedMaster->value  => 0,
            ResponseKeyType::BytesFreed->value     => 0,
            ResponseKeyType::Details->value        => array(),
        );

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result[ResponseKeyType::SkippedMaster->value]++;
                continue;
            }

            $result[ResponseKeyType::Details->value][] = array(
                'id'                             => $snapshot['Id'],
                ResponseKeyType::Filename->value => $snapshot['Filename'] ?? '',
                ResponseKeyType::Reason->value   => $reason,
            );

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
            array(SnapshotStatusType::Complete->value, $cutoff)
        ) ?: array();
    }

    private function getSnapshotsBeyondCount(int $count): array {
        $totalResult = $this->db->querySingle(
            'SELECT COUNT(*) as cnt FROM ' . TableType::Snapshots->value . ' WHERE Status = ?',
            array(SnapshotStatusType::Complete->value)
        );

        $isResultMissing = ($totalResult === null || $totalResult === false);
        $isBelowThreshold = ($isResultMissing || $totalResult['cnt'] <= $count);

        if ($isBelowThreshold) {
            return array();
        }

        $toDelete = $totalResult['cnt'] - $count;

        return $this->db->queryAll(
            'SELECT Id, Filepath, Filename, FileSize, Scope FROM ' . TableType::Snapshots->value .
            ' WHERE Status = ? ORDER BY CreatedAt ASC LIMIT ?',
            array(SnapshotStatusType::Complete->value, $toDelete)
        ) ?: array();
    }
}
