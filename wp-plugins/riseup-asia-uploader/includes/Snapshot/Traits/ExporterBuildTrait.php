<?php
/**
 * ExporterBuildTrait — ZIP build logic and file collection for snapshot exports.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait ExporterBuildTrait {

    /**
     * Build a ZIP archive containing the full snapshot + all incremental children.
     *
     * @param array $snapshot The full snapshot record.
     * @return array {success: bool, export?: array, error?: string}
     */
    private function buildZip($snapshot) {
        $snapshotId = (int) $snapshot['id'];
        $snapshotDir = dirname($snapshot['filepath']);

        $this->log('INFO', 'Building ZIP export', array('snapshot_id' => $snapshotId, 'dir' => basename($snapshotDir)));

        $exportsDir = RiseupPathUtils::getSnapshotsDir() . '/' . SNAPSHOT_EXPORTS_SUBDIR;
        if (RiseupBooleanHelpers::is_dir_missing($exportsDir)) {
            if (!wp_mkdir_p($exportsDir)) {
                return array('success' => false, 'error' => 'Failed to create exports directory', 'code' => ERR_EXPORT_BUILD_FAILED);
            }
            @file_put_contents($exportsDir . '/.htaccess', "deny from all\n");
            @file_put_contents($exportsDir . '/index.php', "<?php // Silence is golden.\n");
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $snapshot['filename']);
        $zipFilename = $safeName . '_export.zip';
        $zipPath = $exportsDir . '/' . $zipFilename;

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return array('success' => false, 'error' => 'Database unavailable', 'code' => ERR_EXPORT_BUILD_FAILED);
        }

        $this->insertBuildingRecord($pdo, $snapshotId, $zipFilename, $zipPath);

        try {
            return $this->assembleZipArchive($pdo, $snapshot, $snapshotId, $snapshotDir, $zipPath, $zipFilename);
        } catch (Exception $e) {
            $this->log('ERROR', 'ZIP export build failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            $stmt = $pdo->prepare('DELETE FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE snapshot_id = ?');
            $stmt->execute(array($snapshotId));
            return array('success' => false, 'error' => 'ZIP build failed: ' . $e->getMessage(), 'code' => ERR_EXPORT_BUILD_FAILED);
        }
    }

    /**
     * Insert a "building" export record.
     *
     * @param PDO    $pdo         PDO instance.
     * @param int    $snapshotId  Snapshot ID.
     * @param string $zipFilename ZIP filename.
     * @param string $zipPath     ZIP path.
     */
    private function insertBuildingRecord(PDO $pdo, int $snapshotId, string $zipFilename, string $zipPath) {
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO ' . TABLE_SNAPSHOT_EXPORTS .
            ' (snapshot_id, zip_filename, zip_path, zip_size, included_ids, incremental_count, status, created_at)' .
            ' VALUES (?, ?, ?, 0, ?, 0, ?, datetime(\'now\'))'
        );
        $stmt->execute(array($snapshotId, $zipFilename, $zipPath, json_encode(array($snapshotId)), SNAPSHOT_EXPORT_STATUS_BUILDING));
    }

    /**
     * Assemble the ZIP archive with full + incremental + manifest.
     *
     * @param PDO    $pdo         PDO instance.
     * @param array  $snapshot    Snapshot record.
     * @param int    $snapshotId  Snapshot ID.
     * @param string $snapshotDir Snapshot directory.
     * @param string $zipPath     Output ZIP path.
     * @param string $zipFilename ZIP filename.
     * @return array Result.
     */
    private function assembleZipArchive(PDO $pdo, array $snapshot, int $snapshotId, string $snapshotDir, string $zipPath, string $zipFilename): array {
        $files = $this->collectSnapshotFiles($snapshotDir);
        if (empty($files)) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);
            return array('success' => false, 'error' => 'No snapshot files found to export', 'code' => ERR_EXPORT_BUILD_FAILED);
        }

        $incrementals = $this->getIncrementalSnapshots($snapshotId, $snapshot['filename']);
        $incrementalDir = $snapshotDir . '/incremental';
        $incrementalFiles = is_dir($incrementalDir) ? $this->collectIncrementalFiles($incrementalDir) : array();
        $includedIds = array($snapshotId);

        foreach ($incrementals as $inc) {
            $includedIds[] = (int) $inc['id'];
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);
            return array('success' => false, 'error' => 'Failed to create ZIP archive (error code: ' . $openResult . ')', 'code' => ERR_EXPORT_BUILD_FAILED);
        }

        $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);
        $this->addFilesToZip($zip, $files, '');
        $this->addFilesToZip($zip, $incrementalFiles, 'incremental/');
        $this->addManifestToZip($zip, $snapshot, $snapshotId, $includedIds, $incrementals);
        $zip->close();

        $this->finalizeExportRecord($pdo, $snapshotId, $includedIds, $incrementals, $zipPath, $zipFilename);

        $export = $this->getValidExport($snapshotId);
        return array('success' => true, 'cached' => false, 'export' => $export);
    }

    /**
     * Add files to ZIP with compression.
     *
     * @param ZipArchive $zip    ZIP archive.
     * @param array      $files  Map of absolute path => relative path.
     * @param string     $prefix Path prefix for entries.
     */
    private function addFilesToZip(ZipArchive $zip, array $files, string $prefix) {
        foreach ($files as $absolutePath => $relativePath) {
            $entryName = $prefix . $relativePath;
            $zip->addFile($absolutePath, $entryName);
            $fileIndex = $zip->locateName($entryName);
            if ($fileIndex !== false) {
                $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE);
            }
        }
    }

    /**
     * Add manifest.json to ZIP archive.
     *
     * @param ZipArchive $zip          ZIP archive.
     * @param array      $snapshot     Snapshot record.
     * @param int        $snapshotId   Snapshot ID.
     * @param array      $includedIds  Included snapshot IDs.
     * @param array      $incrementals Incremental records.
     */
    private function addManifestToZip(ZipArchive $zip, array $snapshot, int $snapshotId, array $includedIds, array $incrementals) {
        $manifest = array(
            'version' => PLUGIN_VERSION, 'created_at' => gmdate('c'), 'snapshot_id' => $snapshotId,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'] ?? '[]', true),
            'total_rows' => (int) ($snapshot['total_rows'] ?? 0), 'included_ids' => $includedIds,
            'incremental_count' => count($incrementals), 'type' => 'full_with_incrementals',
        );
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Finalize the export record after ZIP creation.
     *
     * @param PDO    $pdo          PDO instance.
     * @param int    $snapshotId   Snapshot ID.
     * @param array  $includedIds  Included snapshot IDs.
     * @param array  $incrementals Incremental records.
     * @param string $zipPath      ZIP file path.
     * @param string $zipFilename  ZIP filename.
     */
    private function finalizeExportRecord(PDO $pdo, int $snapshotId, array $includedIds, array $incrementals, string $zipPath, string $zipFilename) {
        $zipSize = filesize($zipPath);
        $stmt = $pdo->prepare(
            'UPDATE ' . TABLE_SNAPSHOT_EXPORTS . ' SET status = ?, zip_size = ?, included_ids = ?, incremental_count = ? WHERE snapshot_id = ?'
        );
        $stmt->execute(array(SNAPSHOT_EXPORT_STATUS_VALID, $zipSize, json_encode($includedIds), count($incrementals), $snapshotId));

        $this->log('INFO', 'ZIP export built successfully', array(
            'snapshot_id' => $snapshotId, 'filename' => $zipFilename, 'size' => RiseupPathUtils::formatBytes($zipSize),
        ));
    }

    /**
     * Collect all .sqlite and .db files from a snapshot directory.
     *
     * @param string $dir Snapshot directory.
     * @return array Map of absolute path => relative path.
     */
    private function collectSnapshotFiles($dir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return $files;
        }

        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (in_array($ext, array('sqlite', 'db'), true)) {
                $files[$file->getPathname()] = $file->getFilename();
            }
        }
        return $files;
    }

    /**
     * Collect all .sqlite files from incremental subdirectories.
     *
     * @param string $incrementalDir The incremental/ directory path.
     * @return array Map of absolute path => relative path.
     */
    private function collectIncrementalFiles($incrementalDir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($incrementalDir)) {
            return $files;
        }

        $subdirs = new DirectoryIterator($incrementalDir);
        foreach ($subdirs as $subdir) {
            if ($subdir->isDot() || !$subdir->isDir()) {
                continue;
            }
            $subdirName = $subdir->getFilename();
            $innerIterator = new DirectoryIterator($subdir->getPathname());
            foreach ($innerIterator as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (in_array($ext, array('sqlite', 'db'), true)) {
                    $files[$file->getPathname()] = $subdirName . '/' . $file->getFilename();
                }
            }
        }
        return $files;
    }

    /**
     * Get all incremental snapshots belonging to a full snapshot.
     *
     * @param int    $parentId   Parent full snapshot ID.
     * @param string $parentName Parent snapshot filename.
     * @return array List of incremental snapshot records.
     */
    private function getIncrementalSnapshots($parentId, $parentName) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return array();
        }

        $stmt = $pdo->prepare(
            'SELECT id, filename, filepath, scope, status, created_at FROM ' . TABLE_SNAPSHOTS .
            ' WHERE scope = \'incremental\' AND filepath LIKE ? AND status = ? ORDER BY created_at ASC'
        );
        $parentDir = '%/' . $parentName . '/incremental/%';
        $stmt->execute(array($parentDir, SNAPSHOT_STATUS_COMPLETE));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
