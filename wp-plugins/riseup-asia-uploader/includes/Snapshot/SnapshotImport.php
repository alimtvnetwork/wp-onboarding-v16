<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Snapshot\Traits\ImportValidationTrait;
use RiseupAsia\Snapshot\Traits\ImportExecutionTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Helpers\PathUtils;

class SnapshotImport {
    use ImportValidationTrait;
    use ImportExecutionTrait;

    private FileLogger $logger;
    private Database $db;
    private SnapshotManager $manager;
    private string $baseDir;
    private array $validationErrors = array();

    public function __construct(FileLogger $logger, Database $db, SnapshotManager $manager) {
        $this->logger  = $logger;
        $this->db      = $db;
        $this->manager = $manager;
        $this->baseDir = PathUtils::getBaseDir();
    }

    /**
     * Import a snapshot from an uploaded ZIP file.
     *
     * @param string $uploadedPath Path to uploaded ZIP file.
     * @return array Result with success status and snapshot details.
     */
    public function import(string $uploadedPath): array {
        $guardError = $this->guardImportFile($uploadedPath);
        if ($guardError) { return $guardError; }

        $this->log(LogLevelType::Info->value, 'Starting snapshot import', array(
            'path' => basename($uploadedPath),
            'size' => PathUtils::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = PathUtils::join(PathUtils::getTempDir(), 'import_' . uniqid());
        if (!PathUtils::ensureDir($tempDir, false)) {
            return $this->fail('Failed to create temp directory');
        }

        return $this->extractAndImport($uploadedPath, $tempDir);
    }

    /**
     * Validate the uploaded file exists and is a ZIP.
     *
     * @param string $path File path.
     * @return array|null Failure result or null if valid.
     */
    private function guardImportFile(string $path): ?array {
        if (!PathUtils::fileExists($path)) { return $this->fail('Uploaded file not found'); }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') { return $this->fail('Invalid file type. Expected ZIP file.'); }
        return null;
    }

    /**
     * Extract ZIP and run appropriate import strategy.
     *
     * @param string $uploadedPath Path to ZIP file.
     * @param string $tempDir      Temporary extraction directory.
     * @return array Import result.
     */
    private function extractAndImport(string $uploadedPath, string $tempDir): array {
        try {
            $this->extractZipTo($uploadedPath, $tempDir);
            $rootDbPath = $this->findFileRecursive($tempDir, 'a-root.db');
            $result = ($rootDbPath !== null)
                ? $this->importPerTable($tempDir, $rootDbPath)
                : $this->manager->importSnapshot($uploadedPath);
            $this->deleteDirectory($tempDir);
            return $result;
        } catch (\Throwable $e) {
            $this->cleanupOnFailure($tempDir, $e);
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Extract a ZIP file to a directory.
     *
     * @param string $zipPath Path to ZIP.
     * @param string $destDir Destination directory.
     * @throws Exception On extraction failure.
     */
    private function extractZipTo(string $zipPath, string $destDir): void {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) { throw new \Exception('Failed to open ZIP file'); }
        $zip->extractTo($destDir);
        $zip->close();
    }

    /**
     * Clean up temp directory and log error on failure.
     *
     * @param string    $tempDir Temp directory.
     * @param Exception $e       The exception.
     */
    private function cleanupOnFailure(string $tempDir, \Throwable $e): void {
        if (PathUtils::dirExists($tempDir)) { $this->deleteDirectory($tempDir); }
        $this->log(LogLevelType::Error->value, 'Snapshot import failed', array('error' => $e->getMessage()));
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log(string $level, string $message, array $context = array()): void {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) { $this->logger->$method('[SnapshotImport] ' . $message, $context); }
    }

    /**
     * Return a failure result.
     *
     * @param string $message Error message.
     * @return array
     */
    private function fail(string $message): array {
        return array('success' => false, 'error' => $message);
    }
}

class_alias(SnapshotImport::class, 'RiseupSnapshotImport');
