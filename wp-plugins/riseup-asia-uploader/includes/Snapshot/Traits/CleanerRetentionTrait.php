<?php
/**
 * Cleaner Retention Trait
 *
 * Retention-policy cleanup logic and master snapshot protection.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait CleanerRetentionTrait {

    /**
     * Cleanup by retention policy (days or count) with master protection.
     *
     * @param array $settings Resolved settings.
     * @param bool  $dry_run  Simulate only.
     * @return array { deleted, skipped_master, bytes_freed, details }.
     */
    private function cleanByRetention($settings, $dry_run = false) {
        $resolved = $this->resolveRetentionSnapshots($settings);
        if (empty($resolved['snapshots'])) {
            return array('deleted' => 0, 'skipped_master' => 0, 'bytes_freed' => 0, 'details' => array());
        }

        return $this->processRetentionDeletions($resolved['snapshots'], $resolved['reason'], $dry_run);
    }

    /** Resolve which snapshots to delete based on retention settings. */
    private function resolveRetentionSnapshots(array $settings): array {
        if ($settings['retention_type'] === 'days' && !empty($settings['retention_days'])) {
            return array(
                'snapshots' => $this->getSnapshotsOlderThan((int) $settings['retention_days']),
                'reason'    => "older than {$settings['retention_days']} days",
            );
        }

        if ($settings['retention_type'] === 'count' && !empty($settings['retention_count'])) {
            return array(
                'snapshots' => $this->getSnapshotsBeyondCount((int) $settings['retention_count']),
                'reason'    => "exceeds max count of {$settings['retention_count']}",
            );
        }

        return array('snapshots' => array(), 'reason' => '');
    }

    /** Process retention deletions for a list of snapshots. */
    private function processRetentionDeletions(array $snapshots, string $reason, bool $dry_run): array {
        $result = array('deleted' => 0, 'skipped_master' => 0, 'bytes_freed' => 0, 'details' => array());

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result['skipped_master']++;
                continue;
            }

            $result['details'][] = array(
                'id' => $snapshot['id'], 'filename' => $snapshot['filename'] ?? '', 'reason' => $reason,
            );

            $this->applyRetentionDelete($snapshot, $dry_run, $result);
        }

        return $result;
    }

    /** Apply a single retention deletion (or simulate in dry-run). */
    private function applyRetentionDelete(array $snapshot, bool $dry_run, array &$result) {
        if ($dry_run) {
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
            'SELECT id, filepath, filename, size, scope, type FROM ' . TableType::Snapshots->value .
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
            'SELECT COUNT(*) as cnt FROM ' . TableType::Snapshots->value . ' WHERE status = ?',
            array(SNAPSHOT_STATUS_COMPLETE)
        );

        if (!$total_result || $total_result['cnt'] <= $count) {
            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->query_all(
            'SELECT id, filepath, filename, size, scope, type FROM ' . TableType::Snapshots->value .
            ' WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            array(SNAPSHOT_STATUS_COMPLETE, $to_delete)
        ) ?: array();
    }
}
