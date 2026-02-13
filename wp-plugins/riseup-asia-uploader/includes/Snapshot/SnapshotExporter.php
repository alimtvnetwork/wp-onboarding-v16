<?php
/**
 * Riseup Asia Uploader - Snapshot ZIP Exporter
 *
 * Manages cached ZIP exports of database snapshots. Full snapshots are bundled
 * with their incremental children into a single ZIP file. The ZIP is cached on
 * disk and tracked in the snapshot_exports SQLite table. When a new incremental
 * backup is added to a full snapshot, the cached ZIP is invalidated.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot ZIP Exporter.
 *
 * Provides cached ZIP export generation, invalidation, and download URL creation.
 */
class RiseupSnapshotExporter {

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotExporter|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null $logger Logger.
     * @param RiseupDatabase|null    $db     Database.
     * @return RiseupSnapshotExporter
     */
    public static function getInstance($logger = null, $db = null) {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger.
     * @param RiseupDatabase    $db     Database.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db     = $db;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Get an existing valid ZIP or build a new one for the given full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array {success: bool, export?: array, error?: string}
     */
    public function getOrBuildZip($fullSnapshotId) {
        $this->log('INFO', 'getOrBuildZip called', array('snapshot_id' => $fullSnapshotId));

        // 1. Validate snapshot exists and is a full snapshot
        $snapshot = $this->getFullSnapshot($fullSnapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Full snapshot not found', 'code' => ERR_SNAPSHOT_NOT_FOUND);
        }

        // 2. Check for a valid cached export
        $existing = $this->getValidExport($fullSnapshotId);
        if ($existing && file_exists($existing['zip_path'])) {
            $this->log('INFO', 'Returning cached ZIP export', array(
                'export_id' => $existing['id'],
                'filename'  => $existing['zip_filename'],
            ));
            return array(
                'success' => true,
                'cached'  => true,
                'export'  => $existing,
            );
        }

        // 3. Clean up any stale record if file is missing
        if ($existing) {
            $this->deleteExportRecord($existing['id']);
        }

        // 4. Build new ZIP
        return $this->buildZip($snapshot);
    }

    /**
     * Invalidate (expire) the cached ZIP for a full snapshot.
     *
     * Called when a new incremental backup is added or content changes.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return bool True if an export was invalidated.
     */
    public function invalidateZip($fullSnapshotId) {
        $this->log('INFO', 'Invalidating ZIP export', array('snapshot_id' => $fullSnapshotId));

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        $export = $this->getValidExport($fullSnapshotId);
        if (!$export) {
            $this->log('DEBUG', 'No valid export to invalidate');
            return false;
        }

        // Delete the ZIP file from disk
        if (file_exists($export['zip_path'])) {
            @unlink($export['zip_path']);
            $this->log('INFO', 'Deleted cached ZIP file', array('path' => basename($export['zip_path'])));
        }

        // Mark as expired in DB
        $stmt = $pdo->prepare(
            'UPDATE ' . TABLE_SNAPSHOT_EXPORTS .
            ' SET status = ?, expires_at = datetime(\'now\') WHERE id = ?'
        );
        $stmt->execute(array(SNAPSHOT_EXPORT_STATUS_EXPIRED, $export['id']));

        $this->log('INFO', 'Export marked as expired', array('export_id' => $export['id']));
        return true;
    }

    /**
     * Remove all export records and files for a full snapshot.
     *
     * Called during cascade delete of a full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return void
     */
    public function removeExports($fullSnapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, zip_path FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE snapshot_id = ?'
        );
        $stmt->execute(array($fullSnapshotId));
        $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exports as $export) {
            if (!empty($export['zip_path']) && file_exists($export['zip_path'])) {
                @unlink($export['zip_path']);
                $this->log('DEBUG', 'Deleted export ZIP', array('path' => basename($export['zip_path'])));
            }
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE snapshot_id = ?');
        $stmt->execute(array($fullSnapshotId));

        $this->log('INFO', 'Removed all exports for snapshot', array(
            'snapshot_id' => $fullSnapshotId,
            'count'       => count($exports),
        ));
    }

