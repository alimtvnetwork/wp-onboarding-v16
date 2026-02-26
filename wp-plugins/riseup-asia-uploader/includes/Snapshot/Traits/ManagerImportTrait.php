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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerImportTrait {
    use ManagerImportValidationTrait;
    use ManagerImportRecordTrait;

    public function importSnapshot(string $uploadedPath): array {
        if (PathHelper::isFileMissing($uploadedPath)) {
            return ResultHelper::error(ResponseMessageType::UploadedFileMissing->value);
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            return ResultHelper::error(ResponseMessageType::InvalidFileTypeZip->value);
        }

        $this->log(LogLevelType::Info->value, 'Importing snapshot from ZIP', array(
            ResponseKeyType::Path->value => $uploadedPath,
            ResponseKeyType::Size->value => PathHelper::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = PathHelper::join(PathHelper::getTempDir(), 'import_' . uniqid());
        $isDirCreationFailed = (PathHelper::makeDirectory($tempDir, false) === false);

        if ($isDirCreationFailed) {
            return ResultHelper::error(ResponseMessageType::TempDirCreateFailed->value);
        }

        try {
            $extracted = $this->extractAndValidateZip($uploadedPath, $tempDir);
            $result = $this->moveAndRecordSnapshot($extracted[ResponseKeyType::Manifest->value], $extracted[ResponseKeyType::SqlitePath->value], $tempDir);

            $this->deleteDirectory($tempDir);

            return $result;
        } catch (Throwable $e) {
            if (PathHelper::dirExists($tempDir)) {
                $this->deleteDirectory($tempDir);
            }

            $this->log(LogLevelType::Error->value, 'Snapshot import failed', array(ResponseKeyType::Error->value => $e->getMessage()));

            return ResultHelper::errorFromException($e);
        }
    }

    private function extractAndValidateZip(string $uploadedPath, string $tempDir): array {
        $this->extractZipToDir($uploadedPath, $tempDir);
        $manifest = $this->loadAndValidateManifest($tempDir);
        $sqlitePath = $this->validateSnapshotSqlite($manifest, $tempDir);

        return array(ResponseKeyType::Manifest->value => $manifest, ResponseKeyType::SqlitePath->value => $sqlitePath);
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
        $isManifestInvalid = ($manifest === null || $manifest === false);

        if ($isManifestInvalid) {
            throw new Exception('Invalid manifest.json format');
        }

        $validation = $this->validateManifest($manifest);
        $isValidationFailed = ($validation[ResponseKeyType::Valid->value] === false);

        if ($isValidationFailed) {
            throw new Exception('Manifest validation failed: ' . $validation[ResponseKeyType::Error->value]);
        }

        return $manifest;
    }

    private function validateSnapshotSqlite(
        array $manifest,
        string $tempDir,
    ): string {
        $sqliteFilename = $manifest['snapshot'][ResponseKeyType::Filename->value];
        $sqlitePath = PathHelper::join($tempDir, $sqliteFilename);

        if (PathHelper::isFileMissing($sqlitePath)) {
            throw new Exception('SQLite file not found in archive: ' . $sqliteFilename);
        }

        $integrity = $this->validateSqliteIntegrity($sqlitePath);
        $isIntegrityFailed = ($integrity[ResponseKeyType::Valid->value] === false);

        if ($isIntegrityFailed) {
            throw new Exception('SQLite integrity check failed: ' . $integrity[ResponseKeyType::Error->value]);
        }

        return $sqlitePath;
    }

    private function moveAndRecordSnapshot(
        array $manifest,
        string $sqlitePath,
        string $tempDir,
    ): array {
        $snapshotsDir = PathHelper::getSnapshotsDir();
        $isDirCreationFailed = (PathHelper::makeDirectory($snapshotsDir, true) === false);

        if ($isDirCreationFailed) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $sequence = $this->getNextImportSequence();
        $newFilename = sprintf('%03d_%s', $sequence, DateHelper::nowFilenameDatetime()) . '.sqlite';
        $destPath = PathHelper::join($snapshotsDir, $newFilename);

        if (PathHelper::isCopyFailed($sqlitePath, $destPath)) {
            throw new Exception('Failed to copy snapshot file to destination');
        }

        $snapshotId = $this->createImportedSnapshotRecord($manifest, $sequence, $newFilename, $destPath);
        $isRecordCreationFailed = ($snapshotId === null || $snapshotId === false || $snapshotId === 0);

        if ($isRecordCreationFailed) {
            PathHelper::deleteFile($destPath);

            throw new Exception('Failed to create snapshot record');
        }

        $this->log(LogLevelType::Info->value, 'Snapshot imported successfully', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $newFilename,
        ));

        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $newFilename,
            ResponseKeyType::Tables->value     => count($manifest['snapshot'][ResponseKeyType::Tables->value]),
            ResponseKeyType::Rows->value       => $manifest['snapshot'][ResponseKeyType::TotalRows->value],
        ));
    }
}
