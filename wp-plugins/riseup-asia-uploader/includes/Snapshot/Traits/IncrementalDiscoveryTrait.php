<?php
/**
 * IncrementalDiscoveryTrait — Master snapshot discovery and table inventory.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use PDO;
use Throwable;

trait IncrementalDiscoveryTrait {

    public function findLatestMasterSnapshot(): ?string {
        $masterFromDb = $this->findMasterFromDb();
        if ($masterFromDb) {
            return $masterFromDb;
        }

        return $this->findMasterFromFilesystem();
    }

    private function findMasterFromDb(): ?string {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->query("SELECT filepath FROM " . TableType::Snapshots->value . "
                WHERE scope != '" . SnapshotModeType::Incremental->value . "' AND status = '" . SnapshotStatusType::Complete->value . "'
                ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $hasFilepath = $row && isset($row['filepath']) && $row['filepath'] !== '';
            $isValidSnapshotDir = $hasFilepath && is_dir($row['filepath']) && file_exists($row['filepath'] . '/a-root.db');
            if ($isValidSnapshotDir) {
                return $row['filepath'];
            }

            return null;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to find master snapshot from DB', array('error' => $e->getMessage()));
            return null;
        }
    }

    private function findMasterFromFilesystem(): ?string {
        $baseDir = $this->getSnapshotsBaseDir();
        if (PathHelper::isDirMissing($baseDir)) {
            return null;
        }

        $dirs = glob($baseDir . '/*_full_*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            return null;
        }

        rsort($dirs);
        foreach ($dirs as $dir) {
            if (file_exists($dir . '/a-root.db')) {
                return $dir;
            }
        }

        return null;
    }

    private function getMasterTableInventory(PDO $rootPdo): array {
        $rows = $rootPdo->query("SELECT table_name, row_count FROM snapshot_tables ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $pk = $this->detectPrimaryKey($row['table_name']);
            $inventory[$row['table_name']] = array('row_count' => (int) $row['row_count'], 'pk_column' => $pk);
        }

        return $inventory;
    }

    private function detectPrimaryKey(string $table): ?string {
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && str_contains($col['Extra'], 'auto_increment')) {
                return $col['Field'];
            }
        }
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && in_array(strtolower($col['Type']), array('bigint', 'int', 'mediumint', 'smallint', 'tinyint'))
                || (str_contains(strtolower($col['Type']), 'int') && $col['Key'] === 'PRI')) {
                return $col['Field'];
            }
        }
        return null;
    }
}
