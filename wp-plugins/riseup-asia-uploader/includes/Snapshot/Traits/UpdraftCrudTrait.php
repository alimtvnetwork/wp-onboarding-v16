<?php
/**
 * UpdraftCrudTrait — CRUD operations for UpdraftPlus snapshot provider.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UpdraftCrudTrait {

    /**
     * Create a snapshot using UpdraftPlus.
     *
     * @param array $options Snapshot options.
     * @return array Snapshot result.
     */
    public function createSnapshot($options) {
        if (!$this->isAvailable()) {
            return array('success' => false, 'error' => 'UpdraftPlus is not available', 'code' => ERR_PROVIDER_NOT_AVAILABLE);
        }

        $this->log(LOG_LEVEL_INFO, 'Creating snapshot via UpdraftPlus', $options);

        try {
            // TODO: Implement UpdraftPlus integration
            return array('success' => false, 'error' => 'UpdraftPlus integration not yet implemented');
        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'UpdraftPlus snapshot failed', array('error' => $e->getMessage()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /** @return array Restore result. */
    public function restoreSnapshot($snapshot_id, $options) {
        return array('success' => false, 'error' => 'UpdraftPlus restore not yet implemented');
    }

    /** @return array Delete result. */
    public function deleteSnapshot($snapshot_id) {
        return array('success' => false, 'error' => 'UpdraftPlus delete not yet implemented');
    }

    /** @return array Export result. */
    public function exportSnapshot($snapshot_id) {
        return array('success' => false, 'error' => 'UpdraftPlus export not yet implemented');
    }

    /** @return array Import result. */
    public function importSnapshot($filepath) {
        return array('success' => false, 'error' => 'UpdraftPlus import not yet implemented');
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        return $this->db->query_single(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE id = ? AND provider = ?',
            array($snapshot_id, $this->provider_id)
        );
    }

    /**
     * List snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );

        $total = $this->db->query_single(
            'SELECT COUNT(*) as count FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ?',
            array($this->provider_id)
        );

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /**
     * Get available tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        global $wpdb;
        $all_tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        return array_map(function($info) use ($wpdb) {
            return array(
                'name' => $info['Name'], 'rows' => (int)$info['Rows'],
                'size' => (int)$info['Data_length'] + (int)$info['Index_length'],
                'is_core' => strpos($info['Name'], $wpdb->prefix) === 0,
            );
        }, $all_tables);
    }
}
