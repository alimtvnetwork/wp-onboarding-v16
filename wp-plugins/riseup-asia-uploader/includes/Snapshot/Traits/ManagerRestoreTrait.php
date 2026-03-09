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
use Throwable;
use Exception;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerRestoreTrait {
    use ManagerRestoreValidationTrait;

    public function restoreSnapshot(int $snapshotId, array $options = array()): array {
        $guard = $this->guardRestorePreConditions($snapshotId, $options);

        if ($guard !== null) {
            return $guard;
        }

        $snapshot = $this->getProvider()->getSnapshot($snapshotId);
        $this->logRestoreStart($snapshotId, $snapshot, $options);

        $backupId = $this->handlePreRestoreBackup($options, $snapshotId);

        if ($backupId instanceof array) {
            return $backupId;
        }

        $result = $this->executeRestore($snapshot, $options);

        return $this->finalizeRestoreResult($result, $snapshotId, $backupId);
    }

    private function logRestoreStart(int $snapshotId, array $snapshot, array $options): void {
        $hasBackupOption = !empty($options['create_backup']);

        $this->log(LogLevelType::Info->value, 'Starting snapshot restore', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value   => $snapshot[ResponseKeyType::Filename->value],
            'createBackup'                     => $hasBackupOption,
        ));
    }

    private function guardRestorePreConditions(int $snapshotId, array $options): ?array {
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

    private function guardConfirmation(array $options): ?array {
        $isConfirmMissing = empty($options['confirm']);
        $isConfirmNotTrue = !$isConfirmMissing && $options['confirm'] !== true;
        $isUnconfirmed    = $isConfirmMissing || $isConfirmNotTrue;

        if ($isUnconfirmed) {
            return ResultHelper::errorWithCode(
                'Restore requires explicit confirmation (confirm=true)',
                SnapshotErrorType::RestoreNoConfirm->value,
            );
        }

        return null;
    }

    private function guardProviderAvailable(): ?array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null || $provider === false);

        if ($isProviderMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotProviderMissing->value,
                SnapshotErrorType::ProviderNotAvail->value,
            );
        }

        return null;
    }

    private function guardSnapshotExists(int $snapshotId): ?array {
        $provider = $this->getProvider();
        $snapshot = $provider->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotNotFound->value,
                SnapshotErrorType::NotFound->value,
            );
        }

        return $this->validateIncrementalParent($snapshot, $snapshotId);
    }

    private function finalizeRestoreResult(
        array $result,
        int $snapshotId,
        int|null $backupId,
    ): array {
        if ($result[ResponseKeyType::Success->value]) {
            $result[ResponseKeyType::BackupId->value] = $backupId;
            $this->logRestoreSuccess($snapshotId, $result);
        } else {
            $this->logRestoreFailure($snapshotId, $result);
        }

        return $result;
    }

    private function logRestoreSuccess(int $snapshotId, array $result): void {
        $this->log(LogLevelType::Info->value, 'Snapshot restored successfully', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Tables->value     => $result[ResponseKeyType::Tables->value] ?? 0,
            ResponseKeyType::Rows->value       => $result[ResponseKeyType::Rows->value] ?? 0,
        ));
    }

    private function logRestoreFailure(int $snapshotId, array $result): void {
        $this->log(LogLevelType::Error->value, 'Snapshot restore failed', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Error->value      => $result[ResponseKeyType::Error->value],
        ));
    }

    private function executeRestore(array $snapshot, array $options): array {
        $startTime = microtime(true);
        $filepath = $snapshot['filepath'];

        if (PathHelper::isFileMissing($filepath)) {
            return ResultHelper::error('Snapshot file not found: ' . basename($filepath));
        }

        try {
            return $this->runSqliteRestore($filepath, $snapshot, $options, $startTime);
        } catch (Throwable $e) {
            $this->logRestoreException($e);

            return ResultHelper::errorFromException($e);
        }
    }

    private function runSqliteRestore(string $filepath, array $snapshot, array $options, float $startTime): array {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = $this->getRestoreTables($snapshot, $options);

        if (empty($tables)) {
            return ResultHelper::error('No tables to restore');
        }

        $counts = $this->restoreAllTables($sqlite, $tables, $options);
        $sqlite = null;

        return ResultHelper::ok(array(
            ResponseKeyType::Tables->value   => $counts[ResponseKeyType::Tables->value],
            ResponseKeyType::Rows->value     => $counts[ResponseKeyType::Rows->value],
            ResponseKeyType::Duration->value => microtime(true) - $startTime,
        ));
    }

    private function logRestoreException(Throwable $e): void {
        $this->log(LogLevelType::Error->value, 'Restore exception', array(
            ResponseKeyType::Error->value => $e->getMessage(),
            'trace'                       => $e->getTraceAsString(),
        ));
    }

    private function restoreAllTables(
        PDO $sqlite,
        array $tables,
        array $options,
    ): array {
        $totalRows = 0;
        $restoredTables = 0;

        foreach ($tables as $table) {
            $result = $this->restoreTable($sqlite, $table);
            $outcome = $this->processTableRestoreResult($table, $result, $options);

            $totalRows += $outcome['rows'];
            $restoredTables += $outcome['restored'];
        }

        return array(
            ResponseKeyType::Tables->value => $restoredTables,
            ResponseKeyType::Rows->value   => $totalRows,
        );
    }

    private function processTableRestoreResult(string $table, array $result, array $options): array {
        if ($result[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Info->value, sprintf('Table %s restored (%d rows)', $table, $result[ResponseKeyType::Rows->value]));

            return array('restored' => 1, 'rows' => $result[ResponseKeyType::Rows->value]);
        }

        $this->log(LogLevelType::Error->value, 'Failed to restore table: ' . $table, array(
            ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value],
        ));

        if (!empty($options['strict'])) {
            throw new Exception('Table restore failed: ' . $table);
        }

        return array('restored' => 0, 'rows' => 0);
    }
}