    /**
     * Generate a time-limited download URL for an export.
     *
     * @param int $exportId The export record ID.
     * @return string|null Download URL or null if export not found.
     */
    public function getDownloadUrl($exportId) {
        $export = $this->getExportById($exportId);
        if (!$export || $export['status'] !== SNAPSHOT_EXPORT_STATUS_VALID) {
            return null;
        }

        // Create a nonce valid for 1 hour (WordPress nonces are valid for 12–24h,
        // but we'll verify our own timestamp too)
        $nonce = wp_create_nonce('riseup_snapshot_download_' . $exportId);

        return rest_url(
            API_FULL_NAMESPACE . '/' .
            ENDPOINT_SNAPSHOT_DOWNLOAD_FILE .
            '?token=' . $nonce .
            '&id=' . $exportId
        );
    }

    /**
     * Validate a download token and return the export record.
     *
     * @param int    $exportId The export ID.
     * @param string $token    The nonce token.
     * @return array|null The export record, or null if invalid.
     */
    public function validateDownloadToken($exportId, $token) {
        // Verify WordPress nonce
        $valid = wp_verify_nonce($token, 'riseup_snapshot_download_' . $exportId);
        if (!$valid) {
            $this->log('WARN', 'Invalid download token', array('export_id' => $exportId));
            return null;
        }

        $export = $this->getExportById($exportId);
        if (!$export) {
            $this->log('WARN', 'Export not found for download', array('export_id' => $exportId));
            return null;
        }

        if ($export['status'] !== SNAPSHOT_EXPORT_STATUS_VALID) {
            $this->log('WARN', 'Export is not valid', array('export_id' => $exportId, 'status' => $export['status']));
            return null;
        }

        if (RiseupBooleanHelpers::is_file_missing($export['zip_path'])) {
            $this->log('WARN', 'Export ZIP file missing', array('path' => $export['zip_path']));
            return null;
        }

        return $export;
    }

    /**
     * Get export status for a full snapshot (used by the UI to show cached/building/expired).
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array|null Export metadata or null if none exists.
     */
    public function getExportStatus($fullSnapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM ' . TABLE_SNAPSHOT_EXPORTS .
            ' WHERE snapshot_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(array($fullSnapshotId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Get a full snapshot record by ID (validates it's not incremental).
     *
     * @param int $snapshotId Snapshot ID.
     * @return array|null Snapshot record or null.
     */
    private function getFullSnapshot($snapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE id = ?');
        $stmt->execute(array($snapshotId));
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$snapshot) {
            return null;
        }

        // Must be a full snapshot (not incremental)
        if ($snapshot['scope'] === 'incremental') {
            $this->log('WARN', 'Cannot export incremental snapshot directly', array('id' => $snapshotId));
            return null;
        }

        if ($snapshot['status'] !== SNAPSHOT_STATUS_COMPLETE) {
            $this->log('WARN', 'Snapshot not complete', array('id' => $snapshotId, 'status' => $snapshot['status']));
            return null;
        }

