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
        $guardError = $this->guardImportFile($uploadedPath);

        if ($guardError !== null) {
            return $guardError;
        }

        $this->logImportStart($uploadedPath);
        $tempDir = $this->createImportTempDir();

        if ($tempDir === null) {
            return ResultHelper::error(ResponseMessageType::TempDirCreateFailed->value);
        }

        return $this->extractValidateAndImport($uploadedPath, $tempDir);
    }

    private function guardImportFile(string $uploadedPath): ?array {
        if (PathHelper::isFileMissing($uploadedPath)) {
            return ResultHelper::error(ResponseMessageType::UploadedFileMissing->value);
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        $isNotZip = ($ext !== 'zip');

        if ($isNotZip) {
            return ResultHelper::error(ResponseMessageType::InvalidFileTypeZip->value);
        }

        return null;
    }

    private function logImportStart(string $uploadedPath): void {
        $this->log(LogLevelType::Info->value, 'Importing snapshot from ZIP', array(
            ResponseKeyType::Path->value => $uploadedPath,
            ResponseKeyType::Size->value => PathHelper::formatBytes(filesize($uploadedPath)),
        ));
    }

    private function createImportTempDir(): ?string {
        $tempDir = PathHelper::join(PathHelper::getTempDir(), 'import_' . uniqid());
        $isDirCreationFailed = (PathHelper::makeDirectory($tempDir, false) === false);

        return $isDirCreationFailed ? null : $tempDir;
    }

    private function extractValidateAndImport(string $uploadedPath, string $tempDir): array {
        try {
            $extracted = $this->extractAndValidateZip($uploadedPath, $tempDir);
            $result = $this->moveAndRecordSnapshot($extracted[ResponseKeyType::Manifest->value], $extracted[ResponseKeyType::SqlitePath->value], $tempDir);

            $this->deleteDirectory($tempDir);

            return $result;
        } catch (Throwable $e) {
            $this->cleanupTempDir($tempDir);
            $this->logError($e, 'Snapshot import failed');

            return ResultHelper::errorFromException($e);
        }
    }

    private function cleanupTempDir(string $tempDir): void {
        if (PathHelper::dirExists($tempDir)) {
            $this->deleteDirectory($tempDir);
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

        $isExtracted = $zip->extractTo($tempDir);
        $zip->close();

        if ($isExtracted === false) {
            throw new Exception('Failed to extract ZIP contents to: ' . $tempDir);
        }
    }

    private function loadAndValidateManifest(string $tempDir): array {
        $manifestPath = PathHelper::join($tempDir, 'manifest.json');

        if (PathHelper::isFileMissing($manifestPath)) {
            throw new Exception('Invalid snapshot archive: manifest.json not found');
        }

        $manifest = $this->parseManifestJson($manifestPath);
        $this->validateParsedManifest($manifest);

        return $manifest;
    }

    private function parseManifestJson(string $manifestPath): array {
        $rawContent = @file_get_contents($manifestPath);

        if ($rawContent === false) {
            throw new Exception('Failed to read manifest.json');
        }

        $manifest = json_decode($rawContent, true);
        $isManifestInvalid = ($manifest === null || $manifest === false);

        if ($isManifestInvalid) {
            throw new Exception('Invalid manifest.json format');
        }

        return $manifest;
    }

    private function validateParsedManifest(array $manifest): void {
        $validation = $this->validateManifest($manifest);
        $isValidationFailed = ($validation[ResponseKeyType::Valid->value] === false);

        if ($isValidationFailed) {
            throw new Exception('Manifest validation failed: ' . $validation[ResponseKeyType::Error->value]);
        }
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
        $destPath = $this->prepareDestination();
        $snapshotId = $this->copyAndCreateRecord($manifest, $sqlitePath, $destPath);

        $this->logImportSuccess($snapshotId, $destPath[ResponseKeyType::Filename->value]);

        return $this->buildImportSuccessResult($snapshotId, $destPath[ResponseKeyType::Filename->value], $manifest);
    }

    private function prepareDestination(): array {
        $snapshotsDir = PathHelper::getSnapshotsDir();
        $isDirCreationFailed = (PathHelper::makeDirectory($snapshotsDir, true) === false);

        if ($isDirCreationFailed) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $sequence = $this->getNextImportSequence();
        $newFilename = sprintf('%03d_%s', $sequence, DateHelper::nowFilenameDatetime()) . '.sqlite';

        return array(
            ResponseKeyType::Sequence->value => $sequence,
            ResponseKeyType::Filename->value => $newFilename,
            ResponseKeyType::Path->value     => PathHelper::join($snapshotsDir, $newFilename),
        );
    }

    private function copyAndCreateRecord(array $manifest, string $sqlitePath, array $destPath): int {
        if (PathHelper::isCopyFailed($sqlitePath, $destPath[ResponseKeyType::Path->value])) {
            throw new Exception('Failed to copy snapshot file to destination');
        }

        $snapshotId = $this->createImportedSnapshotRecord(
            $manifest,
            $destPath[ResponseKeyType::Sequence->value],
            $destPath[ResponseKeyType::Filename->value],
            $destPath[ResponseKeyType::Path->value],
        );

        $isRecordCreationFailed = ($snapshotId === null || $snapshotId === false || $snapshotId === 0);

        if ($isRecordCreationFailed) {
            PathHelper::deleteFile($destPath[ResponseKeyType::Path->value]);

            throw new Exception('Failed to create snapshot record');
        }

        return $snapshotId;
    }

    private function logImportSuccess(int $snapshotId, string $filename): void {
        $this->log(LogLevelType::Info->value, 'Snapshot imported successfully', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $filename,
        ));
    }

    private function buildImportSuccessResult(int $snapshotId, string $filename, array $manifest): array {
        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $filename,
            ResponseKeyType::Tables->value     => count($manifest['snapshot'][ResponseKeyType::Tables->value]),
            ResponseKeyType::Rows->value       => $manifest['snapshot'][ResponseKeyType::TotalRows->value],
        ));
    }
}
