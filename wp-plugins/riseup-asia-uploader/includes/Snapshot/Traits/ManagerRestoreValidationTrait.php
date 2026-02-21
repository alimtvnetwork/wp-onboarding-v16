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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerRestoreValidationTrait {

    private function validateIncrementalParent(array $snapshot, int $snapshotId): ?array {
        $isIncremental = (isset($snapshot['scope']) && $snapshot['scope'] === SnapshotModeType::Incremental->value);
        $isFullSnapshot = ($isIncremental === false);
        if ($isFullSnapshot) {
            return null;
        }

        $tablesMeta = json_decode($snapshot['tables_json'] ?? '{}', true);
        $masterDirname = $tablesMeta['master'] ?? null;

        $isMasterDirnameMissing = ($masterDirname === null);
        if ($isMasterDirnameMissing) {
            return null;
        }

        $masterDir = dirname(dirname($snapshot['filepath']));
        $isMasterMissing = PathHelper::isDirMissing($masterDir) || PathHelper::isFileMissing($masterDir . '/a-root.db');
        $isMasterPresent = ($isMasterMissing === false);
        if ($isMasterPresent) {
            return null;
        }

        $this->log(LogLevelType::Error->value, 'Incremental restore blocked: parent full snapshot missing', array(
            ResponseKeyType::SnapshotId->value => $snapshotId, 'master_dir' => $masterDirname, 'expected_path' => $masterDir,
        ));

        return ResultHelper::errorWithCode(
            'Cannot restore incremental snapshot: the parent full snapshot is missing. Please restore from a full backup instead.',
            SnapshotErrorType::IncrementalNoParent->value,
        );
    }

    /**
     * Handle pre-restore backup creation with optional strict enforcement.
     *
     * @return int|array|null Backup ID, error array, or null.
     */
    private function handlePreRestoreBackup(array $options, int $snapshotId): int|array|null {
        $isBackupExplicitlyDisabled = (isset($options['create_backup']) && $options['create_backup'] === false);
        if ($isBackupExplicitlyDisabled) {
            return null;
        }

        $backupResult = $this->createPreRestoreBackup($snapshotId);

        if ($backupResult[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup created', array(ResponseKeyType::BackupId->value => $backupResult[ResponseKeyType::SnapshotId->value]));

            return $backupResult[ResponseKeyType::SnapshotId->value];
        }

        $this->log(LogLevelType::Warn->value, 'Failed to create pre-restore backup', array(ResponseKeyType::Error->value => $backupResult[ResponseKeyType::Error->value]));

        if (BooleanHelpers::hasValue($options['require_backup'])) {

            return ResultHelper::error('Pre-restore backup failed: ' . $backupResult[ResponseKeyType::Error->value]);
        }

        return null;
    }

    private function getRestoreTables(array $snapshot, array $options): array {
        $allTables = json_decode($snapshot['tables_json'], true);
        $mode = $options['mode'] ?? RestoreModeType::Full->value;

        $isSelective = ($mode === RestoreModeType::Selective->value && BooleanHelpers::hasValue($options['tables']));
        if ($isSelective) {
            return array_intersect($allTables, $options['tables']);
        }

        return $allTables;
    }
}
