<?php
/**
 * ManagerRestoreValidationTrait — Incremental parent validation and pre-restore backup handling.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait ManagerRestoreValidationTrait {

    /**
     * Validate that an incremental snapshot's parent full snapshot exists.
     *
     * @param array $snapshot    Snapshot record.
     * @param int   $snapshot_id Snapshot ID (for logging).
     * @return array|null Error array if blocked, null if OK.
     */
    private function validateIncrementalParent($snapshot, $snapshot_id) {
        $isIncremental = (isset($snapshot['scope']) && $snapshot['scope'] === 'incremental');
        if (!$isIncremental) {
            return null;
        }

        $tables_meta = json_decode($snapshot['tables_json'] ?? '{}', true);
        $master_dirname = $tables_meta['master'] ?? null;

        if (!$master_dirname) {
            return null;
        }

        $master_dir = dirname(dirname($snapshot['filepath']));
        $isMasterMissing = RiseupBooleanHelpers::is_dir_missing($master_dir) || RiseupBooleanHelpers::is_file_missing($master_dir . '/a-root.db');
        if (!$isMasterMissing) {
            return null;
        }

        $this->log(LOG_LEVEL_ERROR, 'Incremental restore blocked: parent full snapshot missing', array(
            'snapshot_id' => $snapshot_id, 'master_dir' => $master_dirname, 'expected_path' => $master_dir,
        ));

        return array(
            'success' => false,
            'error'   => 'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
            'code'    => ERR_INCREMENTAL_NO_PARENT,
        );
    }

    /**
     * Handle pre-restore backup creation with optional strict enforcement.
     *
     * @param array $options     Restore options.
     * @param int   $snapshot_id Snapshot being restored.
     * @return int|null|array Backup ID, null if skipped, or error array.
     */
    private function handlePreRestoreBackup($options, $snapshot_id) {
        $shouldBackup = (!isset($options['create_backup']) || $options['create_backup'] === true);
        if (!$shouldBackup) {
            return null;
        }

        $backup_result = $this->createPreRestoreBackup($snapshot_id);

        if ($backup_result['success']) {
            $this->log(LOG_LEVEL_INFO, 'Pre-restore backup created', array('backup_id' => $backup_result['snapshot_id']));
            return $backup_result['snapshot_id'];
        }

        $this->log(LOG_LEVEL_WARN, 'Failed to create pre-restore backup', array('error' => $backup_result['error']));

        if (!empty($options['require_backup'])) {
            return array('success' => false, 'error' => 'Pre-restore backup failed: ' . $backup_result['error']);
        }

        return null;
    }

    /**
     * Determine which tables to restore.
     *
     * @param array $snapshot Snapshot record.
     * @param array $options  Restore options.
     * @return array Table names.
     */
    private function getRestoreTables($snapshot, $options) {
        $all_tables = json_decode($snapshot['tables_json'], true);
        $mode = isset($options['mode']) ? $options['mode'] : 'full';

        $isSelective = ($mode === 'selective' && !empty($options['tables']));
        if ($isSelective) {
            return array_intersect($all_tables, $options['tables']);
        }

        return $all_tables;
    }
}
