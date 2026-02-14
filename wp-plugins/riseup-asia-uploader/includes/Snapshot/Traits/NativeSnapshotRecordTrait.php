<?php
/**
 * NativeSnapshotRecordTrait — snapshot SQLite creation and record management.
 *
 * Shell trait — CRUD ops delegated to NativeSnapshotCrudTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

require_once __DIR__ . '/NativeSnapshotCrudTrait.php';

trait NativeSnapshotRecordTrait {

    use NativeSnapshotCrudTrait;

    /** Create SQLite database file. */
    private function createSqliteDatabase($filepath) {
        $snapshots_dir = $this->getSnapshotsDir();
        if (!RiseupPathUtils::isSafePath($filepath, $snapshots_dir)) {
            $this->log(LogLevelType::Error->value, 'Unsafe path detected for SQLite database', array('filepath' => $filepath, 'base' => $snapshots_dir));
            return null;
        }

        $parent_dir = dirname($filepath);
        if (!RiseupPathUtils::ensureDir($parent_dir, true)) {
            $this->log(LogLevelType::Error->value, 'Failed to ensure parent directory for SQLite', array('parent' => $parent_dir));
            return null;
        }

        try {
            $this->log(LogLevelType::Debug->value, 'Creating SQLite database', array('filepath' => $filepath));
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('CREATE TABLE IF NOT EXISTS _snapshot_meta (key TEXT PRIMARY KEY, value TEXT)');

            $this->insertSnapshotMeta($pdo);
            return $pdo;
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Failed to create SQLite database', array('filepath' => $filepath, 'error' => $e->getMessage()));
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
        $result = $this->db->insert(TableType::Snapshots->value, array(
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
        $this->db->update(TableType::Snapshots->value, $data, array('id' => $snapshot_id));
    }

    /**
     * Finalize a snapshot with completion details.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $details     Completion details.
     */
    private function finalizeSnapshot($snapshot_id, $details) {
        $this->db->update(TableType::Snapshots->value, array(
            'status' => $details['status'], 'file_size' => $details['file_size'],
            'total_rows' => $details['total_rows'], 'table_counts_json' => json_encode($details['table_counts']),
            'duration_ms' => $details['duration_ms'], 'completed_at' => date('c'), 'updated_at' => date('c'),
        ), array('id' => $snapshot_id));
    }
}