        return $snapshot;
    }

    /**
     * Get a valid (non-expired) export record for a snapshot.
     *
     * @param int $snapshotId Full snapshot ID.
     * @return array|null Export record or null.
     */
    private function getValidExport($snapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM ' . TABLE_SNAPSHOT_EXPORTS .
            ' WHERE snapshot_id = ? AND status = ?'
        );
        $stmt->execute(array($snapshotId, SNAPSHOT_EXPORT_STATUS_VALID));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get an export record by ID.
     *
     * @param int $exportId Export ID.
     * @return array|null
     */
    private function getExportById($exportId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE id = ?');
        $stmt->execute(array($exportId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Delete an export record.
     *
     * @param int $exportId Export ID.
     * @return void
     */
    private function deleteExportRecord($exportId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE id = ?');
        $stmt->execute(array($exportId));
    }

    /**
     * Build a ZIP archive containing the full snapshot + all its incremental children.
     *
     * @param array $snapshot The full snapshot record.
     * @return array {success: bool, export?: array, error?: string}
     */
    private function buildZip($snapshot) {
        $snapshotId = (int) $snapshot['id'];
        $snapshotDir = dirname($snapshot['filepath']);

        $this->log('INFO', 'Building ZIP export', array(
            'snapshot_id' => $snapshotId,
            'dir'         => basename($snapshotDir),
        ));

        // Ensure exports directory exists
        $exportsDir = RiseupPathUtils::getSnapshotsDir() . '/' . SNAPSHOT_EXPORTS_SUBDIR;
        if (RiseupBooleanHelpers::is_dir_missing($exportsDir)) {
            if (!wp_mkdir_p($exportsDir)) {
                return array('success' => false, 'error' => 'Failed to create exports directory', 'code' => ERR_EXPORT_BUILD_FAILED);
            }
            // Add security files
            @file_put_contents($exportsDir . '/.htaccess', "deny from all\n");
            @file_put_contents($exportsDir . '/index.php', "<?php // Silence is golden.\n");
        }

        // Generate ZIP filename
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $snapshot['filename']);
        $zipFilename = $safeName . '_export.zip';
        $zipPath = $exportsDir . '/' . $zipFilename;

        // Mark as building (INSERT OR REPLACE to handle unique constraint)
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return array('success' => false, 'error' => 'Database unavailable', 'code' => ERR_EXPORT_BUILD_FAILED);
        }

        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO ' . TABLE_SNAPSHOT_EXPORTS .
            ' (snapshot_id, zip_filename, zip_path, zip_size, included_ids, incremental_count, status, created_at)' .
            ' VALUES (?, ?, ?, 0, ?, 0, ?, datetime(\'now\'))'
        );
        $stmt->execute(array(
            $snapshotId,
            $zipFilename,
            $zipPath,
            json_encode(array($snapshotId)),
            SNAPSHOT_EXPORT_STATUS_BUILDING,
        ));

        try {
            // Collect files to include
            $files = $this->collectSnapshotFiles($snapshotDir);

            if (empty($files)) {
                $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);
                return array('success' => false, 'error' => 'No snapshot files found to export', 'code' => ERR_EXPORT_BUILD_FAILED);
            }

            // Collect incremental children
            $incrementals = $this->getIncrementalSnapshots($snapshotId, $snapshot['filename']);
            $incrementalDir = $snapshotDir . '/incremental';
            $incrementalFiles = array();
            $includedIds = array($snapshotId);

            if (is_dir($incrementalDir)) {
                $incrementalFiles = $this->collectIncrementalFiles($incrementalDir);
            }

            foreach ($incrementals as $inc) {
                $includedIds[] = (int) $inc['id'];
            }

            // Create ZIP with maximum compression
            $zip = new ZipArchive();
            $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                $this->deleteExportRecord($pdo->lastInsertId() ?: $snapshotId);
                return array(
                    'success' => false,
                    'error'   => 'Failed to create ZIP archive (error code: ' . $openResult . ')',
                    'code'    => ERR_EXPORT_BUILD_FAILED,
                );
            }

            // Set maximum compression
            $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);

            // Add full snapshot files
            foreach ($files as $absolutePath => $relativePath) {
                $zip->addFile($absolutePath, $relativePath);
                // Apply max compression per file
                $fileIndex = $zip->locateName($relativePath);
                if ($fileIndex !== false) {
                    $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE);
                }
            }

            // Add incremental files
            foreach ($incrementalFiles as $absolutePath => $relativePath) {
                $zip->addFile($absolutePath, 'incremental/' . $relativePath);
                $fileIndex = $zip->locateName('incremental/' . $relativePath);
                if ($fileIndex !== false) {
                    $zip->setCompressionIndex($fileIndex, ZipArchive::CM_DEFLATE);
                }
            }

            // Add manifest
            $manifest = array(
                'version'           => PLUGIN_VERSION,
                'created_at'        => gmdate('c'),
                'snapshot_id'       => $snapshotId,
                'filename'          => $snapshot['filename'],
                'scope'             => $snapshot['scope'],
                'tables'            => json_decode($snapshot['tables_json'] ?? '[]', true),
                'total_rows'        => (int) ($snapshot['total_rows'] ?? 0),
                'included_ids'      => $includedIds,
                'incremental_count' => count($incrementals),
                'type'              => 'full_with_incrementals',
            );
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip->close();

            // Update record with final size
            $zipSize = filesize($zipPath);
            $stmt = $pdo->prepare(
                'UPDATE ' . TABLE_SNAPSHOT_EXPORTS .
                ' SET status = ?, zip_size = ?, included_ids = ?, incremental_count = ?' .
                ' WHERE snapshot_id = ?'
            );
            $stmt->execute(array(
                SNAPSHOT_EXPORT_STATUS_VALID,
                $zipSize,
                json_encode($includedIds),
                count($incrementals),
                $snapshotId,
            ));

            $this->log('INFO', 'ZIP export built successfully', array(
                'snapshot_id'       => $snapshotId,
                'filename'          => $zipFilename,
                'size'              => RiseupPathUtils::formatBytes($zipSize),
                'files'             => count($files) + count($incrementalFiles) + 1,
                'incremental_count' => count($incrementals),
            ));

            // Return the export record
            $export = $this->getValidExport($snapshotId);

            return array(
                'success' => true,
                'cached'  => false,
                'export'  => $export,
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'ZIP export build failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            // Clean up partial ZIP
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }

            // Mark as failed / remove record
            $stmt = $pdo->prepare('DELETE FROM ' . TABLE_SNAPSHOT_EXPORTS . ' WHERE snapshot_id = ?');
            $stmt->execute(array($snapshotId));

            return array(
                'success' => false,
                'error'   => 'ZIP build failed: ' . $e->getMessage(),
                'code'    => ERR_EXPORT_BUILD_FAILED,
            );
        }
    }

    /**
     * Collect all .sqlite and .db files from a snapshot directory (excluding incremental/).
     *
     * @param string $dir Snapshot directory.
     * @return array Map of absolute path => relative path.
     */
    private function collectSnapshotFiles($dir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return $files;
        }

        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (in_array($ext, array('sqlite', 'db'), true)) {
                $files[$file->getPathname()] = $file->getFilename();
            }
        }

        return $files;
    }

    /**
     * Collect all .sqlite files from incremental subdirectories.
     *
     * @param string $incrementalDir The incremental/ directory path.
     * @return array Map of absolute path => relative path (e.g. "01_2026-02-12/wp_posts.sqlite").
     */
    private function collectIncrementalFiles($incrementalDir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($incrementalDir)) {
            return $files;
        }

        $subdirs = new DirectoryIterator($incrementalDir);
        foreach ($subdirs as $subdir) {
            if ($subdir->isDot() || !$subdir->isDir()) {
                continue;
            }
            $subdirName = $subdir->getFilename();
            $innerIterator = new DirectoryIterator($subdir->getPathname());
            foreach ($innerIterator as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (in_array($ext, array('sqlite', 'db'), true)) {
                    $files[$file->getPathname()] = $subdirName . '/' . $file->getFilename();
                }
            }
        }

        return $files;
    }

    /**
     * Get all incremental snapshots belonging to a full snapshot.
     *
     * @param int    $parentId   Parent full snapshot ID.
     * @param string $parentName Parent snapshot filename (used for parent_dir matching).
     * @return array List of incremental snapshot records.
     */
    private function getIncrementalSnapshots($parentId, $parentName) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return array();
        }

        // Match incrementals by filepath containing the parent directory
        $stmt = $pdo->prepare(
            'SELECT id, filename, filepath, scope, status, created_at FROM ' . TABLE_SNAPSHOTS .
            ' WHERE scope = \'incremental\' AND filepath LIKE ? AND status = ? ORDER BY created_at ASC'
        );
        $parentDir = '%/' . $parentName . '/incremental/%';
        $stmt->execute(array($parentDir, SNAPSHOT_STATUS_COMPLETE));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log helper.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Context data.
     * @return void
     */
    private function log($level, $message, $context = array()) {
        $context['class'] = 'RiseupSnapshotExporter';
        switch ($level) {
            case 'ERROR':
                $this->logger->error('[SnapshotExporter] ' . $message, $context);
                break;
            case 'WARN':
                $this->logger->warn('[SnapshotExporter] ' . $message, $context);
                break;
            case 'DEBUG':
                $this->logger->debug('[SnapshotExporter] ' . $message, $context);
                break;
            default:
                $this->logger->info('[SnapshotExporter] ' . $message, $context);
                break;
        }
    }

    /**
     * Reset singleton (for testing).
     */
    public static function reset() {
        self::$instance = null;
    }
}
