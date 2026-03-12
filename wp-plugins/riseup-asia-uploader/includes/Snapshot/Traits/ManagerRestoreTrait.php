<?php
/**
 * ManagerRestoreTrait — Snapshot restore operations.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Exception;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerRestoreTrait {
    use ManagerRestoreValidationTrait;

    public function restoreSnapshot($snapshotId, $options = []) {
        $guard = $this->guardRestorePreConditions($snapshotId, $options);

        if ($guard !== null) {
            return $guard;
        }

        $snapshot = $this->getProvider()->getSnapshot($snapshotId);
        $this->logRestoreStart($snapshotId, $snapshot, $options);

        $backupId = $this->handlePreRestoreBackup($options, $snapshotId);

        $isBackupError = gettype($backupId) === 'array';

        if ($isBackupError) {
            return $backupId;
        }

        $result = $this->executeRestore($snapshot, $options);

        return $this->finalizeRestoreResult($result, $snapshotId, $backupId);
    }

    private function logRestoreStart($snapshotId, $snapshot, $options) {
        $hasBackupOption = !empty($options['create_backup']);

        $this->log(LogLevelType::Info->value, 'Starting snapshot restore', [
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $snapshot[ResponseKeyType::Filename->value],
            'createBackup'                     => $hasBackupOption,
        ]);
    }

    private function guardRestorePreConditions($snapshotId, $options) {
        $confirmGuard = $this->guardConfirmation($options);

        if ($confirmGuard !== null) {
            return $confirmGuard;
        }

        $providerGuard = $this->guardProviderAvailable();

        if ($providerGuard !== null) {
            return $providerGuard;
        }

        return $this->guardSnapshotExists($snapshotId);
    }

    private function guardConfirmation($options) {
        $isConfirmMissing = empty($options['confirm']);
        $isConfirmNotTrue = (!$isConfirmMissing) && $options['confirm'] !== true;
        $isUnconfirmed    = $isConfirmMissing || $isConfirmNotTrue;

        if ($isUnconfirmed) {
            return ResultHelper::errorWithCode(
                'Restore requires explicit confirmation (confirm=true)',
                SnapshotErrorType::RestoreNoConfirm->value
            );
        }

        return null;
    }

    private function guardProviderAvailable() {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null || $provider === false);

        if ($isProviderMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotProviderMissing->value,
                SnapshotErrorType::ProviderNotAvail->value
            );
        }

        return null;
    }

    private function guardSnapshotExists($snapshotId) {
        $provider = $this->getProvider();
        $snapshot = $provider->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotNotFound->value,
                SnapshotErrorType::NotFound->value
            );
        }

        return $this->validateIncrementalParent($snapshot, $snapshotId);
    }

    private function finalizeRestoreResult($result, $snapshotId, $backupId) {
        $isRestoreSuccess = !empty($result[ResponseKeyType::Success->value]);

        if ($isRestoreSuccess) {
            $result[ResponseKeyType::BackupId->value] = $backupId;
            $this->logRestoreSuccess($snapshotId, $result);

            return $result;
        }

        $this->logRestoreFailure($snapshotId, $result);

        return $result;
    }

    private function logRestoreSuccess($snapshotId, $result) {
        $hasTablesCount = isset($result[ResponseKeyType::Tables->value]);
        $hasRowsCount = isset($result[ResponseKeyType::Rows->value]);

        $tablesCount = $hasTablesCount ? $result[ResponseKeyType::Tables->value] : 0;
        $rowsCount = $hasRowsCount ? $result[ResponseKeyType::Rows->value] : 0;

        $this->log(LogLevelType::Info->value, 'Snapshot restored successfully', [
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Tables->value     => $tablesCount,
            ResponseKeyType::Rows->value       => $rowsCount,
        ]);
    }

    private function logRestoreFailure($snapshotId, $result) {
        $this->log(LogLevelType::Error->value, 'Snapshot restore failed', [
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Error->value      => $result[ResponseKeyType::Error->value],
        ]);
    }

    private function executeRestore($snapshot, $options) {
        $startTime = microtime(true);
        $filepath = $snapshot['filepath'];

        $isFileMissing = PathHelper::isFileMissing($filepath);

        if ($isFileMissing) {
            return ResultHelper::error('Snapshot file not found: ' . basename($filepath));
        }

        try {
            return $this->runSqliteRestore($filepath, $snapshot, $options, $startTime);
        } catch (Exception $e) {
            $this->logRestoreException($e);

            return ResultHelper::errorFromException($e);
        }
    }

    private function runSqliteRestore($filepath, $snapshot, $options, $startTime) {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = $this->getRestoreTables($snapshot, $options);
        $isTablesEmpty = empty($tables);

        if ($isTablesEmpty) {
            return ResultHelper::error('No tables to restore');
        }

        $counts = $this->restoreAllTables($sqlite, $tables, $options);
        $sqlite = null;

        return ResultHelper::ok([
            ResponseKeyType::Tables->value   => $counts[ResponseKeyType::Tables->value],
            ResponseKeyType::Rows->value     => $counts[ResponseKeyType::Rows->value],
            ResponseKeyType::Duration->value => microtime(true) - $startTime,
        ]);
    }

    private function logRestoreException($e) {
        $this->log(LogLevelType::Error->value, 'Restore exception', [
            ResponseKeyType::Error->value => $e->getMessage(),
            'trace'                       => $e->getTraceAsString(),
        ]);
    }

    private function restoreAllTables($sqlite, $tables, $options) {
        $totalRows = 0;
        $restoredTables = 0;

        foreach ($tables as $table) {
            $result = $this->restoreTable($sqlite, $table);
            $outcome = $this->processTableRestoreResult($table, $result, $options);

            $totalRows += $outcome['rows'];
            $restoredTables += $outcome['restored'];
        }

        return [
            ResponseKeyType::Tables->value => $restoredTables,
            ResponseKeyType::Rows->value   => $totalRows,
        ];
    }

    private function processTableRestoreResult($table, $result, $options) {
        $isTableRestoreSuccess = !empty($result[ResponseKeyType::Success->value]);

        if ($isTableRestoreSuccess) {
            $this->log(LogLevelType::Info->value, sprintf('Table %s restored (%d rows)', $table, $result[ResponseKeyType::Rows->value]));

            return ['restored' => 1, 'rows' => $result[ResponseKeyType::Rows->value]];
        }

        $this->log(LogLevelType::Error->value, 'Failed to restore table: ' . $table, [
            ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value],
        ]);

        $isStrictMode = !empty($options['strict']);

        if ($isStrictMode) {
            throw new Exception('Table restore failed: ' . $table);
        }

        return ['restored' => 0, 'rows' => 0];
    }
}
