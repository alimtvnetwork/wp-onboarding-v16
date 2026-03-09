<?php
/**
 * IncrementalDiscoveryTrait — Master snapshot discovery and table inventory.
 *
 * Supports both old snake_case and new PascalCase root DB schemas.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

trait IncrementalDiscoveryTrait {
    use RootDbCompatTrait;

    public function findLatestMasterSnapshot(): ?string {
        $masterFromDb = $this->findMasterFromDb();

        if ($masterFromDb) {
            return $masterFromDb;
        }

        return $this->findMasterFromFilesystem();
    }

    private function findMasterFromDb(): ?string {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        try {
            $stmt = $pdo->query("SELECT Filepath FROM " . TableType::Snapshots->value . "
                WHERE Scope != '" . SnapshotModeType::Incremental->value . "' AND Status = '" . SnapshotStatusType::Complete->value . "'
                ORDER BY CreatedAt DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $hasFilepath = $row && isset($row['Filepath']) && $row['Filepath'] !== '';
            $isValidSnapshotDir = $hasFilepath && is_dir($row['Filepath']) && file_exists($row['Filepath'] . PathDatabaseType::Root->value);

            if ($isValidSnapshotDir) {
                return $row['Filepath'];
            }

            return null;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to find master snapshot from DB', array(
                'error' => $e->getMessage(),
            ));

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
            if (file_exists($dir . PathDatabaseType::Root->value)) {
                return $dir;
            }
        }

        return null;
    }

    private function getMasterTableInventory(PDO $rootPdo): array {
        $table = $this->resolveRootTable($rootPdo, 'SnapshotTables', 'snapshot_tables');
        $tableNameCol = $this->resolveRootCol($rootPdo, $table, 'TableName', 'table_name');
        $rowCountCol = $this->resolveRootCol($rootPdo, $table, 'RowCount', 'row_count');

        $rows = $rootPdo->query("SELECT {$tableNameCol} AS TableName, {$rowCountCol} AS RowCount FROM {$table} ORDER BY {$tableNameCol}")->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();

        foreach ($rows as $row) {
            $pk = $this->detectPrimaryKey($row[ResponseKeyType::TableName->value]);
            $inventory[$row[ResponseKeyType::TableName->value]] = array(
                ResponseKeyType::RowCount->value  => (int) $row[ResponseKeyType::RowCount->value],
                ResponseKeyType::PkColumn->value => $pk,
            );
        }

        return $inventory;
    }

    private function detectPrimaryKey(string $table): ?string {
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);

        foreach ($columns as $col) {
            $isPrimaryAutoIncrement =
                $col['Key'] === 'PRI' &&
                str_contains($col['Extra'], 'auto_increment');

            if ($isPrimaryAutoIncrement) {
                return $col['Field'];
            }
        }

        foreach ($columns as $col) {
            $isIntPrimaryKey = $col['Key'] === 'PRI' && in_array(strtolower($col['Type']), array(
                'bigint',
                'int',
                'mediumint',
                'smallint',
                'tinyint',
            ));

            $isIntTypePrimary = str_contains(strtolower($col['Type']), 'int') && $col['Key'] === 'PRI';

            if ($isIntPrimaryKey || $isIntTypePrimary) {
                return $col['Field'];
            }
        }

        return null;
    }
}
