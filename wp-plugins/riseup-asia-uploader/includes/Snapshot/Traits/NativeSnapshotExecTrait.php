<?php
/**
 * NativeSnapshotExecTrait — snapshot execution, export loop, and result building.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\PathHelper;

trait NativeSnapshotExecTrait {
    public function executeSnapshot(int $snapshotId, array $tables): array {
        $startTime = microtime(true);
        $guardError = $this->guardSnapshotReady($snapshotId);

        if ($guardError !== null) {
            return $guardError;
        }

        try {
            $snapshot = $this->getSnapshot($snapshotId);

            return $this->runSnapshotExport($snapshotId, $snapshot['filepath'], $tables, $startTime);
        } catch (Throwable $e) {
            return $this->handleSnapshotException($snapshotId, $e);
        } finally {
            $this->releaseLock();
        }
    }

    private function guardSnapshotReady(int $snapshotId): ?array {
        $snapshot = $this->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null);

        if ($isSnapshotMissing) {
            return $this->snapshotNotFoundError();
        }

        $isLockFailed = ($this->acquireLock() === false);

        if ($isLockFailed) {
            return $this->handleLockFailure($snapshotId);
        }

        return null;
    }

    private function snapshotNotFoundError(): array {
        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => 'Snapshot record not found',
        );
    }

    private function handleLockFailure(int $snapshotId): array {
        $this->updateSnapshotStatus(
            $snapshotId,
            SnapshotStatusType::Failed->value,
            'Failed to acquire lock',
        );

        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => 'Failed to acquire lock',
        );
    }

    private function handleSnapshotException(int $snapshotId, Throwable $e): array {
        $this->log(LogLevelType::Error->value, 'Snapshot failed', array(
            ResponseKeyType::Error->value => $e->getMessage(),
            'trace'                       => $e->getTraceAsString(),
        ));

        $this->updateSnapshotStatus(
            $snapshotId,
            SnapshotStatusType::Failed->value,
            $e->getMessage(),
        );

        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => $e->getMessage(),
        );
    }

    private function runSnapshotExport(
        int $snapshotId,
        string $filepath,
        array $tables,
        float $startTime,
    ): array {
        $this->updateSnapshotStatus($snapshotId, SnapshotStatusType::Running->value, 'Exporting');
        $sqlite = $this->initSqliteForExport($filepath);

        try {
            $tableCounts = $this->exportAllTables($sqlite, $tables, $snapshotId);
            $sqlite->commit();

            return $this->buildExportResult($snapshotId, $filepath, $tables, $tableCounts, $startTime);
        } catch (Throwable $e) {
            $sqlite->rollBack();

            throw $e;
        }
    }

    private function initSqliteForExport(string $filepath): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->beginTransaction();

        return $sqlite;
    }

    private function exportAllTables(
        PDO $sqlite,
        array $tables,
        int $snapshotId,
    ): array {
        $tableCounts = array();

        foreach ($tables as $table) {
            $result = $this->exportTable($sqlite, $table, $snapshotId);
            $tableCounts[$table] = $result;
            $this->logTableExportResult($table, $result);
        }

        return $tableCounts;
    }

    private function logTableExportResult(string $table, array $result): void {
        if ($result[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Debug->value, 'Exported table', array(
                'table'                           => $table,
                ResponseKeyType::Rows->value      => $result[ResponseKeyType::Rows->value],
                ResponseKeyType::Bytes->value     => PathHelper::formatBytes($result[ResponseKeyType::Bytes->value]),
            ));

            return;
        }

        $this->log(LogLevelType::Error->value, 'Failed to export table', array(
            'table'                           => $table,
            ResponseKeyType::Error->value     => $result[ResponseKeyType::Error->value],
        ));
    }

    private function buildExportResult(
        int $snapshotId,
        string $filepath,
        array $tables,
        array $tableCounts,
        float $startTime,
    ): array {
        $totals = $this->sumTableCounts($tableCounts);
        $duration = microtime(true) - $startTime;

        $this->logSnapshotComplete($snapshotId, $tables, $totals, $duration);
        $this->markSnapshotComplete($snapshotId, $tables, $totals['bytes']);

        return $this->buildExportResultArray($filepath, $tables, $totals);
    }

    private function buildExportResultArray(string $filepath, array $tables, array $totals): array {
        return array(
            ResponseKeyType::Success->value  => true,
            ResponseKeyType::Tables->value   => count($tables),
            ResponseKeyType::Rows->value     => $totals['rows'],
            ResponseKeyType::Bytes->value    => $totals['bytes'],
            ResponseKeyType::FilePath->value => $filepath,
        );
    }

    private function sumTableCounts(array $tableCounts): array {
        $totalRows = 0;
        $totalBytes = 0;

        foreach ($tableCounts as $result) {
            $totalRows += $result[ResponseKeyType::Rows->value];
            $totalBytes += $result[ResponseKeyType::Bytes->value];
        }

        return array('rows' => $totalRows, 'bytes' => $totalBytes);
    }

    private function logSnapshotComplete(int $snapshotId, array $tables, array $totals, float $duration): void {
        $this->log(LogLevelType::Info->value, 'Snapshot complete', array(
            'id'                             => $snapshotId,
            ResponseKeyType::Tables->value   => count($tables),
            ResponseKeyType::Rows->value     => $totals['rows'],
            ResponseKeyType::Bytes->value    => PathHelper::formatBytes($totals['bytes']),
            ResponseKeyType::Duration->value => round($duration, 2) . 's',
        ));
    }

    private function markSnapshotComplete(int $snapshotId, array $tables, int $totalBytes): void {
        $this->updateSnapshotStatus(
            $snapshotId,
            SnapshotStatusType::Complete->value,
            sprintf('Exported %d tables (%s)', count($tables), PathHelper::formatBytes($totalBytes)),
        );
    }
}
