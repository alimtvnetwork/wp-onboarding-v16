<?php
/**
 * Snapshot Query Trait — Read queries, statistics, and cleanup.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardSnapshotQueryTrait
 *
 * Handles snapshot listing, counting, statistics, deletion, and cleanup.
 */
trait OnboardSnapshotQueryTrait {

    /**
     * Get snapshots for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @param int    $limit       Limit.
     * @param int    $offset      Offset.
     * @return array
     */
    public function get_snapshots($plugin_slug, $limit = 50, $offset = 0) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($plugin_slug, $limit, $offset)
        )->fetchAll();
    }

    /**
     * Get all snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array
     */
    public function get_all_snapshots($limit = 100, $offset = 0) {
        return $this->db->query(
            'SELECT * FROM snapshots ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        )->fetchAll();
    }

    /**
     * Get snapshot by ID.
     *
     * @param string $snapshot_id Snapshot ID.
     * @return array|null
     */
    public function get_snapshot($snapshot_id) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE snapshot_id = ?',
            array($snapshot_id)
        )->fetch();
    }

    /**
     * Get snapshot by version and date.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $version     Version.
     * @param string $backup_date Backup date.
     * @return array|null
     */
    public function get_snapshot_by_version($plugin_slug, $version, $backup_date) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? AND version = ? AND backup_date = ?',
            array($plugin_slug, $version, $backup_date)
        )->fetch();
    }

    /**
     * Get latest snapshot for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @return array|null
     */
    public function get_latest_snapshot($plugin_slug) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT 1',
            array($plugin_slug)
        )->fetch();
    }

    /**
     * Delete a snapshot.
     *
     * @param string $snapshot_id Snapshot ID.
     * @return bool
     */
    public function delete($snapshot_id) {
        $snapshot = $this->get_snapshot($snapshot_id);

        if (!$snapshot) {
            return false;
        }

        // Delete file.
        if (file_exists($snapshot['file_path'])) {
            unlink($snapshot['file_path']);
        }

        // Delete database record.
        $this->db->query('DELETE FROM snapshots WHERE snapshot_id = ?', array($snapshot_id));

        // Log deletion.
        $this->audit_logger->log(
            'snapshot_deleted',
            $snapshot['plugin_slug'],
            null,
            null,
            'success',
            array(
                'version' => $snapshot['version'],
                'backup_date' => $snapshot['backup_date'],
            )
        );

        return true;
    }

    /**
     * Get snapshot count for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @return int
     */
    public function get_snapshot_count($plugin_slug) {
        $result = $this->db->query(
            'SELECT COUNT(*) as count FROM snapshots WHERE plugin_slug = ?',
            array($plugin_slug)
        )->fetch();

        return (int) $result['count'];
    }

    /**
     * Get total snapshot count.
     *
     * @return int
     */
    public function get_total_count() {
        $result = $this->db->query('SELECT COUNT(*) as count FROM snapshots')->fetch();

        return (int) $result['count'];
    }

    /**
     * Get total snapshot size.
     *
     * @return int Size in bytes.
     */
    public function get_total_size() {
        $result = $this->db->query('SELECT SUM(file_size) as total FROM snapshots')->fetch();

        return (int) $result['total'];
    }

    /**
     * Get unique plugins with snapshots.
     *
     * @return array
     */
    public function get_plugins_with_snapshots() {
        return $this->db->query(
            'SELECT plugin_slug, COUNT(*) as snapshot_count, MAX(created_at) as last_backup FROM snapshots GROUP BY plugin_slug ORDER BY last_backup DESC'
        )->fetchAll();
    }

    /**
     * Clean up old snapshots based on retention policy.
     *
     * @param int|null $retention_count Number of snapshots to keep per plugin.
     * @return int Number of snapshots deleted.
     */
    public function cleanup($retention_count = null) {
        if ($retention_count === null) {
            // Use constant with safe default.
            $default_retention = defined('ONBOARD_SNAPSHOT_RETENTION_COUNT') ? ONBOARD_SNAPSHOT_RETENTION_COUNT : 5;
            $retention_count = $this->db->get_setting('snapshot_retention_count') ?: $default_retention;
        }

        $plugins = $this->get_plugins_with_snapshots();
        $deleted = 0;

        foreach ($plugins as $plugin) {
            $slug = $plugin['plugin_slug'];

            // Get snapshots to delete (older than retention count).
            $old_snapshots = $this->db->query(
                'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT -1 OFFSET ?',
                array($slug, $retention_count)
            )->fetchAll();

            foreach ($old_snapshots as $snapshot) {
                if ($this->delete($snapshot['snapshot_id'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
