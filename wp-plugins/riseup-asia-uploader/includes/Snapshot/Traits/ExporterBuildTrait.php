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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\DateHelper;

trait ExporterBuildTrait {
    use ExporterBuildCollectTrait;

    private function buildZip($snapshot) {
        $snapshotId = (int) $snapshot['id'];
        $snapshotDir = dirname($snapshot['filepath']);

        $this->log(LogLevelType::Info->value, 'Building ZIP export', array('snapshot_id' => $snapshotId, 'dir' => basename($snapshotDir)));

        $exportsDir = $this->ensureExportsDir();
        $isExportsDirMissing = ($exportsDir === null);
        if ($isExportsDirMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Failed to create exports directory', ResponseKeyType::Code->value => SnapshotErrorType::ExportBuildFailed->value);
        }

        $zipMeta = $this->prepareZipPaths($exportsDir, $snapshot);
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Database unavailable', ResponseKeyType::Code->value => SnapshotErrorType::ExportBuildFailed->value);
        }

        $this->insertBuildingRecord($pdo, $snapshotId, $zipMeta['filename'], $zipMeta['path']);

        return $this->attemptZipAssembly($pdo, $snapshot, $snapshotId, $snapshotDir, $zipMeta);
    }

    private function ensureExportsDir(): ?string {
        $exportsDir = PathHelper::getSnapshotsDir() . PathSubdirType::Exports->value;
        if (PathHelper::dirExists($exportsDir)) {
            return $exportsDir;
        }

        $isMkdirFailed = (wp_mkdir_p($exportsDir) === false);
        if ($isMkdirFailed) {
            return null;
        }

        @file_put_contents($exportsDir . '/.htaccess', "deny from all\n");
        @file_put_contents($exportsDir . '/index.php', "<?php // Silence is golden.\n");

        return $exportsDir;
    }

    private function prepareZipPaths(string $exportsDir, array $snapshot): array {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $snapshot['filename']);
        $zipFilename = $safeName . '_export.zip';

        return array('filename' => $zipFilename, 'path' => $exportsDir . '/' . $zipFilename);
    }

    private function attemptZipAssembly(
        PDO $pdo,
        array $snapshot,
        int $snapshotId,
        string $snapshotDir,
        array $zipMeta,
    ): array {
        try {

            return $this->assembleZipArchive($pdo, $snapshot, $snapshotId, $snapshotDir, $zipMeta['path'], $zipMeta['filename']);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'ZIP export build failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->cleanupFailedExport($pdo, $snapshotId, $zipMeta['path']);

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'ZIP build failed: ' . $e->getMessage(), ResponseKeyType::Code->value => SnapshotErrorType::ExportBuildFailed->value);
        }
    }

    private function cleanupFailedExport(
        PDO $pdo,
        int $snapshotId,
        string $zipPath,
    ) {
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ?');
        $stmt->execute(array($snapshotId));
    }

    private function insertBuildingRecord(
        PDO $pdo,
        int $snapshotId,
        string $zipFilename,
        string $zipPath,
    ) {
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO ' . TableType::SnapshotExports->value .
            ' (snapshot_id, zip_filename, zip_path, zip_size, included_ids, incremental_count, status, created_at)' .
            ' VALUES (?, ?, ?, 0, ?, 0, ?, datetime(\'now\'))'
        );
        $stmt->execute(array($snapshotId, $zipFilename, $zipPath, json_encode(array($snapshotId)), SnapshotExportStatusType::Building->value));
    }

    private function assembleZipArchive(
        PDO $pdo,
        array $snapshot,
        int $snapshotId,
        string $snapshotDir,
        string $zipPath,
        string $zipFilename,
    ): array {
        $files = $this->collectSnapshotFiles($snapshotDir);
        if (empty($files)) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'No snapshot files found to export', ResponseKeyType::Code->value => SnapshotErrorType::ExportBuildFailed->value);
        }

        $incrementalData = $this->gatherIncrementalData($snapshotId, $snapshot, $snapshotDir);

        $zip = $this->openZipForExport($zipPath, $pdo, $snapshotId);
        $this->populateZipArchive($zip, $files, $incrementalData, $snapshot, $snapshotId);
        $zip->close();

        $this->finalizeExportRecord($pdo, $snapshotId, $incrementalData['included_ids'], $incrementalData['incrementals'], $zipPath, $zipFilename);
        $export = $this->getValidExport($snapshotId);

        return array(ResponseKeyType::Success->value => true, 'cached' => false, 'export' => $export);
    }

    private function gatherIncrementalData(
        int $snapshotId,
        array $snapshot,
        string $snapshotDir,
    ): array {
        $incrementals = $this->getIncrementalSnapshots($snapshotId, $snapshot['filename']);
        $incrementalDir = $snapshotDir . '/incremental';
        $incrementalFiles = is_dir($incrementalDir) ? $this->collectIncrementalFiles($incrementalDir) : array();
        $includedIds = array($snapshotId);

        foreach ($incrementals as $inc) {
            $includedIds[] = (int) $inc['id'];
        }

        return array('incrementals' => $incrementals, 'files' => $incrementalFiles, 'included_ids' => $includedIds);
    }

    private function openZipForExport(
        string $zipPath,
        PDO $pdo,
        int $snapshotId,
    ): ZipArchive {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);

            throw new Exception('Failed to create ZIP archive (error code: ' . $openResult . ')');
        }

        $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);

        return $zip;
    }

    private function populateZipArchive(
        ZipArchive $zip,
        array $files,
        array $incrementalData,
        array $snapshot,
        int $snapshotId,
    ) {
        $this->addFilesToZip($zip, $files, '');
        $this->addFilesToZip($zip, $incrementalData['files'], 'incremental/');
        $this->addManifestToZip($zip, $snapshot, $snapshotId, $incrementalData['included_ids'], $incrementalData['incrementals']);
    }

    private function addFilesToZip(
        ZipArchive $zip,
        array $files,
        string $prefix,
    ) {
        foreach ($files as $absolutePath => $relativePath) {
            $entryName = $prefix . $relativePath;
            $zip->addFile($absolutePath, $entryName);
            $fileIndex = $zip->locateName($entryName);
            if ($fileIndex !== false) {
                $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE);
            }
        }
    }

    private function addManifestToZip(
        ZipArchive $zip,
        array $snapshot,
        int $snapshotId,
        array $includedIds,
        array $incrementals,
    ) {
        $manifest = array(
            'version' => PluginConfigType::Version->value, 'created_at' => DateHelper::nowIso(), 'snapshot_id' => $snapshotId,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'] ?? '[]', true),
            'total_rows' => (int) ($snapshot['total_rows'] ?? 0), 'included_ids' => $includedIds,
            'incremental_count' => count($incrementals), 'type' => 'full_with_incrementals',
        );
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function finalizeExportRecord(
        PDO $pdo,
        int $snapshotId,
        array $includedIds,
        array $incrementals,
        string $zipPath,
        string $zipFilename,
    ) {
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
