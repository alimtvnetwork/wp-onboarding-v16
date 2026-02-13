<?php
/**
 * ManagerRestoreTrait — Snapshot restore operations.
 *
 * Shell trait — validation delegated to ManagerRestoreValidationTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/ManagerRestoreValidationTrait.php';

trait ManagerRestoreTrait {

    use ManagerRestoreValidationTrait;

    /**
     * Restore from a snapshot with safety checks.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Result with success status.
     */
    public function restoreSnapshot($snapshot_id, $options = array()) {
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array('success' => false, 'error' => 'Restore requires explicit confirmation (confirm=true)', 'code' => ERR_RESTORE_NO_CONFIRM);
        }

        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available', 'code' => ERR_PROVIDER_NOT_AVAILABLE);
        }

        $snapshot = $provider->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found', 'code' => ERR_SNAPSHOT_NOT_FOUND);
        }

        $guard = $this->validateIncrementalParent($snapshot, $snapshot_id);
        if ($guard !== null) {
            return $guard;
        }

        $this->log(LOG_LEVEL_INFO, 'Starting snapshot restore', array(
            'snapshot_id' => $snapshot_id, 'filename' => $snapshot['filename'], 'create_backup' => !empty($options['create_backup']),
        ));

        $backup_id = $this->handlePreRestoreBackup($options, $snapshot_id);
        if ($backup_id instanceof array) {
            return $backup_id;
        }

        $result = $this->executeRestore($snapshot, $options);

        if ($result['success']) {
            $result['backup_id'] = $backup_id;
            $this->log(LOG_LEVEL_INFO, 'Snapshot restored successfully', array(
                'snapshot_id' => $snapshot_id, 'tables' => $result['tables'] ?? 0, 'rows' => $result['rows'] ?? 0,
            ));
        } else {
            $this->log(LOG_LEVEL_ERROR, 'Snapshot restore failed', array('snapshot_id' => $snapshot_id, 'error' => $result['error']));
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

        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found: ' . basename($filepath));
        }

        try {
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = $this->getRestoreTables($snapshot, $options);
            if (empty($tables)) {
                return array('success' => false, 'error' => 'No tables to restore');
            }

            $this->log(LOG_LEVEL_INFO, 'Restoring tables', array('count' => count($tables), 'mode' => $options['mode'] ?? 'full'));

            $total_rows = 0;
            $restored_tables = 0;

            foreach ($tables as $table) {
                $result = $this->restoreTable($sqlite, $table);
                if ($result['success']) {
                    $total_rows += $result['rows'];
                    $restored_tables++;
                    $this->log(LOG_LEVEL_INFO, sprintf('Table %s restored (%d rows)', $table, $result['rows']));
                    continue;
                }

                $this->log(LOG_LEVEL_ERROR, 'Failed to restore table: ' . $table, array('error' => $result['error']));
                if (!empty($options['strict'])) {
                    throw new Exception('Table restore failed: ' . $table);
                }
            }

            $sqlite = null;

            return array('success' => true, 'tables' => $restored_tables, 'rows' => $total_rows, 'duration' => microtime(true) - $start_time);
        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'Restore exception', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
}
