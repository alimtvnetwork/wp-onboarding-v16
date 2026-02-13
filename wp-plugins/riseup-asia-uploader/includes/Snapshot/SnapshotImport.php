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
        if (!RiseupPathUtils::fileExists($uploadedPath)) {
            return $this->fail('Uploaded file not found');
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $this->fail('Invalid file type. Expected ZIP file.');
        }

        $fileSize = filesize($uploadedPath);
        $this->log('INFO', 'Starting snapshot import', array('path' => basename($uploadedPath), 'size' => RiseupPathUtils::formatBytes($fileSize)));

        $tempDir = RiseupPathUtils::join(RiseupPathUtils::getTempDir(), 'import_' . uniqid());
        if (!RiseupPathUtils::ensureDir($tempDir, false)) {
            return $this->fail('Failed to create temp directory');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($uploadedPath) !== true) {
                throw new Exception('Failed to open ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $rootDbPath = $this->findFileRecursive($tempDir, 'a-root.db');
            $result = ($rootDbPath !== null)
                ? $this->importPerTable($tempDir, $rootDbPath)
                : $this->manager->importSnapshot($uploadedPath);

            $this->deleteDirectory($tempDir);
            return $result;
        } catch (Exception $e) {
            if (RiseupPathUtils::dirExists($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            $this->log('ERROR', 'Snapshot import failed', array('error' => $e->getMessage()));
            return $this->fail($e->getMessage());
        }
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
