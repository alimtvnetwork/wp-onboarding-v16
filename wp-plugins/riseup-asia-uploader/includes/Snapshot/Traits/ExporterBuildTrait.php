<?php
/**
 * ExporterBuildTrait — ZIP build logic for snapshot exports.
 *
 * Shell trait — file collection delegated to ExporterBuildCollectTrait.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

trait ExporterBuildTrait {
    use ExporterBuildCollectTrait;

    /**
     * Build a ZIP archive containing the full snapshot + all incremental children.
     *
     * @param array $snapshot The full snapshot record.
     * @return array {success: bool, export?: array, error?: string}
     */
    private function buildZip($snapshot) {
        $snapshotId = (int) $snapshot['id'];
        $snapshotDir = dirname($snapshot['filepath']);

        $this->log(LogLevelType::Info->value, 'Building ZIP export', array('snapshot_id' => $snapshotId, 'dir' => basename($snapshotDir)));

        $exportsDir = $this->ensureExportsDir();
        if (!$exportsDir) {
            return array('success' => false, 'error' => 'Failed to create exports directory', 'code' => SnapshotErrorType::ExportBuildFailed->value);
        }

        $zipMeta = $this->prepareZipPaths($exportsDir, $snapshot);
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return array('success' => false, 'error' => 'Database unavailable', 'code' => SnapshotErrorType::ExportBuildFailed->value);
        }

        $this->insertBuildingRecord($pdo, $snapshotId, $zipMeta['filename'], $zipMeta['path']);

        return $this->attemptZipAssembly($pdo, $snapshot, $snapshotId, $snapshotDir, $zipMeta);
    }

    /** Ensure the exports directory exists with security files. */
    private function ensureExportsDir(): ?string {
        $exportsDir = PathHelper::getSnapshotsDir() . PathSubdirType::Exports->value;
        if (!PathHelper::isDirMissing($exportsDir)) {
            return $exportsDir;
        }

        if (!wp_mkdir_p($exportsDir)) {
            return null;
        }

        @file_put_contents($exportsDir . '/.htaccess', "deny from all\n");
        @file_put_contents($exportsDir . '/index.php', "<?php // Silence is golden.\n");

        return $exportsDir;
    }

    /** Prepare ZIP filename and path. */
    private function prepareZipPaths(string $exportsDir, array $snapshot): array {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $snapshot['filename']);
        $zipFilename = $safeName . '_export.zip';

        return array('filename' => $zipFilename, 'path' => $exportsDir . '/' . $zipFilename);
    }

    /** Attempt ZIP assembly with error cleanup. */
    private function attemptZipAssembly(PDO $pdo, array $snapshot, int $snapshotId, string $snapshotDir, array $zipMeta): array {
        try {
            return $this->assembleZipArchive($pdo, $snapshot, $snapshotId, $snapshotDir, $zipMeta['path'], $zipMeta['filename']);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'ZIP export build failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->cleanupFailedExport($pdo, $snapshotId, $zipMeta['path']);

            return array('success' => false, 'error' => 'ZIP build failed: ' . $e->getMessage(), 'code' => SnapshotErrorType::ExportBuildFailed->value);
        }
    }

    /** Clean up after a failed export build. */
    private function cleanupFailedExport(PDO $pdo, int $snapshotId, string $zipPath) {
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ?');
        $stmt->execute(array($snapshotId));
    }

    /** Insert a "building" export record. */
    private function insertBuildingRecord(PDO $pdo, int $snapshotId, string $zipFilename, string $zipPath) {
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO ' . TableType::SnapshotExports->value .
            ' (snapshot_id, zip_filename, zip_path, zip_size, included_ids, incremental_count, status, created_at)' .
            ' VALUES (?, ?, ?, 0, ?, 0, ?, datetime(\'now\'))'
        );
        $stmt->execute(array($snapshotId, $zipFilename, $zipPath, json_encode(array($snapshotId)), SnapshotExportStatusType::Building->value));
    }

    /** Assemble the ZIP archive with full + incremental + manifest. */
    private function assembleZipArchive(PDO $pdo, array $snapshot, int $snapshotId, string $snapshotDir, string $zipPath, string $zipFilename): array {
        $files = $this->collectSnapshotFiles($snapshotDir);
        if (empty($files)) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);

            return array('success' => false, 'error' => 'No snapshot files found to export', 'code' => SnapshotErrorType::ExportBuildFailed->value);
        }

        $incrementalData = $this->gatherIncrementalData($snapshotId, $snapshot, $snapshotDir);

        $zip = $this->openZipForExport($zipPath, $pdo, $snapshotId);
        $this->populateZipArchive($zip, $files, $incrementalData, $snapshot, $snapshotId);
        $zip->close();

        $this->finalizeExportRecord($pdo, $snapshotId, $incrementalData['included_ids'], $incrementalData['incrementals'], $zipPath, $zipFilename);
        $export = $this->getValidExport($snapshotId);

        return array('success' => true, 'cached' => false, 'export' => $export);
    }

    /** Gather incremental snapshot data for ZIP assembly. */
    private function gatherIncrementalData(int $snapshotId, array $snapshot, string $snapshotDir): array {
        $incrementals = $this->getIncrementalSnapshots($snapshotId, $snapshot['filename']);
        $incrementalDir = $snapshotDir . '/incremental';
        $incrementalFiles = is_dir($incrementalDir) ? $this->collectIncrementalFiles($incrementalDir) : array();
        $includedIds = array($snapshotId);

        foreach ($incrementals as $inc) {
            $includedIds[] = (int) $inc['id'];
        }

        return array('incrementals' => $incrementals, 'files' => $incrementalFiles, 'included_ids' => $includedIds);
    }

    /** Open a new ZIP archive for writing. */
    private function openZipForExport(string $zipPath, PDO $pdo, int $snapshotId): ZipArchive {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);
            throw new Exception('Failed to create ZIP archive (error code: ' . $openResult . ')');
        }

        $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);

        return $zip;
    }

    /** Populate ZIP with snapshot files, incrementals, and manifest. */
    private function populateZipArchive(ZipArchive $zip, array $files, array $incrementalData, array $snapshot, int $snapshotId) {
        $this->addFilesToZip($zip, $files, '');
        $this->addFilesToZip($zip, $incrementalData['files'], 'incremental/');
        $this->addManifestToZip($zip, $snapshot, $snapshotId, $incrementalData['included_ids'], $incrementalData['incrementals']);
    }

    /** Add files to ZIP with compression. */
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

    /** Add manifest.json to ZIP archive. */
    private function addManifestToZip(ZipArchive $zip, array $snapshot, int $snapshotId, array $includedIds, array $incrementals) {
        $manifest = array(
            'version' => PluginConfigType::Version->value, 'created_at' => gmdate('c'), 'snapshot_id' => $snapshotId,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'] ?? '[]', true),
            'total_rows' => (int) ($snapshot['total_rows'] ?? 0), 'included_ids' => $includedIds,
            'incremental_count' => count($incrementals), 'type' => 'full_with_incrementals',
        );
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** Finalize the export record after ZIP creation. */
    private function finalizeExportRecord(PDO $pdo, int $snapshotId, array $includedIds, array $incrementals, string $zipPath, string $zipFilename) {
        $zipSize = filesize($zipPath);
        $stmt = $pdo->prepare(
            'UPDATE ' . TableType::SnapshotExports->value . ' SET status = ?, zip_size = ?, included_ids = ?, incremental_count = ? WHERE snapshot_id = ?'
        );
        $stmt->execute(array(SnapshotExportStatusType::Valid->value, $zipSize, json_encode($includedIds), count($incrementals), $snapshotId));

        $this->log(LogLevelType::Info->value, 'ZIP export built successfully', array(
            'snapshot_id' => $snapshotId, 'filename' => $zipFilename, 'size' => PathHelper::formatBytes($zipSize),
        ));
    }
}
