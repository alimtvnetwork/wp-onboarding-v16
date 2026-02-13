<?php
/**
 * ManagerImportTrait — Snapshot ZIP import operations.
 *
 * Shell trait — delegates to ManagerImportValidationTrait and ManagerImportRecordTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/ManagerImportValidationTrait.php';
require_once __DIR__ . '/ManagerImportRecordTrait.php';

trait ManagerImportTrait {

    use ManagerImportValidationTrait;
    use ManagerImportRecordTrait;

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
        $this->extractZipToDir($uploaded_path, $temp_dir);
        $manifest = $this->loadAndValidateManifest($temp_dir);
        $sqlite_path = $this->validateSnapshotSqlite($manifest, $temp_dir);

        return array('manifest' => $manifest, 'sqlite_path' => $sqlite_path);
    }

    /** Extract a ZIP file into a directory. */
    private function extractZipToDir(string $uploaded_path, string $temp_dir) {
        $zip = new ZipArchive();
        if ($zip->open($uploaded_path) !== true) {
            throw new Exception('Failed to open ZIP file');
        }
        $zip->extractTo($temp_dir);
        $zip->close();
    }

    /** Load and validate the manifest.json from the extracted directory. */
    private function loadAndValidateManifest(string $temp_dir): array {
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

        return $manifest;
    }

    /** Validate the SQLite file referenced by the manifest. */
    private function validateSnapshotSqlite(array $manifest, string $temp_dir): string {
        $sqlite_filename = $manifest['snapshot']['filename'];
        $sqlite_path = RiseupPathUtils::join($temp_dir, $sqlite_filename);

        if (!RiseupPathUtils::fileExists($sqlite_path)) {
            throw new Exception('SQLite file not found in archive: ' . $sqlite_filename);
        }

        $integrity = $this->validateSqliteIntegrity($sqlite_path);
        if (!$integrity['valid']) {
            throw new Exception('SQLite integrity check failed: ' . $integrity['error']);
        }

        return $sqlite_path;
    }

    /**
     * Move validated snapshot to storage and create DB record.
     *
     * @param array  $manifest    Parsed manifest.
     * @param string $sqlite_path Path to validated SQLite file.
     * @param string $temp_dir    Temp directory.
     * @return array Success result.
     */
    private function moveAndRecordSnapshot($manifest, $sqlite_path, $temp_dir) {
        $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
        if (!RiseupPathUtils::ensureDir($snapshots_dir, true)) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $sequence = $this->getNextImportSequence();
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
}
