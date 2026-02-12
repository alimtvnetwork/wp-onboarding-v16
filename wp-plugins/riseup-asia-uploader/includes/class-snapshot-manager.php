<?php
/**
 * Riseup Asia Uploader - Snapshot Manager
 *
 * Central manager for database snapshot operations including
 * import, export, and restore functionality with ZIP handling.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Manager class.
 *
 * Coordinates snapshot operations across providers and handles
 * file-based operations (ZIP import/export, manifest validation).
 */
class RiseupSnapshotManager {

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Snapshot detector for provider selection.
     *
     * @var RiseupSnapshotDetector
     */
    private $detector;

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Singleton instance.
     *
     * @var RiseupSnapshotManager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     * @return RiseupSnapshotManager
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
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
        require_once dirname(__FILE__) . '/class-snapshot-factory.php';
        $this->detector = RiseupSnapshotFactory::detector($logger, $db);

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the active snapshot provider.
     *
     * @return RiseupSnapshotProviderInterface|null
     */
    public function getProvider() {
        $provider_id = $this->detector->getActiveProvider();
        return $this->detector->getProviderInstance($provider_id, $this->logger, $this->db);
    }

    /**
     * Create a new snapshot.
     *
     * @param array $options Snapshot options.
     * @return array Result with success status.
     */
    public function createSnapshot($options = array()) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array(
                'success' => false,
                'error' => 'No snapshot provider available',
                'code' => RISEUP_ERR_PROVIDER_NOT_AVAILABLE,
            );
        }

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Creating snapshot', array(
            'provider' => $provider->getProviderId(),
            'scope' => isset($options['scope']) ? $options['scope'] : 'default',
        ));

        return $provider->createSnapshot($options);
    }

    /**
     * Restore from a snapshot with safety checks.
     *
     * For incremental snapshots, validates that the parent full snapshot exists
     * before allowing the restore to proceed.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Result with success status.
     */
    public function restoreSnapshot($snapshot_id, $options = array()) {
        // Verify confirmation flag for safety
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array(
                'success' => false,
                'error' => 'Restore requires explicit confirmation (confirm=true)',
                'code' => RISEUP_ERR_RESTORE_NO_CONFIRM,
            );
        }

        $provider = $this->getProvider();
        if (!$provider) {
            return array(
                'success' => false,
                'error' => 'No snapshot provider available',
                'code' => RISEUP_ERR_PROVIDER_NOT_AVAILABLE,
            );
        }

        // Get snapshot details
        $snapshot = $provider->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array(
                'success' => false,
                'error' => 'Snapshot not found',
                'code' => RISEUP_ERR_SNAPSHOT_NOT_FOUND,
            );
        }

        // --- Incremental restore guard: parent full snapshot must exist ---
        if (isset($snapshot['scope']) && $snapshot['scope'] === 'incremental') {
            $tables_meta = json_decode($snapshot['tables_json'] ?? '{}', true);
            $master_dirname = $tables_meta['master'] ?? null;

            if ($master_dirname) {
                // Resolve master directory from filepath (incremental path is master_dir/incremental/folder)
                $master_dir = dirname(dirname($snapshot['filepath']));
                if (!is_dir($master_dir) || !file_exists($master_dir . '/a-root.db')) {
                    $this->log(RISEUP_LOG_LEVEL_ERROR, 'Incremental restore blocked: parent full snapshot missing', array(
                        'snapshot_id'   => $snapshot_id,
                        'master_dir'    => $master_dirname,
                        'expected_path' => $master_dir,
                    ));
                    return array(
                        'success' => false,
                        'error'   => 'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
                        'code'    => RISEUP_ERR_INCREMENTAL_NO_PARENT,
                    );
                }
            }
        }

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Starting snapshot restore', array(
            'snapshot_id' => $snapshot_id,
            'filename' => $snapshot['filename'],
            'create_backup' => !empty($options['create_backup']),
        ));

        // Create pre-restore backup if requested (default true)
        $backup_id = null;
        if (!isset($options['create_backup']) || $options['create_backup'] === true) {
            $backup_result = $this->createPreRestoreBackup($snapshot_id);
            if ($backup_result['success']) {
                $backup_id = $backup_result['snapshot_id'];
                $this->log(RISEUP_LOG_LEVEL_INFO, 'Pre-restore backup created', array(
                    'backup_id' => $backup_id,
                ));
            } else {
                $this->log(RISEUP_LOG_LEVEL_WARN, 'Failed to create pre-restore backup', array(
                    'error' => $backup_result['error'],
                ));
                // Continue with restore anyway unless strict mode
                if (!empty($options['require_backup'])) {
                    return array(
                        'success' => false,
                        'error' => 'Pre-restore backup failed: ' . $backup_result['error'],
                    );
                }
            }
        }

        // Execute restore via provider
        $result = $this->executeRestore($snapshot, $options);

        if ($result['success']) {
            $result['backup_id'] = $backup_id;
            $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot restored successfully', array(
                'snapshot_id' => $snapshot_id,
                'tables' => $result['tables'] ?? 0,
                'rows' => $result['rows'] ?? 0,
            ));
        } else {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Snapshot restore failed', array(
                'snapshot_id' => $snapshot_id,
                'error' => $result['error'],
            ));
        }

        return $result;
    }

    /**
     * Execute the actual restore operation.
     *
     * @param array $snapshot Snapshot record.
     * @param array $options  Restore options.
     * @return array Result.
     */
    private function executeRestore($snapshot, $options) {
        $start_time = microtime(true);
        $filepath = $snapshot['filepath'];

        // Validate file exists
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array(
                'success' => false,
                'error' => 'Snapshot file not found: ' . basename($filepath),
            );
        }

        try {
            // Open SQLite database
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Get tables to restore
            $tables_json = $snapshot['tables_json'];
            $all_tables = json_decode($tables_json, true);

            // Filter by mode if selective
            $mode = isset($options['mode']) ? $options['mode'] : 'full';
            if ($mode === 'selective' && !empty($options['tables'])) {
                $tables = array_intersect($all_tables, $options['tables']);
            } else {
                $tables = $all_tables;
            }

            if (empty($tables)) {
                return array(
                    'success' => false,
                    'error' => 'No tables to restore',
                );
            }

            $this->log(RISEUP_LOG_LEVEL_INFO, 'Restoring tables', array(
                'count' => count($tables),
                'mode' => $mode,
            ));

            $total_rows = 0;
            $restored_tables = 0;

            foreach ($tables as $table) {
                $result = $this->restoreTable($sqlite, $table);
                if ($result['success']) {
                    $total_rows += $result['rows'];
                    $restored_tables++;
                    $this->log(RISEUP_LOG_LEVEL_INFO, sprintf(
                        'Table %s restored (%d rows)',
                        $table,
                        $result['rows']
                    ));
                } else {
                    $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to restore table: ' . $table, array(
                        'error' => $result['error'],
                    ));
                    // Continue with other tables unless strict mode
                    if (!empty($options['strict'])) {
                        throw new Exception('Table restore failed: ' . $table);
                    }
                }
            }

            $sqlite = null; // Close connection
            $duration = microtime(true) - $start_time;

            return array(
                'success' => true,
                'tables' => $restored_tables,
                'rows' => $total_rows,
                'duration' => $duration,
            );

        } catch (Exception $e) {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Restore exception', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Restore a single table from SQLite to MySQL.
     *
     * @param PDO    $sqlite SQLite PDO instance.
     * @param string $table  Table name.
     * @return array Result with rows count.
     */
    private function restoreTable($sqlite, $table) {
        try {
            // Check if table exists in SQLite
            $check = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            if (!$check->fetch()) {
                return array('success' => false, 'error' => 'Table not found in snapshot', 'rows' => 0);
            }

            // Get column info from SQLite
            $columns_result = $sqlite->query("PRAGMA table_info('{$table}')");
            $columns = $columns_result->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'name');

            // Truncate MySQL table (use transaction for safety)
            $this->wpdb->query("START TRANSACTION");

            try {
                // Disable foreign key checks
                $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

                // Truncate the table
                $this->wpdb->query("TRUNCATE TABLE `{$table}`");

                // Get all rows from SQLite
                $batch_size = RISEUP_SNAPSHOT_BATCH_SIZE;
                $offset = 0;
                $total_rows = 0;

                // Count total rows
                $count_stmt = $sqlite->query("SELECT COUNT(*) FROM `{$table}`");
                $row_count = $count_stmt->fetchColumn();

                while ($offset < $row_count) {
                    $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$batch_size} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        // Build INSERT statement
                        $values = array();
                        $placeholders = array();

                        foreach ($column_names as $col) {
                            $values[] = isset($row[$col]) ? $row[$col] : null;
                            $placeholders[] = '%s';
                        }

                        $columns_sql = '`' . implode('`, `', $column_names) . '`';
                        $placeholders_sql = implode(', ', $placeholders);

                        $sql = "INSERT INTO `{$table}` ({$columns_sql}) VALUES ({$placeholders_sql})";
                        $prepared = $this->wpdb->prepare($sql, $values);
                        $this->wpdb->query($prepared);

                        $total_rows++;
                    }

                    $offset += $batch_size;
                }

                // Re-enable foreign key checks
                $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

                $this->wpdb->query("COMMIT");

                return array('success' => true, 'rows' => $total_rows);

            } catch (Exception $e) {
                $this->wpdb->query("ROLLBACK");
                $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                throw $e;
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'rows' => 0,
            );
        }
    }

    /**
     * Create a pre-restore backup snapshot.
     *
     * @param int $original_snapshot_id Original snapshot being restored.
     * @return array Result.
     */
    private function createPreRestoreBackup($original_snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->createSnapshot(array(
            'scope' => RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
            'trigger' => RISEUP_SNAPSHOT_TRIGGER_API,
            'pre_restore_of' => $original_snapshot_id,
        ));
    }

    /**
     * Export a snapshot to a downloadable ZIP file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Result with filepath.
     */
    public function exportSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array(
                'success' => false,
                'error' => 'No snapshot provider available',
            );
        }

        $snapshot = $provider->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array(
                'success' => false,
                'error' => 'Snapshot not found',
                'code' => RISEUP_ERR_SNAPSHOT_NOT_FOUND,
            );
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array(
                'success' => false,
                'error' => 'Snapshot file not found',
            );
        }

        // Determine ZIP path
        $zip_path = preg_replace('/\.sqlite$/', '.zip', $filepath);

        // Create ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to create ZIP file', array('path' => $zip_path));
            return array(
                'success' => false,
                'error' => 'Failed to create ZIP file',
            );
        }

        // Add SQLite file
        $zip->addFile($filepath, basename($filepath));

        // Create and add manifest
        $manifest = $this->createExportManifest($snapshot);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();

        $size = filesize($zip_path);

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot exported to ZIP', array(
            'snapshot_id' => $snapshot_id,
            'zip_path' => $zip_path,
            'size' => RiseupPathUtils::formatBytes($size),
        ));

        return array(
            'success' => true,
            'filepath' => $zip_path,
            'filename' => basename($zip_path),
            'size' => $size,
        );
    }

    /**
     * Create export manifest for ZIP.
     *
     * @param array $snapshot Snapshot record.
     * @return array Manifest data.
     */
    private function createExportManifest($snapshot) {
        return array(
            'version' => RISEUP_VERSION,
            'format_version' => '1.0',
            'created_at' => date('c'),
            'exported_at' => date('c'),
            'snapshot' => array(
                'id' => $snapshot['id'],
                'sequence' => $snapshot['sequence'],
                'filename' => $snapshot['filename'],
                'scope' => $snapshot['scope'],
                'provider' => $snapshot['provider'],
                'tables' => json_decode($snapshot['tables_json'], true),
                'total_rows' => $snapshot['total_rows'],
                'file_size' => $snapshot['file_size'],
                'created_at' => $snapshot['created_at'],
            ),
            'source' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'site_url' => get_site_url(),
                'db_prefix' => $this->wpdb->prefix,
            ),
        );
    }

    /**
     * Import a snapshot from an uploaded ZIP file.
     *
     * @param string $uploaded_path Path to uploaded file.
     * @return array Result with snapshot ID.
     */
    public function importSnapshot($uploaded_path) {
        // Validate file exists
        if (!RiseupPathUtils::fileExists($uploaded_path)) {
            return array(
                'success' => false,
                'error' => 'Uploaded file not found',
            );
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploaded_path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return array(
                'success' => false,
                'error' => 'Invalid file type. Expected ZIP file.',
            );
        }

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Importing snapshot from ZIP', array(
            'path' => $uploaded_path,
            'size' => RiseupPathUtils::formatBytes(filesize($uploaded_path)),
        ));

        // Create temp extraction directory
        $temp_dir = RiseupPathUtils::join(
            RiseupPathUtils::getTempDir(),
            'import_' . uniqid()
        );

        if (!RiseupPathUtils::ensureDir($temp_dir, false)) {
            return array(
                'success' => false,
                'error' => 'Failed to create temp directory',
            );
        }

        try {
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($uploaded_path) !== true) {
                throw new Exception('Failed to open ZIP file');
            }

            $zip->extractTo($temp_dir);
            $zip->close();

            // Find manifest
            $manifest_path = RiseupPathUtils::join($temp_dir, 'manifest.json');
            if (!RiseupPathUtils::fileExists($manifest_path)) {
                throw new Exception('Invalid snapshot archive: manifest.json not found');
            }

            // Parse manifest
            $manifest_json = file_get_contents($manifest_path);
            $manifest = json_decode($manifest_json, true);
            if (!$manifest) {
                throw new Exception('Invalid manifest.json format');
            }

            // Validate manifest
            $validation = $this->validateManifest($manifest);
            if (!$validation['valid']) {
                throw new Exception('Manifest validation failed: ' . $validation['error']);
            }

            // Find SQLite file
            $sqlite_filename = $manifest['snapshot']['filename'];
            $sqlite_path = RiseupPathUtils::join($temp_dir, $sqlite_filename);
            if (!RiseupPathUtils::fileExists($sqlite_path)) {
                throw new Exception('SQLite file not found in archive: ' . $sqlite_filename);
            }

            // Validate SQLite integrity
            $integrity = $this->validateSqliteIntegrity($sqlite_path);
            if (!$integrity['valid']) {
                throw new Exception('SQLite integrity check failed: ' . $integrity['error']);
            }

            // Move to snapshots directory
            $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
            if (!RiseupPathUtils::ensureDir($snapshots_dir, true)) {
                throw new Exception('Failed to ensure snapshots directory');
            }

            // Generate new filename with sequence
            $provider = $this->getProvider();
            $sequence = $this->getNextSequence();
            $new_filename = sprintf('%03d_%s', $sequence, date('Y-m-d_His')) . '.sqlite';
            $dest_path = RiseupPathUtils::join($snapshots_dir, $new_filename);

            // Copy file (use copy instead of rename for cross-device compatibility)
            if (!copy($sqlite_path, $dest_path)) {
                throw new Exception('Failed to copy snapshot file to destination');
            }

            // Create database record
            $snapshot_id = $this->createImportedSnapshotRecord($manifest, $sequence, $new_filename, $dest_path);
            if (!$snapshot_id) {
                RiseupPathUtils::deleteFile($dest_path);
                throw new Exception('Failed to create snapshot record');
            }

            // Cleanup temp directory
            $this->deleteDirectory($temp_dir);

            $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot imported successfully', array(
                'snapshot_id' => $snapshot_id,
                'filename' => $new_filename,
            ));

            return array(
                'success' => true,
                'snapshot_id' => $snapshot_id,
                'filename' => $new_filename,
                'tables' => count($manifest['snapshot']['tables']),
                'rows' => $manifest['snapshot']['total_rows'],
            );

        } catch (Exception $e) {
            // Cleanup on error
            if (RiseupPathUtils::dirExists($temp_dir)) {
                $this->deleteDirectory($temp_dir);
            }

            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Snapshot import failed', array(
                'error' => $e->getMessage(),
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Validate manifest structure and version.
     *
     * @param array $manifest Manifest data.
     * @return array Validation result.
     */
    private function validateManifest($manifest) {
        // Check required fields
        $required = array('version', 'snapshot');
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                return array('valid' => false, 'error' => "Missing required field: {$field}");
            }
        }

        // Check snapshot fields
        $snapshot_required = array('filename', 'tables', 'scope');
        foreach ($snapshot_required as $field) {
            if (!isset($manifest['snapshot'][$field])) {
                return array('valid' => false, 'error' => "Missing snapshot field: {$field}");
            }
        }

        // Version compatibility check
        $format_version = isset($manifest['format_version']) ? $manifest['format_version'] : '1.0';
        if (version_compare($format_version, '2.0', '>=')) {
            return array('valid' => false, 'error' => 'Unsupported format version: ' . $format_version);
        }

        return array('valid' => true);
    }

    /**
     * Validate SQLite database integrity.
     *
     * @param string $filepath Path to SQLite file.
     * @return array Validation result.
     */
    private function validateSqliteIntegrity($filepath) {
        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Run integrity check
            $result = $pdo->query("PRAGMA integrity_check");
            $integrity = $result->fetchColumn();

            if ($integrity !== 'ok') {
                return array('valid' => false, 'error' => 'Database integrity check failed: ' . $integrity);
            }

            // Check for _snapshot_meta table
            $meta_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='_snapshot_meta'");
            if (!$meta_check->fetch()) {
                return array('valid' => false, 'error' => 'Missing _snapshot_meta table');
            }

            $pdo = null;
            return array('valid' => true);

        } catch (Exception $e) {
            return array('valid' => false, 'error' => 'SQLite error: ' . $e->getMessage());
        }
    }

    /**
     * Get the next sequence number.
     *
     * @return int Next sequence.
     */
    private function getNextSequence() {
        $result = $this->db->query_single(
            'SELECT MAX(sequence) as max_seq FROM ' . RISEUP_TABLE_SNAPSHOTS
        );
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    /**
     * Create a database record for an imported snapshot.
     *
     * @param array  $manifest    Original manifest.
     * @param int    $sequence    New sequence number.
     * @param string $filename    New filename.
     * @param string $filepath    Full path.
     * @return int|false Snapshot ID or false.
     */
    private function createImportedSnapshotRecord($manifest, $sequence, $filename, $filepath) {
        $snapshot_data = $manifest['snapshot'];

        $data = array(
            'sequence' => $sequence,
            'filename' => $filename,
            'filepath' => $filepath,
            'provider' => RISEUP_SNAPSHOT_PROVIDER_NATIVE,
            'scope' => $snapshot_data['scope'],
            'tables_json' => json_encode($snapshot_data['tables']),
            'total_rows' => $snapshot_data['total_rows'] ?? 0,
            'file_size' => filesize($filepath),
            'trigger_source' => 'import',
            'status' => RISEUP_SNAPSHOT_STATUS_COMPLETE,
            'created_at' => date('c'),
            'completed_at' => date('c'),
            'import_source' => json_encode(array(
                'original_id' => $snapshot_data['id'] ?? null,
                'original_created_at' => $snapshot_data['created_at'] ?? null,
                'source_site' => $manifest['source']['site_url'] ?? null,
            )),
        );

        $result = $this->db->insert(RISEUP_TABLE_SNAPSHOTS, $data);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     * @return bool Success.
     */
    private function deleteDirectory($dir) {
        if (!RiseupPathUtils::dirExists($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = RiseupPathUtils::join($dir, $file);
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                RiseupPathUtils::deleteFile($path);
            }
        }

        return RiseupPathUtils::deleteDir($dir);
    }

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Result.
     */
    public function deleteSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array(
                'success' => false,
                'error' => 'No provider available',
            );
        }

        return $provider->deleteSnapshot($snapshot_id);
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return null;
        }

        return $provider->getSnapshot($snapshot_id);
    }

    /**
     * List all snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots($limit = 50, $offset = 0) {
        // Query all snapshots regardless of provider
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . RISEUP_TABLE_SNAPSHOTS . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->query_single(
            'SELECT COUNT(*) as count FROM ' . RISEUP_TABLE_SNAPSHOTS
        );

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /**
     * Get current snapshot settings.
     *
     * @return array Settings.
     */
    public function getSettings() {
        // Read from SQLite snapshot_settings table (source of truth)
        $settings = array();
        $pdo = $this->db->get_pdo();
        
        if ($pdo) {
            try {
                $rows = $pdo->query("SELECT key, value, type FROM snapshot_settings")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $key = str_replace('snapshot.', '', $row['key']);
                    $settings[$key] = $this->castSettingValue($row['value'], $row['type']);
                }
            } catch (Exception $e) {
                $this->log(RISEUP_LOG_LEVEL_WARN, 'Failed to read snapshot_settings from SQLite', array('error' => $e->getMessage()));
            }
        }
        
        // Fallback defaults for any missing keys
        $defaults = array(
            'mode'               => 'per_table',
            'backup_type'        => 'incremental',
            'worker_count'       => 10,
            'storage_path'       => 'snapshots/',
            'include_plugins'    => true,
            'plugin_selection'   => 'all',
            'retention_days'     => RISEUP_SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count'    => RISEUP_SNAPSHOT_RETENTION_COUNT_DEFAULT,
            'compression'        => true,
            'batch_size'         => RISEUP_SNAPSHOT_BATCH_SIZE,
            'provider'           => RISEUP_SNAPSHOT_PROVIDER_AUTO,
            'scope'              => RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
            'frequency'          => RISEUP_SNAPSHOT_FREQ_MANUAL,
            'schedule_time'      => '03:00',
            'pre_restore_backup' => true,
            'custom_tables'      => array(),
        );

        return array_merge($defaults, $settings);
    }

    /**
     * Update snapshot settings in SQLite.
     *
     * @param array $settings New settings.
     * @return array Updated settings.
     */
    public function updateSettings($settings) {
        $pdo = $this->db->get_pdo();
        
        if ($pdo) {
            try {
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_settings (key, value, type, updated_at) VALUES (?, ?, ?, ?)");
                
                foreach ($settings as $key => $value) {
                    $dbKey = 'snapshot.' . $key;
                    $type = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
                    $dbValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    $stmt->execute(array($dbKey, $dbValue, $type, $now));
                }
            } catch (Exception $e) {
                $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to update snapshot_settings', array('error' => $e->getMessage()));
            }
        }

        // Sync cron schedule if frequency changed
        if (isset($settings['frequency'])) {
            $updated = $this->getSettings();
            $scheduler = RiseupSnapshotFactory::scheduler($this->logger, $this->db);
            $scheduler->syncSchedule($updated);
        }

        $result = $this->getSettings();
        $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot settings updated', array(
            'keys' => array_keys($settings),
        ));

        return $result;
    }

    /**
     * Cast a setting value to its declared type.
     *
     * @param string $value Raw string value.
     * @param string $type  Type hint: 'string', 'int', 'bool', 'json'.
     * @return mixed Typed value.
     */
    private function castSettingValue($value, $type) {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'bool':
                return $value === '1' || $value === 'true';
            case 'json':
                return json_decode($value, true) ?: array();
            default:
                return $value;
        }
    }

    /**
     * Get available providers and their status.
     *
     * @return array Providers list.
     */
    public function getProviders() {
        return $this->detector->getAvailableProviders();
    }

    /**
     * Get available database tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        $provider = $this->getProvider();
        if (!$provider) {
            return array();
        }

        return $provider->getAvailableTables();
    }

    /**
     * Log a message with manager context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [MANAGER]';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case RISEUP_LOG_LEVEL_DEBUG:
                    $this->logger->debug($full_message);
                    break;
                case RISEUP_LOG_LEVEL_INFO:
                    $this->logger->info($full_message);
                    break;
                case RISEUP_LOG_LEVEL_WARN:
                    $this->logger->warn($full_message);
                    break;
                case RISEUP_LOG_LEVEL_ERROR:
                    $this->logger->error($full_message);
                    break;
                default:
                    $this->logger->info($full_message);
            }
        }
    }
}
