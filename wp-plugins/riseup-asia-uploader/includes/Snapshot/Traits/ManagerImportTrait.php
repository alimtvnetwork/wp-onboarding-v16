<?php
/**
 * ManagerImportTrait — Snapshot ZIP import operations.
 *
 * Shell trait — validation delegated to ManagerImportValidationTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/ManagerImportValidationTrait.php';

trait ManagerImportTrait {

    use ManagerImportValidationTrait;

    /**
     * Import a snapshot from an uploaded ZIP file.
     *
     * @param string $uploaded_path Path to uploaded file.
     * @return array Result with snapshot ID.
     */
    public function importSnapshot($uploaded_path) {
        if (!RiseupPathUtils::fileExists($uploaded_path)) {
            return array('success' => false, 'error' => 'Uploaded file not found');
        }

        $ext = strtolower(pathinfo($uploaded_path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return array('success' => false, 'error' => 'Invalid file type. Expected ZIP file.');
        }

        $this->log(LOG_LEVEL_INFO, 'Importing snapshot from ZIP', array(
            'path' => $uploaded_path, 'size' => RiseupPathUtils::formatBytes(filesize($uploaded_path)),
        ));

        $temp_dir = RiseupPathUtils::join(RiseupPathUtils::getTempDir(), 'import_' . uniqid());

        if (!RiseupPathUtils::ensureDir($temp_dir, false)) {
            return array('success' => false, 'error' => 'Failed to create temp directory');
        }

        try {
            $extracted = $this->extractAndValidateZip($uploaded_path, $temp_dir);
            $result = $this->moveAndRecordSnapshot($extracted['manifest'], $extracted['sqlite_path'], $temp_dir);

            $this->deleteDirectory($temp_dir);
            return $result;
        } catch (Exception $e) {
            if (RiseupPathUtils::dirExists($temp_dir)) {
                $this->deleteDirectory($temp_dir);
            }

            $this->log(LOG_LEVEL_ERROR, 'Snapshot import failed', array('error' => $e->getMessage()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Extract ZIP and validate contents.
     *
     * @param string $uploaded_path Path to ZIP file.
     * @param string $temp_dir      Temp extraction directory.
     * @return array With 'manifest' and 'sqlite_path' keys.
     * @throws Exception On validation failure.
     */
    private function extractAndValidateZip($uploaded_path, $temp_dir) {
        $zip = new ZipArchive();
        if ($zip->open($uploaded_path) !== true) {
            throw new Exception('Failed to open ZIP file');
        }

        $zip->extractTo($temp_dir);
        $zip->close();

        $manifest_path = RiseupPathUtils::join($temp_dir, 'manifest.json');
        if (!RiseupPathUtils::fileExists($manifest_path)) {
            throw new Exception('Invalid snapshot archive: manifest.json not found');
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (!$manifest) {
            throw new Exception('Invalid manifest.json format');
        }

        $validation = $this->validateManifest($manifest);
        if (!$validation['valid']) {
            throw new Exception('Manifest validation failed: ' . $validation['error']);
        }

        $sqlite_filename = $manifest['snapshot']['filename'];
        $sqlite_path = RiseupPathUtils::join($temp_dir, $sqlite_filename);
        if (!RiseupPathUtils::fileExists($sqlite_path)) {
            throw new Exception('SQLite file not found in archive: ' . $sqlite_filename);
        }

        $integrity = $this->validateSqliteIntegrity($sqlite_path);
        if (!$integrity['valid']) {
            throw new Exception('SQLite integrity check failed: ' . $integrity['error']);
        }

        return array('manifest' => $manifest, 'sqlite_path' => $sqlite_path);
    }

    /**
     * Move validated snapshot to storage and create DB record.
     *
     * @param array  $manifest    Parsed manifest.
     * @param string $sqlite_path Path to validated SQLite file.
     * @param string $temp_dir    Temp directory (for cleanup reference).
     * @return array Success result.
     * @throws Exception On failure.
     */
    private function moveAndRecordSnapshot($manifest, $sqlite_path, $temp_dir) {
        $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
        if (!RiseupPathUtils::ensureDir($snapshots_dir, true)) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $sequence = $this->getNextSequence();
        $new_filename = sprintf('%03d_%s', $sequence, date('Y-m-d_His')) . '.sqlite';
        $dest_path = RiseupPathUtils::join($snapshots_dir, $new_filename);

        if (!copy($sqlite_path, $dest_path)) {
            throw new Exception('Failed to copy snapshot file to destination');
        }

        $snapshot_id = $this->createImportedSnapshotRecord($manifest, $sequence, $new_filename, $dest_path);
        if (!$snapshot_id) {
            RiseupPathUtils::deleteFile($dest_path);
            throw new Exception('Failed to create snapshot record');
        }

        $this->log(LOG_LEVEL_INFO, 'Snapshot imported successfully', array(
            'snapshot_id' => $snapshot_id, 'filename' => $new_filename,
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshot_id, 'filename' => $new_filename,
            'tables' => count($manifest['snapshot']['tables']),
            'rows' => $manifest['snapshot']['total_rows'],
        );
    }

    /**
     * Get the next sequence number.
     *
     * @return int Next sequence.
     */
    private function getNextSequence() {
        $result = $this->db->query_single('SELECT MAX(sequence) as max_seq FROM ' . TABLE_SNAPSHOTS);
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    /**
     * Create a database record for an imported snapshot.
     *
     * @param array  $manifest Original manifest.
     * @param int    $sequence New sequence number.
     * @param string $filename New filename.
     * @param string $filepath Full path.
     * @return int|false Snapshot ID or false.
     */
    private function createImportedSnapshotRecord($manifest, $sequence, $filename, $filepath) {
        $snapshot_data = $manifest['snapshot'];

        $data = array(
            'sequence' => $sequence,
            'filename' => $filename,
            'filepath' => $filepath,
            'provider' => SNAPSHOT_PROVIDER_NATIVE,
            'scope' => $snapshot_data['scope'],
            'tables_json' => json_encode($snapshot_data['tables']),
            'total_rows' => $snapshot_data['total_rows'] ?? 0,
            'file_size' => filesize($filepath),
            'trigger_source' => 'import',
            'status' => SNAPSHOT_STATUS_COMPLETE,
            'created_at' => date('c'),
            'completed_at' => date('c'),
            'import_source' => json_encode(array(
                'original_id' => $snapshot_data['id'] ?? null,
                'original_created_at' => $snapshot_data['created_at'] ?? null,
                'source_site' => $manifest['source']['site_url'] ?? null,
            )),
        );

        $result = $this->db->insert(TABLE_SNAPSHOTS, $data);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }
}
