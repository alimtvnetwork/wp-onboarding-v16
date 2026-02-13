<?php
/**
 * NativeSnapshotCrudTrait — Snapshot delete, export, import, list, and get operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait NativeSnapshotCrudTrait {

    /**
     * Delete a snapshot.
     */
    public function deleteSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (RiseupPathUtils::fileExists($filepath)) {
            if (!RiseupPathUtils::deleteFile($filepath)) {
                $this->log(LogLevelType::Error->value, 'Failed to delete snapshot file', array('filepath' => $filepath));
                return array('success' => false, 'error' => 'Failed to delete snapshot file');
            }
        }

        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        if (RiseupPathUtils::fileExists($zip_path)) {
            RiseupPathUtils::deleteFile($zip_path);
        }

        $this->db->delete(TABLE_SNAPSHOTS, array('id' => $snapshot_id));
        $this->log(LogLevelType::Info->value, 'Snapshot deleted', array('snapshot_id' => $snapshot_id, 'filename' => $snapshot['filename']));
        return array('success' => true);
    }

    /**
     * Export snapshot to ZIP file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Export result.
     */
    public function exportSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found');
        }

        return $this->createExportZip($snapshot_id, $filepath, $snapshot);
    }

    /** Create a ZIP export for a single snapshot. */
    private function createExportZip(int $snapshot_id, string $filepath, array $snapshot): array {
        $zip_path = str_replace('.sqlite', '.zip', $filepath);

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return array('success' => false, 'error' => 'Failed to create ZIP file');
        }

        $zip->addFile($filepath, basename($filepath));
        $zip->addFromString('manifest.json', json_encode($this->buildExportManifest($snapshot_id, $snapshot), JSON_PRETTY_PRINT));
        $zip->close();

        return array('success' => true, 'filepath' => $zip_path, 'filename' => basename($zip_path), 'size' => filesize($zip_path));
    }

    /** Build manifest data for a snapshot export. */
    private function buildExportManifest(int $snapshot_id, array $snapshot): array {
        return array(
            'version' => PLUGIN_VERSION, 'created_at' => date('c'), 'snapshot_id' => $snapshot_id,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'], true),
            'total_rows' => $snapshot['total_rows'], 'file_size' => $snapshot['file_size'],
        );
    }

    /**
     * Import snapshot from uploaded file (delegates to manager).
     *
     * @param string $filepath Path to uploaded file.
     * @return array Import result.
     */
    public function importSnapshot($filepath) {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->importSnapshot($filepath);
    }

    /**
     * Restore from a snapshot (delegates to manager).
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Restore result.
     */
    public function restoreSnapshot($snapshot_id, $options) {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->restoreSnapshot($snapshot_id, $options);
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        return $this->db->query_single('SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE id = ?', array($snapshot_id));
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
        $total = $this->db->query_single('SELECT COUNT(*) as count FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ?', array($this->provider_id));
        return array('snapshots' => $snapshots ?: array(), 'total' => $total ? (int)$total['count'] : 0);
    }

    /**
     * Get available tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        $tables = array();
        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'], 'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $this->wpdb->prefix) === 0,
            );
        }
        return $tables;
    }
}
