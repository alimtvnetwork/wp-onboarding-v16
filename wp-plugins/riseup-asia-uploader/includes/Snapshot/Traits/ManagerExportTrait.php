<?php
/**
 * ManagerExportTrait — Snapshot ZIP export operations.
 *
 * Handles exporting snapshots to ZIP archives with manifest generation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait ManagerExportTrait {

    /**
     * Export a snapshot to a downloadable ZIP file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Result with filepath.
     */
    public function exportSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available');
        }

        $snapshot = $provider->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found', 'code' => ERR_SNAPSHOT_NOT_FOUND);
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found');
        }

        return $this->createSnapshotZip($snapshot_id, $filepath, $snapshot);
    }

    /**
     * Create a ZIP archive from a snapshot file with manifest.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param string $filepath   Path to the SQLite file.
     * @param array  $snapshot   Snapshot record.
     * @return array Result with filepath, filename, size.
     */
    private function createSnapshotZip(int $snapshot_id, string $filepath, array $snapshot): array {
        $zip_path = preg_replace('/\.sqlite$/', '.zip', $filepath);

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->log(LOG_LEVEL_ERROR, 'Failed to create ZIP file', array('path' => $zip_path));
            return array('success' => false, 'error' => 'Failed to create ZIP file');
        }

        $zip->addFile($filepath, basename($filepath));
        $manifest = $this->createExportManifest($snapshot);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        $size = filesize($zip_path);
        $this->log(LOG_LEVEL_INFO, 'Snapshot exported to ZIP', array(
            'snapshot_id' => $snapshot_id, 'zip_path' => $zip_path, 'size' => RiseupPathUtils::formatBytes($size),
        ));

        return array('success' => true, 'filepath' => $zip_path, 'filename' => basename($zip_path), 'size' => $size);
    }

    /**
     * Create export manifest for ZIP.
     *
     * @param array $snapshot Snapshot record.
     * @return array Manifest data.
     */
    private function createExportManifest($snapshot) {
        return array(
            'version' => PLUGIN_VERSION,
            'format_version' => '1.0',
            'created_at' => date('c'),
            'exported_at' => date('c'),
            'snapshot' => array(
                'id' => $snapshot['id'],
                'sequence' => $snapshot['sequence'],
                'filename' => $snapshot['filename'],
                'scope' => $snapshot['scope'],
                'provider' => $snapshot['provider'],
                'tables' => json_decode($snapshot['tables_json'], true),
                'total_rows' => $snapshot['total_rows'],
                'file_size' => $snapshot['file_size'],
                'created_at' => $snapshot['created_at'],
            ),
            'source' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'site_url' => get_site_url(),
                'db_prefix' => $this->wpdb->prefix,
            ),
        );
    }
}
