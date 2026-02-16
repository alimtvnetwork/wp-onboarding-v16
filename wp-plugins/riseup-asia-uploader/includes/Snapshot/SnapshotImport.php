<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Snapshot\Traits\ImportValidationTrait;
use RiseupAsia\Snapshot\Traits\ImportExecutionTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Helpers\PathHelper;

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
        if ($guardError) { return $guardError; }

        $this->log(LogLevelType::Info->value, 'Starting snapshot import', array(
            'path' => basename($uploadedPath),
            'size' => PathHelper::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = PathHelper::join(PathHelper::getTempDir(), 'import_' . uniqid());
        if (!PathHelper::ensureDir($tempDir, false)) {
            return $this->fail('Failed to create temp directory');
        }

        return $this->extractAndImport($uploadedPath, $tempDir);
    }

    private function guardImportFile(string $path): ?array {
        if (!PathHelper::fileExists($path)) { return $this->fail('Uploaded file not found'); }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') { return $this->fail('Invalid file type. Expected ZIP file.'); }

        return null;
    }

    private function extractAndImport(string $uploadedPath, string $tempDir): array {
        try {
            $this->extractZipTo($uploadedPath, $tempDir);
            $rootDbPath = $this->findFileRecursive($tempDir, 'a-root.db');
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
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) { throw new \Exception('Failed to open ZIP file'); }
        $zip->extractTo($destDir);
        $zip->close();
    }

    private function cleanupOnFailure(string $tempDir, Throwable $e): void {
        if (PathHelper::dirExists($tempDir)) { $this->deleteDirectory($tempDir); }
        $this->log(LogLevelType::Error->value, 'Snapshot import failed', array('error' => $e->getMessage()));
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) { $this->logger->$method('[SnapshotImport] ' . $message, $context); }
    }

    private function fail(string $message): array {
        return array('success' => false, 'error' => $message);
    }
}
