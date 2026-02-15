<?php
/**
 * CleanerRetentionTrait — Retention-policy cleanup logic and master snapshot protection.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\SnapshotStatusType;

trait CleanerRetentionTrait {

    private function cleanByRetention(array $settings, bool $dryRun = false): array {
        $resolved = $this->resolveRetentionSnapshots($settings);
        if (empty($resolved['snapshots'])) {
            return array('deleted' => 0, 'skipped_master' => 0, 'bytes_freed' => 0, 'details' => array());
        }

        return $this->processRetentionDeletions($resolved['snapshots'], $resolved['reason'], $dryRun);
    }

    private function resolveRetentionSnapshots(array $settings): array {
        if ($settings['retention_type'] === RetentionType::Days->value && !empty($settings['retention_days'])) {
            return array(
                'snapshots' => $this->getSnapshotsOlderThan((int) $settings['retention_days']),
                'reason'    => "older than {$settings['retention_days']} days",
            );
        }

        if ($settings['retention_type'] === RetentionType::Count->value && !empty($settings['retention_count'])) {
            return array(
                'snapshots' => $this->getSnapshotsBeyondCount((int) $settings['retention_count']),
                'reason'    => "exceeds max count of {$settings['retention_count']}",
            );
        }

        return array('snapshots' => array(), 'reason' => '');
    }

    private function processRetentionDeletions(array $snapshots, string $reason, bool $dryRun): array {
        $result = array('deleted' => 0, 'skipped_master' => 0, 'bytes_freed' => 0, 'details' => array());

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result['skipped_master']++;
                continue;
            }

            $result['details'][] = array(
                'id' => $snapshot['id'], 'filename' => $snapshot['filename'] ?? '', 'reason' => $reason,
            );

            $this->applyRetentionDelete($snapshot, $dryRun, $result);
        }

        return $result;
    }

    private function applyRetentionDelete(array $snapshot, bool $dryRun, array &$result): void {
        if ($dryRun) {
            $result['deleted']++;
            $result['bytes_freed'] += $snapshot['size'] ?? 0;
            return;
        }

        $delete_result = $this->deleteSnapshot($snapshot);
        if ($delete_result['success']) {
            $result['deleted']++;
            $result['bytes_freed'] += $delete_result['bytes_freed'];
        }
    }

    private function isMasterSnapshot(array $snap): bool {
        if (isset($snap['scope']) && $snap['scope'] === 'full') return true;
        if (isset($snap['type']) && $snap['type'] === 'full') return true;
        if (isset($snap['filename']) && strpos($snap['filename'], '_full_') !== false) return true;
        return false;
    }

    private function getSnapshotsOlderThan(int $days): array {
        $cutoff = date('c', strtotime("-{$days} days"));

        return $this->db->queryAll(
            'SELECT id, filepath, filename, size, scope, type FROM ' . TableType::Snapshots->value .
            ' WHERE status = ? AND created_at < ? ORDER BY created_at ASC',
            array(SnapshotStatusType::Complete->value, $cutoff)
        ) ?: array();
    }

    private function getSnapshotsBeyondCount(int $count): array {
        $total_result = $this->db->querySingle(
            'SELECT COUNT(*) as cnt FROM ' . TableType::Snapshots->value . ' WHERE status = ?',
            array(SnapshotStatusType::Complete->value)
        );

        if (!$total_result || $total_result['cnt'] <= $count) {
            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->queryAll(
            'SELECT id, filepath, filename, size, scope, type FROM ' . TableType::Snapshots->value .
            ' WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            array(SnapshotStatusType::Complete->value, $to_delete)
        ) ?: array();
    }
}
