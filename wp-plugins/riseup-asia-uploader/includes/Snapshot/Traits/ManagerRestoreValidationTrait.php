<?php
/**
 * ManagerRestoreValidationTrait — Incremental parent validation and pre-restore backup handling.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Helpers\PathHelper;

use RiseupAsia\Helpers\ResultHelper;

trait ManagerRestoreValidationTrait {
    private function validateIncrementalParent($snapshot, $snapshotId) {
        $hasScope = isset($snapshot['scope']);
        $isIncremental = $hasScope && $snapshot['scope'] === SnapshotModeType::Incremental->value;
        $isFullSnapshot = ($isIncremental === false);

        if ($isFullSnapshot) {
            return null;
        }

        $tablesJson = isset($snapshot['tables_json']) ? $snapshot['tables_json'] : '{}';
        $tablesMeta = json_decode($tablesJson, true);
        $masterDirname = isset($tablesMeta['master']) ? $tablesMeta['master'] : null;

        $isMasterDirnameMissing = ($masterDirname === null);

        if ($isMasterDirnameMissing) {
            return null;
        }

        $masterDir = dirname(dirname($snapshot['filepath']));
        $isMasterDirMissing = PathHelper::isDirMissing($masterDir);
        $isMasterRootMissing = PathHelper::isFileMissing($masterDir . PathDatabaseType::Root->value);
        $isMasterMissing = $isMasterDirMissing || $isMasterRootMissing;
        $isMasterPresent = ($isMasterMissing === false);

        if ($isMasterPresent) {
            return null;
        }

        $this->log(LogLevelType::Error->value, 'Incremental restore blocked: parent full snapshot missing', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            'masterDir'                        => $masterDirname,
            'expectedPath'                     => $masterDir,
        ));

        return ResultHelper::errorWithCode(
            'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
            SnapshotErrorType::IncrementalNoParent->value
        );
    }

    /**
     * Handle pre-restore backup creation with optional strict enforcement.
     *
     * @return int|array|null Backup ID, error array, or null.
     */
    private function handlePreRestoreBackup($options, $snapshotId) {
        $hasCreateBackup = isset($options['create_backup']);
        $isBackupExplicitlyDisabled = $hasCreateBackup && $options['create_backup'] === false;

        if ($isBackupExplicitlyDisabled) {
            return null;
        }

        $backupResult = $this->createPreRestoreBackup($snapshotId);
        $isBackupCreated = !empty($backupResult[ResponseKeyType::Success->value]);

        if ($isBackupCreated) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup created', array(ResponseKeyType::BackupId->value => $backupResult[ResponseKeyType::SnapshotId->value]));

            return $backupResult[ResponseKeyType::SnapshotId->value];
        }

        $this->log(LogLevelType::Warn->value, 'Failed to create pre-restore backup', array(ResponseKeyType::Error->value => $backupResult[ResponseKeyType::Error->value]));

        if (!empty($options['require_backup'])) {
            return ResultHelper::error('Pre-restore backup failed: ' . $backupResult[ResponseKeyType::Error->value]);
        }

        return null;
    }

    private function getRestoreTables($snapshot, $options) {
        $allTables = json_decode($snapshot['tables_json'], true);
        $mode = isset($options['mode']) ? $options['mode'] : RestoreModeType::Full->value;

        $hasTablesSelection = !empty($options['tables']);
        $isSelective = ($mode === RestoreModeType::Selective->value) && $hasTablesSelection;

        if ($isSelective) {
            return array_intersect($allTables, $options['tables']);
        }

        return $allTables;
    }
}
