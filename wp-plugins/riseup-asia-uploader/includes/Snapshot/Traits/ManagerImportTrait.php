<?php
/**
 * ManagerImportTrait — Snapshot ZIP import operations.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use Throwable;
use Exception;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\PathHelper;

trait ManagerImportTrait {

    use ManagerImportValidationTrait;
    use ManagerImportRecordTrait;

    public function importSnapshot(string $uploadedPath): array {
        if (PathHelper::isFileMissing($uploadedPath)) {
            return array('success' => false, 'error' => 'Uploaded file not found');
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return array('success' => false, 'error' => 'Invalid file type. Expected ZIP file.');
        }

        $this->log(LogLevelType::Info->value, 'Importing snapshot from ZIP', array(
            'path' => $uploadedPath, 'size' => PathHelper::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = PathHelper::join(PathHelper::getTempDir(), 'import_' . uniqid());
        $isDirCreationFailed = !PathHelper::ensureDir($tempDir, false);
        if ($isDirCreationFailed) {
            return array('success' => false, 'error' => 'Failed to create temp directory');
        }

        try {
            $extracted = $this->extractAndValidateZip($uploadedPath, $tempDir);
            $result = $this->moveAndRecordSnapshot($extracted['manifest'], $extracted['sqlite_path'], $tempDir);

            $this->deleteDirectory($tempDir);
            return $result;
        } catch (Throwable $e) {
            if (PathHelper::dirExists($tempDir)) {
                $this->deleteDirectory($tempDir);
            }

            $this->log(LogLevelType::Error->value, 'Snapshot import failed', array('error' => $e->getMessage()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private function extractAndValidateZip(string $uploadedPath, string $tempDir): array {
        $this->extractZipToDir($uploadedPath, $tempDir);
        $manifest = $this->loadAndValidateManifest($tempDir);
        $sqlitePath = $this->validateSnapshotSqlite($manifest, $tempDir);

        return array('manifest' => $manifest, 'sqlite_path' => $sqlitePath);
    }

    private function extractZipToDir(string $uploadedPath, string $tempDir): void {
        $zip = new ZipArchive();
        if ($zip->open($uploadedPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }
        $zip->extractTo($tempDir);
        $zip->close();
    }

    private function loadAndValidateManifest(string $tempDir): array {
        $manifestPath = PathHelper::join($tempDir, 'manifest.json');
        if (PathHelper::isFileMissing($manifestPath)) {
            throw new Exception('Invalid snapshot archive: manifest.json not found');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            throw new Exception('Invalid manifest.json format');
        }

        $validation = $this->validateManifest($manifest);
        if (!$validation['valid']) {
            throw new Exception('Manifest validation failed: ' . $validation['error']);
        }

        return $manifest;
    }

    private function validateSnapshotSqlite(
        array $manifest,
        string $tempDir,
    ): string {
        $sqliteFilename = $manifest['snapshot']['filename'];
        $sqlitePath = PathHelper::join($tempDir, $sqliteFilename);

        if (PathHelper::isFileMissing($sqlitePath)) {
            throw new Exception('SQLite file not found in archive: ' . $sqliteFilename);
        }

        $integrity = $this->validateSqliteIntegrity($sqlitePath);
        if (!$integrity['valid']) {
            throw new Exception('SQLite integrity check failed: ' . $integrity['error']);
        }

        return $sqlitePath;
    }

    private function moveAndRecordSnapshot(
        array $manifest,
        string $sqlitePath,
        string $tempDir,
    ): array {
        $snapshotsDir = PathHelper::getSnapshotsDir();
        $isDirCreationFailed = !PathHelper::ensureDir($snapshotsDir, true);
        if ($isDirCreationFailed) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $sequence = $this->getNextImportSequence();
        $newFilename = sprintf('%03d_%s', $sequence, date('Y-m-d_His')) . '.sqlite';
        $destPath = PathHelper::join($snapshotsDir, $newFilename);

        if (PathHelper::isCopyFailed($sqlitePath, $destPath)) {
            throw new Exception('Failed to copy snapshot file to destination');
        }

        $snapshotId = $this->createImportedSnapshotRecord($manifest, $sequence, $newFilename, $destPath);
        if (!$snapshotId) {
            PathHelper::deleteFile($destPath);
            throw new Exception('Failed to create snapshot record');
        }

        $this->log(LogLevelType::Info->value, 'Snapshot imported successfully', array(
            'snapshot_id' => $snapshotId, 'filename' => $newFilename,
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshotId, 'filename' => $newFilename,
            'tables' => count($manifest['snapshot']['tables']),
            'rows' => $manifest['snapshot']['total_rows'],
        );
    }
}
