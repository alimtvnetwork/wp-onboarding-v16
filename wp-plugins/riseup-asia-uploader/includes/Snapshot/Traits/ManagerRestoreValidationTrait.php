<?php
/**
 * ManagerRestoreValidationTrait — Incremental parent validation and pre-restore backup handling.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotErrorType;

trait ManagerRestoreValidationTrait {

    /**
     * Validate that an incremental snapshot's parent full snapshot exists.
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
        $isMasterMissing = RiseupBooleanHelpers::isDirMissing($master_dir) || RiseupBooleanHelpers::isFileMissing($master_dir . '/a-root.db');
        if (!$isMasterMissing) {
            return null;
        }

        $this->log(LogLevelType::Error->value, 'Incremental restore blocked: parent full snapshot missing', array(
            'snapshot_id' => $snapshot_id, 'master_dir' => $master_dirname, 'expected_path' => $master_dir,
        ));

        return array(
            'success' => false,
            'error'   => 'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
            'code'    => SnapshotErrorType::IncrementalNoParent->value,
        );
    }

    /**
     * Handle pre-restore backup creation with optional strict enforcement.
     */
    private function handlePreRestoreBackup($options, $snapshot_id) {
        $shouldBackup = (!isset($options['create_backup']) || $options['create_backup'] === true);
        if (!$shouldBackup) {
            return null;
        }

        $backup_result = $this->createPreRestoreBackup($snapshot_id);

        if ($backup_result['success']) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup created', array('backup_id' => $backup_result['snapshot_id']));
            return $backup_result['snapshot_id'];
        }

        $this->log(LogLevelType::Warn->value, 'Failed to create pre-restore backup', array('error' => $backup_result['error']));

        if (!empty($options['require_backup'])) {
            return array('success' => false, 'error' => 'Pre-restore backup failed: ' . $backup_result['error']);
        }

        return null;
    }

    /**
     * Determine which tables to restore.
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
