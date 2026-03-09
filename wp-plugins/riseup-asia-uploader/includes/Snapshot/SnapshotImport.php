<?php
/**
 * Riseup Asia Uploader - Snapshot Import
 *
 * @package RiseupAsia\Snapshot
 * @since   1.12.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use Throwable;
use ZipArchive;

use RiseupAsia\Database\Database;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Snapshot\Traits\ImportExecutionTrait;
use RiseupAsia\Snapshot\Traits\ImportValidationTrait;

class SnapshotImport {
    use ImportValidationTrait;
    use ImportExecutionTrait;

    private FileLogger $logger;
    private Database $db;
    private SnapshotManager $manager;
    private string $baseDir;
    private array $validationErrors = array();

    public function __construct(
        FileLogger $logger,
        Database $db,
        SnapshotManager $manager,
    ) {
        $this->logger  = $logger;
        $this->db      = $db;
        $this->manager = $manager;
        $this->baseDir = PathHelper::getBaseDir();
    }

    public function import(string $uploadedPath): array {
        $guardError = $this->guardImportFile($uploadedPath);

        if ($guardError) {
            return $guardError;
        }

        $this->log(LogLevelType::Info->value, 'Starting snapshot import', array(
            'path' => basename($uploadedPath),
            'size' => PathHelper::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = PathHelper::join(PathHelper::getTempDir(), 'import_' . uniqid());
        $isDirCreationFailed = (PathHelper::makeDirectory($tempDir, false) === false);

        if ($isDirCreationFailed) {
            return $this->fail(ResponseMessageType::TempDirCreateFailed->value);
        }

        return $this->extractAndImport($uploadedPath, $tempDir);
    }

    private function guardImportFile(string $path): ?array {
        if (PathHelper::isFileMissing($path)) {
            return $this->fail(ResponseMessageType::UploadedFileMissing->value);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            return $this->fail(ResponseMessageType::InvalidFileTypeZip->value);
        }

        return null;
    }

    private function extractAndImport(string $uploadedPath, string $tempDir): array {
        try {
            $this->extractZipTo($uploadedPath, $tempDir);
            $rootDbPath = $this->findFileRecursive($tempDir, SnapshotConfigType::RootDbFilename);
            $result = ($rootDbPath !== null)
                ? $this->importPerTable($tempDir, $rootDbPath)
                : $this->manager->importSnapshot($uploadedPath);
            $this->deleteDirectory($tempDir);

            return $result;
        } catch (Throwable $e) {
            $this->cleanupOnFailure($tempDir, $e);

            return $this->fail($e->getMessage());
        }
    }

    private function extractZipTo(string $zipPath, string $destDir): void {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }

        $isExtracted = $zip->extractTo($destDir);
        $zip->close();

        if ($isExtracted === false) {
            throw new Exception('Failed to extract ZIP contents to: ' . $destDir);
        }
    }

    private function cleanupOnFailure(string $tempDir, Throwable $e): void {
        if (PathHelper::dirExists($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        $this->logError($e, 'Snapshot import failed');
    }

    private function logError(Throwable $e, string $message, array $context = array()): void {
        $context['error'] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Error->value, $message, $context);
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $method = strtolower($level);

        if (method_exists($this->logger, $method)) {
            $this->logger->$method('[SnapshotImport] ' . $message, $context);
        }
    }

    private function fail(string $message): array {
        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => $message,
        );
    }
}
