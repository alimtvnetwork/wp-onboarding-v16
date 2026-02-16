<?php
/**
 * NativeSnapshotRecordTrait — snapshot SQLite creation and record management.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait NativeSnapshotRecordTrait {

    use NativeSnapshotCrudTrait;

    private function createSqliteDatabase(string $filepath): ?PDO {
        $snapshotsDir = $this->getSnapshotsDir();
        if (!RiseupPathUtils::isSafePath($filepath, $snapshotsDir)) {
            $this->log(LogLevelType::Error->value, 'Unsafe path detected for SQLite database', array('filepath' => $filepath, 'base' => $snapshotsDir));
            return null;
        }

        $parentDir = dirname($filepath);
        if (!RiseupPathUtils::ensureDir($parentDir, true)) {
            $this->log(LogLevelType::Error->value, 'Failed to ensure parent directory for SQLite', array('parent' => $parentDir));
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
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to create SQLite database', array('filepath' => $filepath, 'error' => $e->getMessage()));
            return null;
        }
    }

    private function insertSnapshotMeta(PDO $pdo): void {
        $meta = array(
            'created_at' => date('c'), 'wp_version' => get_bloginfo('version'),
            'site_url' => get_site_url(), 'php_version' => PHP_VERSION,
            'provider' => $this->provider_id, 'plugin_version' => PluginConfigType::Version->value,
        );
        $stmt = $pdo->prepare('INSERT INTO _snapshot_meta (key, value) VALUES (?, ?)');
        foreach ($meta as $key => $value) {
            $stmt->execute(array($key, $value));
        }
    }

    private function createSnapshotRecord(int $sequence, string $filename, string $filepath, string $scope, array $tables, string $trigger): int|false {
        $result = $this->db->insert(TableType::Snapshots->value, array(
            'sequence' => $sequence, 'filename' => $filename . '.sqlite', 'filepath' => $filepath,
            'provider' => $this->provider_id, 'scope' => $scope, 'tables_json' => json_encode($tables),
            'trigger_source' => $trigger, 'status' => SnapshotStatusType::Pending->value, 'created_at' => date('c'),
        ));
        return $result ? $this->db->lastInsertId() : false;
    }

    private function updateSnapshotStatus(int $snapshotId, string $status, ?string $error = null): void {
        $data = array('status' => $status, 'updated_at' => date('c'));
        if ($error) {
            $data['error_message'] = $error;
        }
        if ($status === SnapshotStatusType::Running->value) {
            $data['started_at'] = date('c');
        }
        $this->db->update(TableType::Snapshots->value, $data, array('id' => $snapshotId));
    }

    private function finalizeSnapshot(int $snapshotId, array $details): void {
        $this->db->update(TableType::Snapshots->value, array(
            'status' => $details['status'], 'file_size' => $details['file_size'],
            'total_rows' => $details['total_rows'], 'table_counts_json' => json_encode($details['table_counts']),
            'duration_ms' => $details['duration_ms'], 'completed_at' => date('c'), 'updated_at' => date('c'),
        ), array('id' => $snapshotId));
    }
}
