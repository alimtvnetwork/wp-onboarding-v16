<?php
/**
 * Restore Table Trait
 *
 * Master table restoration: single table restore and file-level orchestration.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/RestoreSqliteValidationTrait.php';

trait RestoreTableTrait {

    use RestoreSqliteValidationTrait;

    /**
     * Restore master tables from SQLite files.
     *
     * @param array  $restoreOrder   Tables in restore order.
     * @param array  $tableInventory Table inventory map.
     * @param string $snapshotDir    Snapshot directory.
     * @param array  $options        Restore options.
     * @return array Result with tables_restored, total_rows, errors.
     */
    private function restoreMasterTables(array $restoreOrder, array $tableInventory, string $snapshotDir, array $options): array {
        $tables_restored = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($restoreOrder as $table) {
            $result = $this->restoreSingleMasterTable($table, $tableInventory, $snapshotDir);

            if ($result === null) {
                $errors[] = $result['error'] ?? $table . ': skipped';
                continue;
            }

            if ($result['success']) {
                $tables_restored++;
                $total_rows += $result['rows'];
                $this->log('INFO', sprintf('Restored: %s (%d rows)', $table, $result['rows']));
                continue;
            }

            $errors[] = $table . ': ' . $result['error'];
            $this->log('ERROR', 'Restore failed: ' . $table, array('error' => $result['error']));

            if (!empty($options['strict'])) {
                throw new Exception('Strict mode: table restore failed for ' . $table);
            }
        }

        return array('tables_restored' => $tables_restored, 'total_rows' => $total_rows, 'errors' => $errors);
    }

    /**
     * Restore a single master table.
     *
     * @param string $table          Table name.
     * @param array  $tableInventory Table inventory.
     * @param string $snapshotDir    Snapshot directory.
     * @return array|null Result or null if missing.
     */
    private function restoreSingleMasterTable(string $table, array $tableInventory, string $snapshotDir): ?array {
        $table_info = $tableInventory[$table] ?? null;
        if (!$table_info) {
            return array('success' => false, 'error' => $table . ': not found in inventory', 'rows' => 0);
        }

        $sqlite_path = $snapshotDir . '/' . $table_info['sqlite_file'];
        if (RiseupBooleanHelpers::is_file_missing($sqlite_path)) {
            $this->log('ERROR', 'SQLite file missing for table', array('table' => $table, 'file' => $table_info['sqlite_file']));
            return array('success' => false, 'error' => 'SQLite file missing (' . $table_info['sqlite_file'] . ')', 'rows' => 0);
        }

        return $this->restoreTableFromFile($sqlite_path, $table, 'truncate');
    }

    /**
     * Restore a single table from its individual SQLite file into MySQL.
     *
     * @param string $sqlite_path Path to the table's .sqlite file.
     * @param string $table       MySQL table name.
     * @param string $strategy    'truncate' or 'merge'.
     * @return array Result: success, rows, error.
     */
    private function restoreTableFromFile($sqlite_path, $table, $strategy = 'truncate') {
        try {
            $validated = $this->openAndValidateSqliteTable($sqlite_path, $table);
            if (!$validated['success']) {
                return $validated;
            }

            return $this->batchInsertToMysql(
                $validated['sqlite'], $table, $validated['columns'],
                $strategy, $validated['row_count']
            );
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0);
        }
    }
}
