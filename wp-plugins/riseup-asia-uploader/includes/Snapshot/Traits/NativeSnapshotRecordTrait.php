<?php
/**
 * NativeSnapshotRecordTrait — snapshot CRUD, SQLite creation, and lifecycle operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait NativeSnapshotRecordTrait {

    /**
     * Create SQLite database file.
     *
     * @param string $filepath Path to create database.
     * @return PDO|null PDO instance or null on failure.
     */
    private function createSqliteDatabase($filepath) {
        $snapshots_dir = $this->getSnapshotsDir();
        if (!RiseupPathUtils::isSafePath($filepath, $snapshots_dir)) {
            $this->log(LOG_LEVEL_ERROR, 'Unsafe path detected for SQLite database', array('filepath' => $filepath, 'base' => $snapshots_dir));
            return null;
        }

        $parent_dir = dirname($filepath);
        if (!RiseupPathUtils::ensureDir($parent_dir, true)) {
            $this->log(LOG_LEVEL_ERROR, 'Failed to ensure parent directory for SQLite', array('parent' => $parent_dir));
            return null;
        }

        try {
            $this->log(LOG_LEVEL_DEBUG, 'Creating SQLite database', array('filepath' => $filepath));
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('CREATE TABLE IF NOT EXISTS _snapshot_meta (key TEXT PRIMARY KEY, value TEXT)');

            $this->insertSnapshotMeta($pdo);
            return $pdo;
        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'Failed to create SQLite database', array('filepath' => $filepath, 'error' => $e->getMessage()));
            return null;
        }
    }

    /**
     * Insert metadata into SQLite snapshot.
     *
     * @param PDO $pdo SQLite PDO instance.
     */
    private function insertSnapshotMeta(PDO $pdo) {
        $meta = array(
            'created_at' => date('c'), 'wp_version' => get_bloginfo('version'),
            'site_url' => get_site_url(), 'php_version' => PHP_VERSION,
            'provider' => $this->provider_id, 'plugin_version' => PLUGIN_VERSION,
        );
        $stmt = $pdo->prepare('INSERT INTO _snapshot_meta (key, value) VALUES (?, ?)');
        foreach ($meta as $key => $value) {
            $stmt->execute(array($key, $value));
        }
    }

    /**
     * Create a snapshot record in the database.
     *
     * @param int    $sequence Sequence number.
     * @param string $filename Filename without extension.
     * @param string $filepath Full path to file.
     * @param string $scope    Snapshot scope.
     * @param array  $tables   Tables included.
     * @param string $trigger  Trigger source.
     * @return int|false Snapshot ID or false.
     */
    private function createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger) {
        $result = $this->db->insert(TABLE_SNAPSHOTS, array(
            'sequence' => $sequence, 'filename' => $filename . '.sqlite', 'filepath' => $filepath,
            'provider' => $this->provider_id, 'scope' => $scope, 'tables_json' => json_encode($tables),
            'trigger_source' => $trigger, 'status' => SNAPSHOT_STATUS_PENDING, 'created_at' => date('c'),
        ));
        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Update snapshot status.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param string $status      New status.
     * @param string $error       Error message (optional).
     */
    private function updateSnapshotStatus($snapshot_id, $status, $error = null) {
        $data = array('status' => $status, 'updated_at' => date('c'));
        if ($error) {
            $data['error_message'] = $error;
        }
        if ($status === SNAPSHOT_STATUS_RUNNING) {
            $data['started_at'] = date('c');
        }
        $this->db->update(TABLE_SNAPSHOTS, $data, array('id' => $snapshot_id));
    }

    /**
     * Finalize a snapshot with completion details.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $details     Completion details.
     */
    private function finalizeSnapshot($snapshot_id, $details) {
        $this->db->update(TABLE_SNAPSHOTS, array(
            'status' => $details['status'], 'file_size' => $details['file_size'],
            'total_rows' => $details['total_rows'], 'table_counts_json' => json_encode($details['table_counts']),
            'duration_ms' => $details['duration_ms'], 'completed_at' => date('c'), 'updated_at' => date('c'),
        ), array('id' => $snapshot_id));
    }

    /**
     * Restore from a snapshot (delegates to manager).
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Restore result.
     */
    public function restoreSnapshot($snapshot_id, $options) {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->restoreSnapshot($snapshot_id, $options);
    }

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Delete result.
     */
    public function deleteSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (RiseupPathUtils::fileExists($filepath)) {
            if (!RiseupPathUtils::deleteFile($filepath)) {
                $this->log(LOG_LEVEL_ERROR, 'Failed to delete snapshot file', array('filepath' => $filepath));
                return array('success' => false, 'error' => 'Failed to delete snapshot file');
            }
        }

        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        if (RiseupPathUtils::fileExists($zip_path)) {
            RiseupPathUtils::deleteFile($zip_path);
        }

        $this->db->delete(TABLE_SNAPSHOTS, array('id' => $snapshot_id));
        $this->log(LOG_LEVEL_INFO, 'Snapshot deleted', array('snapshot_id' => $snapshot_id, 'filename' => $snapshot['filename']));
        return array('success' => true);
    }

    /**
     * Export snapshot to ZIP file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Export result.
     */
    public function exportSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found');
        }

        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return array('success' => false, 'error' => 'Failed to create ZIP file');
        }

        $zip->addFile($filepath, basename($filepath));
        $zip->addFromString('manifest.json', json_encode(array(
            'version' => PLUGIN_VERSION, 'created_at' => date('c'), 'snapshot_id' => $snapshot_id,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'], true),
            'total_rows' => $snapshot['total_rows'], 'file_size' => $snapshot['file_size'],
        ), JSON_PRETTY_PRINT));
        $zip->close();

        return array('success' => true, 'filepath' => $zip_path, 'filename' => basename($zip_path), 'size' => filesize($zip_path));
    }

    /**
     * Import snapshot from uploaded file (delegates to manager).
     *
     * @param string $filepath Path to uploaded file.
     * @return array Import result.
     */
    public function importSnapshot($filepath) {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->importSnapshot($filepath);
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        return $this->db->query_single('SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE id = ?', array($snapshot_id));
    }

    /**
     * List snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );
        $total = $this->db->query_single('SELECT COUNT(*) as count FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ?', array($this->provider_id));
        return array('snapshots' => $snapshots ?: array(), 'total' => $total ? (int)$total['count'] : 0);
    }

    /**
     * Get available tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        $tables = array();
        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'], 'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $this->wpdb->prefix) === 0,
            );
        }
        return $tables;
    }
}
