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

trait CleanerRetentionTrait {

    /**
     * Cleanup by retention policy (days or count) with master protection.
     *
     * @param array $settings Resolved settings.
     * @param bool  $dry_run  Simulate only.
     * @return array { deleted, skipped_master, bytes_freed, details }.
     */
    private function cleanByRetention($settings, $dry_run = false) {
        $result = array(
            'deleted'        => 0,
            'skipped_master' => 0,
            'bytes_freed'    => 0,
            'details'        => array(),
        );

        $snapshots = array();

        if ($settings['retention_type'] === 'days' && !empty($settings['retention_days'])) {
            $snapshots = $this->getSnapshotsOlderThan((int) $settings['retention_days']);
            $reason = "older than {$settings['retention_days']} days";
        } elseif ($settings['retention_type'] === 'count' && !empty($settings['retention_count'])) {
            $snapshots = $this->getSnapshotsBeyondCount((int) $settings['retention_count']);
            $reason = "exceeds max count of {$settings['retention_count']}";
        }

        if (empty($snapshots)) {
            return $result;
        }

        foreach ($snapshots as $snapshot) {
            if ($this->isMasterSnapshot($snapshot)) {
                $result['skipped_master']++;
                continue;
            }

            $result['details'][] = array(
                'id'       => $snapshot['id'],
                'filename' => $snapshot['filename'] ?? '',
                'reason'   => $reason,
            );

            if (!$dry_run) {
                $delete_result = $this->deleteSnapshot($snapshot);
                if ($delete_result['success']) {
                    $result['deleted']++;
                    $result['bytes_freed'] += $delete_result['bytes_freed'];
                }
            } else {
                $result['deleted']++;
                $result['bytes_freed'] += $snapshot['size'] ?? 0;
            }
        }

        return $result;
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
            'SELECT id, filepath, filename, size, scope, type FROM ' . TABLE_SNAPSHOTS .
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
            'SELECT COUNT(*) as cnt FROM ' . TABLE_SNAPSHOTS . ' WHERE status = ?',
            array(SNAPSHOT_STATUS_COMPLETE)
        );

        if (!$total_result || $total_result['cnt'] <= $count) {
            return array();
        }

        $to_delete = $total_result['cnt'] - $count;

        return $this->db->query_all(
            'SELECT id, filepath, filename, size, scope, type FROM ' . TABLE_SNAPSHOTS .
            ' WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            array(SNAPSHOT_STATUS_COMPLETE, $to_delete)
        ) ?: array();
    }
}
