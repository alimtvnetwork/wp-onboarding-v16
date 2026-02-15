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

    private function validateIncrementalParent(array $snapshot, int $snapshotId): ?array {
        $isIncremental = (isset($snapshot['scope']) && $snapshot['scope'] === 'incremental');
        if (!$isIncremental) {
            return null;
        }

        $tablesMeta = json_decode($snapshot['tables_json'] ?? '{}', true);
        $masterDirname = $tablesMeta['master'] ?? null;

        if (!$masterDirname) {
            return null;
        }

        $masterDir = dirname(dirname($snapshot['filepath']));
        $isMasterMissing = RiseupBooleanHelpers::isDirMissing($masterDir) || RiseupBooleanHelpers::isFileMissing($masterDir . '/a-root.db');
        if (!$isMasterMissing) {
            return null;
        }

        $this->log(LogLevelType::Error->value, 'Incremental restore blocked: parent full snapshot missing', array(
            'snapshot_id' => $snapshotId, 'master_dir' => $masterDirname, 'expected_path' => $masterDir,
        ));

        return array(
            'success' => false,
            'error'   => 'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
            'code'    => SnapshotErrorType::IncrementalNoParent->value,
        );
    }

    /**
     * Handle pre-restore backup creation with optional strict enforcement.
     *
     * @return int|array|null Backup ID, error array, or null.
     */
    private function handlePreRestoreBackup(array $options, int $snapshotId): int|array|null {
        $shouldBackup = (!isset($options['create_backup']) || $options['create_backup'] === true);
        if (!$shouldBackup) {
            return null;
        }

        $backupResult = $this->createPreRestoreBackup($snapshotId);

        if ($backupResult['success']) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup created', array('backup_id' => $backupResult['snapshot_id']));
            return $backupResult['snapshot_id'];
        }

        $this->log(LogLevelType::Warn->value, 'Failed to create pre-restore backup', array('error' => $backupResult['error']));

        if (!empty($options['require_backup'])) {
            return array('success' => false, 'error' => 'Pre-restore backup failed: ' . $backupResult['error']);
        }

        return null;
    }

    private function getRestoreTables(array $snapshot, array $options): array {
        $allTables = json_decode($snapshot['tables_json'], true);
        $mode = $options['mode'] ?? 'full';

        $isSelective = ($mode === 'selective' && !empty($options['tables']));
        if ($isSelective) {
            return array_intersect($allTables, $options['tables']);
        }

        return $allTables;
    }
}
