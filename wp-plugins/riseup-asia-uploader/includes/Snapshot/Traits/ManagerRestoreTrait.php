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
use RiseupAsia\Helpers\BooleanHelpers;

trait ManagerRestoreTrait {

    use ManagerRestoreValidationTrait;

    public function restoreSnapshot(int $snapshotId, array $options = array()): array {
        $guard = $this->guardRestorePreConditions($snapshotId, $options);
        if ($guard !== null) {
            return $guard;
        }

        $snapshot = $this->getProvider()->getSnapshot($snapshotId);

        $hasBackupOption = BooleanHelpers::hasValue($options['create_backup']);
        $this->log(LogLevelType::Info->value, 'Starting snapshot restore', array(
            'snapshot_id' => $snapshotId, 'filename' => $snapshot['filename'], 'create_backup' => $hasBackupOption,
        ));

        $backupId = $this->handlePreRestoreBackup($options, $snapshotId);
        if ($backupId instanceof array) {
            return $backupId;
        }

        $result = $this->executeRestore($snapshot, $options);

        return $this->finalizeRestoreResult($result, $snapshotId, $backupId);
    }

    private function guardRestorePreConditions(int $snapshotId, array $options): ?array {
        if (empty($options['confirm']) || $options['confirm'] !== true) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Restore requires explicit confirmation (confirm=true)', ResponseKeyType::Code->value => SnapshotErrorType::RestoreNoConfirm->value);
        }

        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null || $provider === false);
        if ($isProviderMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotProviderMissing->value, ResponseKeyType::Code->value => SnapshotErrorType::ProviderNotAvail->value);
        }

        $snapshot = $provider->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);
        if ($isSnapshotMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotNotFound->value, ResponseKeyType::Code->value => SnapshotErrorType::NotFound->value);
        }

        return $this->validateIncrementalParent($snapshot, $snapshotId);
    }

    private function finalizeRestoreResult(
        array $result,
        int $snapshotId,
        int|null $backupId,
    ): array {
        if ($result[ResponseKeyType::Success->value]) {
            $result['backup_id'] = $backupId;
            $this->log(LogLevelType::Info->value, 'Snapshot restored successfully', array(
                'snapshot_id' => $snapshotId, 'tables' => $result['tables'] ?? 0, 'rows' => $result['rows'] ?? 0,
            ));
        } else {
            $this->log(LogLevelType::Error->value, 'Snapshot restore failed', array('snapshot_id' => $snapshotId, 'error' => $result[ResponseKeyType::Error->value]));
        }

        return $result;
    }

    private function executeRestore(array $snapshot, array $options): array {
        $startTime = microtime(true);
        $filepath = $snapshot['filepath'];

        if (PathHelper::isFileMissing($filepath)) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Snapshot file not found: ' . basename($filepath));
        }

        try {
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = $this->getRestoreTables($snapshot, $options);
            if (empty($tables)) {

                return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'No tables to restore');
            }

            $counts = $this->restoreAllTables($sqlite, $tables, $options);
            $sqlite = null;

            return array(ResponseKeyType::Success->value => true, 'tables' => $counts['tables'], 'rows' => $counts['rows'], 'duration' => microtime(true) - $startTime);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Restore exception', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage());
        }
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
            if ($result[ResponseKeyType::Success->value]) {
                $totalRows += $result['rows'];
                $restoredTables++;
                $this->log(LogLevelType::Info->value, sprintf('Table %s restored (%d rows)', $table, $result['rows']));
                continue;
            }

            $this->log(LogLevelType::Error->value, 'Failed to restore table: ' . $table, array('error' => $result[ResponseKeyType::Error->value]));
            if (BooleanHelpers::hasValue($options['strict'])) {

                throw new Exception('Table restore failed: ' . $table);
            }
        }

        return array('tables' => $restoredTables, 'rows' => $totalRows);
    }
}
