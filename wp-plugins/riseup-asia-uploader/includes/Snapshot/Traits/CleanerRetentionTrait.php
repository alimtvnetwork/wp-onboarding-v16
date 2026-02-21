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
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\BooleanHelpers;

trait CleanerRetentionTrait {
    private function cleanByRetention(array $settings, bool $dryRun = false): array {
        $resolved = $this->resolveRetentionSnapshots($settings);

        if (empty($resolved['snapshots'])) {

            return array(
                ResponseKeyType::Deleted->value       => 0,
                ResponseKeyType::SkippedMaster->value  => 0,
                ResponseKeyType::BytesFreed->value     => 0,
                'details'                              => array(),
            );
        }

        return $this->processRetentionDeletions($resolved['snapshots'], $resolved['reason'], $dryRun);
    }

    private function resolveRetentionSnapshots(array $settings): array {
        $isDaysRetention = ($settings['retention_type'] === RetentionType::Days->value && BooleanHelpers::hasValue($settings['retention_days']));

        if ($isDaysRetention) {

            return array(
                'snapshots' => $this->getSnapshotsOlderThan((int) $settings['retention_days']),
                'reason'    => "older than {$settings['retention_days']} days",
            );
        }

        $isCountRetention = ($settings['retention_type'] === RetentionType::Count->value && BooleanHelpers::hasValue($settings['retention_count']));

        if ($isCountRetention) {

            return array(
                'snapshots' => $this->getSnapshotsBeyondCount((int) $settings['retention_count']),
                'reason'    => "exceeds max count of {$settings['retention_count']}",
            );
        }

        return array('snapshots' => array(), 'reason' => '');
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
            'details'                              => array(),
        );

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result[ResponseKeyType::SkippedMaster->value]++;
                continue;
            }

            $result['details'][] = array(
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

        $delete_result = $this->deleteSnapshot($snapshot);

        if ($delete_result[ResponseKeyType::Success->value]) {
            $result[ResponseKeyType::Deleted->value]++;
            $result[ResponseKeyType::BytesFreed->value] += $delete_result[ResponseKeyType::BytesFreed->value];
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
        $total_result = $this->db->querySingle(
            'SELECT COUNT(*) as cnt FROM ' . TableType::Snapshots->value . ' WHERE Status = ?',
            array(SnapshotStatusType::Complete->value)
        );

        $isResultMissing = ($total_result === null || $total_result === false);
        $isBelowThreshold = ($isResultMissing || $total_result['cnt'] <= $count);

        if ($isBelowThreshold) {

            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->queryAll(
            'SELECT Id, Filepath, Filename, FileSize, Scope FROM ' . TableType::Snapshots->value .
            ' WHERE Status = ? ORDER BY CreatedAt ASC LIMIT ?',
            array(SnapshotStatusType::Complete->value, $to_delete)
        ) ?: array();
    }
}
