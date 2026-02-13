<?php
/**
 * IncrementalDeltaTrait — Delta detection and max-ID resolution.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait IncrementalDeltaTrait {

    /**
     * Export delta for a single table if it has new rows.
     *
     * @param string $tableName Table name.
     * @param array  $info      Master table info.
     * @param string $incDir    Incremental directory.
     * @param PDO    $rootPdo   Root DB connection.
     * @param int    $sequence  Sequence number.
     * @return array|null Export result or null if skipped.
     */
    private function exportTableDelta(string $tableName, array $info, string $incDir, PDO $rootPdo, int $sequence): ?array {
        $last_max_id = $this->getLastMaxId($tableName, $info, $rootPdo, $sequence);

        if ($last_max_id === null) {
            $this->log('INFO', 'Skipping table (no auto-increment PK): ' . $tableName);
            return null;
        }

        $new_count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE `{$info['pk_column']}` > %d", $last_max_id)
        );

        if ($new_count === 0) {
            return null;
        }

        $result = $this->exportDeltaRows($incDir, $tableName, $info['pk_column'], $last_max_id, $new_count);

        if ($result['success']) {
            $this->log('INFO', sprintf('Incremental export: %s (+%d rows, %s)', $tableName, $result['rows'], $this->formatBytes($result['file_size'])));
            $result['entry'] = array('table' => $tableName, 'new_rows' => $result['rows'], 'size' => $result['file_size']);
        } else {
            $this->log('ERROR', 'Incremental export failed: ' . $tableName, array('error' => $result['error']));
        }

        return $result;
    }

    /**
     * Determine the last_max_id for a table.
     *
     * @param string $table_name Table name.
     * @param array  $info       Master table info.
     * @param PDO    $rootPdo    a-root.db connection.
     * @param int    $sequence   Current sequence.
     * @return int|null Last max ID or null if no PK.
     */
    private function getLastMaxId($table_name, $info, $rootPdo, $sequence) {
        if ($info['pk_column'] === null) {
            return null;
        }

        if ($sequence === 1) {
            return $this->getMaxIdFromMasterSqlite($rootPdo, $table_name, $info['pk_column'], $info);
        }

        return $this->getMaxIdFromPreviousIncremental($rootPdo, $table_name, $info['pk_column'], $sequence, $info);
    }

    /**
     * Get max ID from the master snapshot's SQLite file.
     */
    private function getMaxIdFromMasterSqlite(PDO $rootPdo, string $tableName, string $pk, array $info): int {
        $sqlite_file = $this->findMasterSqliteFile($rootPdo, $tableName);
        if (!$sqlite_file) {
            return (int) $info['row_count'];
        }

        try {
            $tablePdo = new PDO('sqlite:' . $sqlite_file);
            $tablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $max_id = $tablePdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $tablePdo = null;
            return ($max_id !== false && $max_id !== null) ? (int) $max_id : 0;
        } catch (Exception $e) {
            $this->log('WARN', 'Could not read master SQLite for max ID', array('table' => $tableName, 'error' => $e->getMessage()));
            return (int) $info['row_count'];
        }
    }

    /**
     * Get max ID from the previous incremental's SQLite file.
     */
    private function getMaxIdFromPreviousIncremental(PDO $rootPdo, string $tableName, string $pk, int $sequence, array $info): int {
        $prev_seq = $sequence - 1;
        $prev_folder = $rootPdo->query("SELECT folder_name FROM incremental_backups WHERE sequence_num = {$prev_seq}")->fetchColumn();

        if ($prev_folder) {
            $root_dir = $this->getRootDirFromPdo($rootPdo);
            $prev_sqlite = $root_dir . '/incremental/' . $prev_folder . '/' . $tableName . '.sqlite';
            $maxId = $this->readMaxIdFromSqlite($prev_sqlite, $tableName, $pk);
            if ($maxId !== null) {
                return $maxId;
            }
        }

        return $this->getMaxIdFromMasterSqlite($rootPdo, $tableName, $pk, $info);
    }

    /**
     * Read MAX(pk) from a SQLite file.
     */
    private function readMaxIdFromSqlite(string $sqlitePath, string $tableName, string $pk): ?int {
        if (RiseupBooleanHelpers::is_file_missing($sqlitePath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $max_id = $pdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $pdo = null;
            return ($max_id !== false && $max_id !== null) ? (int) $max_id : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Find the master SQLite file path for a table.
     */
    private function findMasterSqliteFile($rootPdo, $table_name) {
        $stmt = $rootPdo->prepare("SELECT sqlite_file FROM snapshot_tables WHERE table_name = ?");
        $stmt->execute(array($table_name));
        $filename = $stmt->fetchColumn();

        if (!$filename) {
            return null;
        }

        $root_dir = $this->getRootDirFromPdo($rootPdo);
        $full_path = $root_dir . '/' . $filename;
        return file_exists($full_path) ? $full_path : null;
    }

    /**
     * Get the snapshot root directory from the a-root.db PDO path.
     */
    private function getRootDirFromPdo($rootPdo) {
        $result = $rootPdo->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['file'])) {
            return dirname($result['file']);
        }
        return '';
    }

    /**
     * Get the next incremental sequence number.
     */
    private function getNextSequence($rootPdo) {
        $max = $rootPdo->query("SELECT MAX(sequence_num) FROM incremental_backups")->fetchColumn();
        return ($max !== false && $max !== null) ? (int) $max + 1 : 1;
    }
}
