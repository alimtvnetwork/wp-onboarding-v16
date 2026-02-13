<?php
/**
 * Riseup Asia Uploader - Snapshot Import Engine
 *
 * Shell class delegating to ImportValidationTrait and ImportExecutionTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.16.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once dirname(__FILE__) . '/Traits/ImportValidationTrait.php';
require_once dirname(__FILE__) . '/Traits/ImportExecutionTrait.php';

/**
 * Snapshot Import Engine.
 */
class RiseupSnapshotImport {

    use ImportValidationTrait;
    use ImportExecutionTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotManager */
    private $manager;

    /** @var string */
    private $baseDir;

    /** @var array */
    private $validationErrors = array();

    /**
     * Constructor.
     *
     * @param RiseupFileLogger      $logger  Logger instance.
     * @param RiseupDatabase        $db      Database instance.
     * @param RiseupSnapshotManager $manager Snapshot manager.
     */
    public function __construct($logger, $db, $manager) {
        $this->logger  = $logger;
        $this->db      = $db;
        $this->manager = $manager;
        $this->baseDir = RiseupPathUtils::getBaseDir();
    }

    /**
     * Import a snapshot from an uploaded ZIP file.
     *
     * @param string $uploadedPath Path to uploaded ZIP file.
     * @return array Result with success status and snapshot details.
     */
    public function import($uploadedPath) {
        $guardError = $this->guardImportFile($uploadedPath);
        if ($guardError) {
            return $guardError;
        }

        $this->log(LogLevelType::Info->value, 'Starting snapshot import', array(
            'path' => basename($uploadedPath),
            'size' => RiseupPathUtils::formatBytes(filesize($uploadedPath)),
        ));

        $tempDir = RiseupPathUtils::join(RiseupPathUtils::getTempDir(), 'import_' . uniqid());
        if (!RiseupPathUtils::ensureDir($tempDir, false)) {
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
        if (!RiseupPathUtils::fileExists($path)) {
            return $this->fail('Uploaded file not found');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $this->fail('Invalid file type. Expected ZIP file.');
        }
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
        } catch (Exception $e) {
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
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }
        $zip->extractTo($destDir);
        $zip->close();
    }

    /**
     * Clean up temp directory and log error on failure.
     *
     * @param string    $tempDir Temp directory.
     * @param Exception $e       The exception.
     */
    private function cleanupOnFailure(string $tempDir, Exception $e): void {
        if (RiseupPathUtils::dirExists($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
        $this->log(LogLevelType::Error->value, 'Snapshot import failed', array('error' => $e->getMessage()));
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method('[SnapshotImport] ' . $message, $context);
        }
    }

    /**
     * Return a failure result.
     *
     * @param string $message Error message.
     * @return array
     */
    private function fail($message) {
        return array('success' => false, 'error' => $message);
    }
}
