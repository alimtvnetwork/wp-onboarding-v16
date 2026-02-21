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
use RiseupAsia\Helpers\ResultHelper;

trait ExporterBuildTrait {
    use ExporterBuildCollectTrait;

    private function buildZip($snapshot) {
        $snapshotId = (int) $snapshot['id'];
        $snapshotDir = dirname($snapshot['filepath']);

        $this->log(LogLevelType::Info->value, 'Building ZIP export', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            'dir' => basename($snapshotDir),
        ));

        $exportsDir = $this->ensureExportsDir();
        $isExportsDirMissing = ($exportsDir === null);
        if ($isExportsDirMissing) {

            return ResultHelper::errorWithCode(
                'Failed to create exports directory',
                SnapshotErrorType::ExportBuildFailed->value,
            );
        }

        $zipMeta = $this->prepareZipPaths($exportsDir, $snapshot);
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {

            return ResultHelper::errorWithCode(
                'Database unavailable',
                SnapshotErrorType::ExportBuildFailed->value,
            );
        }

        $this->insertBuildingRecord($pdo, $snapshotId, $zipMeta[ResponseKeyType::Filename->value], $zipMeta[ResponseKeyType::Path->value]);

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

        return array(
            ResponseKeyType::Filename->value => $zipFilename,
            ResponseKeyType::Path->value     => $exportsDir . '/' . $zipFilename,
        );
    }

    private function attemptZipAssembly(
        PDO $pdo,
        array $snapshot,
        int $snapshotId,
        string $snapshotDir,
        array $zipMeta,
    ): array {
        try {

            return $this->assembleZipArchive($pdo, $snapshot, $snapshotId, $snapshotDir, $zipMeta[ResponseKeyType::Path->value], $zipMeta[ResponseKeyType::Filename->value]);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'ZIP export build failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->cleanupFailedExport($pdo, $snapshotId, $zipMeta[ResponseKeyType::Path->value]);

            return ResultHelper::errorWithCode(
                'ZIP build failed: ' . $e->getMessage(),
                SnapshotErrorType::ExportBuildFailed->value,
            );
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

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE SnapshotId = ?');
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
            ' (SnapshotId, ZipFilename, ZipPath, ZipSize, IncludedIds, IncrementalCount, Status, CreatedAt)' .
            ' VALUES (?, ?, ?, 0, ?, 0, ?, datetime(\'now\'))'
        );
        $stmt->execute(array(
            $snapshotId,
            $zipFilename,
            $zipPath,
            json_encode(array($snapshotId)),
            SnapshotExportStatusType::Building->value,
        ));
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

            return ResultHelper::errorWithCode(
                'No snapshot files found to export',
                SnapshotErrorType::ExportBuildFailed->value,
            );
        }

        $incrementalData = $this->gatherIncrementalData($snapshotId, $snapshot, $snapshotDir);

        $zip = $this->openZipForExport($zipPath, $pdo, $snapshotId);
        $this->populateZipArchive($zip, $files, $incrementalData, $snapshot, $snapshotId);
        $zip->close();

        $this->finalizeExportRecord($pdo, $snapshotId, $incrementalData['included_ids'], $incrementalData[ResponseKeyType::Incrementals->value], $zipPath, $zipFilename);
        $export = $this->getValidExport($snapshotId);

        return ResultHelper::ok(array(
            ResponseKeyType::Cached->value => false,
            ResponseKeyType::Export->value => $export,
        ));
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

        return array(
            ResponseKeyType::Incrementals->value => $incrementals,
            ResponseKeyType::Files->value        => $incrementalFiles,
            'included_ids'                       => $includedIds,
        );
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
        $this->addFilesToZip($zip, $incrementalData[ResponseKeyType::Files->value], 'incremental/');
        $this->addManifestToZip($zip, $snapshot, $snapshotId, $incrementalData['included_ids'], $incrementalData[ResponseKeyType::Incrementals->value]);
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
            'version' => PluginConfigType::Version->value,
            'created_at' => DateHelper::nowIso(),
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value => $snapshot['filename'],
            ResponseKeyType::Scope->value => $snapshot['scope'],
            ResponseKeyType::Tables->value => json_decode($snapshot['tables_json'] ?? '[]', true),
            ResponseKeyType::TotalRows->value => (int) ($snapshot['total_rows'] ?? 0),
            'included_ids' => $includedIds,
            'incremental_count' => count($incrementals),
            'type' => 'full_with_incrementals',
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
            'UPDATE ' . TableType::SnapshotExports->value . ' SET Status = ?, ZipSize = ?, IncludedIds = ?, IncrementalCount = ? WHERE SnapshotId = ?'
        );
        $stmt->execute(array($snapshotId, $zipFilename, $zipPath, json_encode(array($snapshotId)), SnapshotExportStatusType::Building->value));

        $this->log(LogLevelType::Info->value, 'ZIP export built successfully', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value => $zipFilename,
            ResponseKeyType::Size->value => PathHelper::formatBytes($zipSize),
        ));
    }
}
