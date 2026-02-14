<?php
/**
 * IncrementalDiscoveryTrait — Master snapshot discovery and table inventory.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait IncrementalDiscoveryTrait {

    /**
     * Find the latest full (master) snapshot directory.
     *
     * @return string|null Path or null.
     */
    public function findLatestMasterSnapshot() {
        $masterFromDb = $this->findMasterFromDb();
        if ($masterFromDb) {
            return $masterFromDb;
        }

        return $this->findMasterFromFilesystem();
    }

    /** Find the latest master snapshot from the database. */
    private function findMasterFromDb(): ?string {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->query("SELECT filepath FROM " . TableType::Snapshots->value . "
                WHERE scope != 'incremental' AND status = '" . SnapshotStatusType::Complete->value . "'
                ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['filepath']) && is_dir($row['filepath']) && file_exists($row['filepath'] . '/a-root.db')) {
                return $row['filepath'];
            }

            return null;
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Failed to find master snapshot from DB', array('error' => $e->getMessage()));
            return null;
        }
    }

    /** Find the latest master snapshot from the filesystem (fallback). */
    private function findMasterFromFilesystem(): ?string {
        $base_dir = $this->getSnapshotsBaseDir();
        if (RiseupBooleanHelpers::isDirMissing($base_dir)) {
            return null;
        }

        $dirs = glob($base_dir . '/*_full_*', GLOB_ONLYDIR);
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

    /**
     * Get master table inventory from a-root.db.
     *
     * @param PDO $rootPdo a-root.db connection.
     * @return array Map of table_name => { row_count, pk_column }.
     */
    private function getMasterTableInventory($rootPdo) {
        $rows = $rootPdo->query("SELECT table_name, row_count FROM snapshot_tables ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $pk = $this->detectPrimaryKey($row['table_name']);
            $inventory[$row['table_name']] = array('row_count' => (int) $row['row_count'], 'pk_column' => $pk);
        }

        return $inventory;
    }

    /**
     * Detect the auto-increment primary key column of a MySQL table.
     *
     * @param string $table Table name.
     * @return string|null PK column name or null.
     */
    private function detectPrimaryKey($table) {
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && strpos($col['Extra'], 'auto_increment') !== false) {
                return $col['Field'];
            }
        }
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && in_array(strtolower($col['Type']), array('bigint', 'int', 'mediumint', 'smallint', 'tinyint'))
                || (strpos(strtolower($col['Type']), 'int') !== false && $col['Key'] === 'PRI')) {
                return $col['Field'];
            }
        }
        return null;
    }
}
