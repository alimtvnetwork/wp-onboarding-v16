<?php
/**
 * ManagerRestoreTrait — Snapshot restore operations.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/ManagerRestoreValidationTrait.php';

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotErrorType;

trait ManagerRestoreTrait {

    use ManagerRestoreValidationTrait;

    public function restoreSnapshot(int $snapshotId, array $options = array()): array {
        $guard = $this->guardRestorePreConditions($snapshotId, $options);
        if ($guard !== null) {
            return $guard;
        }

        $snapshot = $this->getProvider()->getSnapshot($snapshotId);

        $this->log(LogLevelType::Info->value, 'Starting snapshot restore', array(
            'snapshot_id' => $snapshotId, 'filename' => $snapshot['filename'], 'create_backup' => !empty($options['create_backup']),
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
            return array('success' => false, 'error' => 'Restore requires explicit confirmation (confirm=true)', 'code' => SnapshotErrorType::RestoreNoConfirm->value);
        }

        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available', 'code' => SnapshotErrorType::ProviderNotAvail->value);
        }

        $snapshot = $provider->getSnapshot($snapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found', 'code' => SnapshotErrorType::NotFound->value);
        }

        return $this->validateIncrementalParent($snapshot, $snapshotId);
    }

    private function finalizeRestoreResult(array $result, int $snapshotId, int|null $backupId): array {
        if ($result['success']) {
            $result['backup_id'] = $backupId;
            $this->log(LogLevelType::Info->value, 'Snapshot restored successfully', array(
                'snapshot_id' => $snapshotId, 'tables' => $result['tables'] ?? 0, 'rows' => $result['rows'] ?? 0,
            ));
        } else {
            $this->log(LogLevelType::Error->value, 'Snapshot restore failed', array('snapshot_id' => $snapshotId, 'error' => $result['error']));
        }

        return $result;
    }

    private function executeRestore(array $snapshot, array $options): array {
        $startTime = microtime(true);
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

            $counts = $this->restoreAllTables($sqlite, $tables, $options);
            $sqlite = null;

            return array('success' => true, 'tables' => $counts['tables'], 'rows' => $counts['rows'], 'duration' => microtime(true) - $startTime);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Restore exception', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private function restoreAllTables(PDO $sqlite, array $tables, array $options): array {
        $totalRows = 0;
        $restoredTables = 0;

        foreach ($tables as $table) {
            $result = $this->restoreTable($sqlite, $table);
            if ($result['success']) {
                $totalRows += $result['rows'];
                $restoredTables++;
                $this->log(LogLevelType::Info->value, sprintf('Table %s restored (%d rows)', $table, $result['rows']));
                continue;
            }

            $this->log(LogLevelType::Error->value, 'Failed to restore table: ' . $table, array('error' => $result['error']));
            if (!empty($options['strict'])) {
                throw new Exception('Table restore failed: ' . $table);
            }
        }

        return array('tables' => $restoredTables, 'rows' => $totalRows);
    }
}
