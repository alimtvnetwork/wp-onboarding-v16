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
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\PathHelper;

trait NativeSnapshotExecTrait {

    public function executeSnapshot(int $snapshotId, array $tables): array {
        $start_time = microtime(true);

        $snapshot = $this->getSnapshot($snapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot record not found');
        }

        if (!$this->acquireLock()) {
            $this->updateSnapshotStatus($snapshotId, SnapshotStatusType::Failed->value, 'Failed to acquire lock');
            return array('success' => false, 'error' => 'Failed to acquire lock');
        }

        try {
            return $this->runSnapshotExport($snapshotId, $snapshot['filepath'], $tables, $start_time);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->updateSnapshotStatus($snapshotId, SnapshotStatusType::Failed->value, $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        } finally {
            $this->releaseLock();
        }
    }

    private function runSnapshotExport(int $snapshotId, string $filepath, array $tables, float $startTime): array {
        $this->updateSnapshotStatus($snapshotId, SnapshotStatusType::Running->value, 'Exporting');

        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->beginTransaction();

        try {
            $table_counts = $this->exportAllTables($sqlite, $tables, $snapshotId);
            $sqlite->commit();
            return $this->buildExportResult($snapshotId, $filepath, $tables, $table_counts, $startTime);
        } catch (Throwable $e) {
            $sqlite->rollBack();
            throw $e;
        }
    }

    private function exportAllTables(PDO $sqlite, array $tables, int $snapshotId): array {
        $table_counts = array();

        foreach ($tables as $table) {
            $result = $this->exportTable($sqlite, $table, $snapshotId);
            $table_counts[$table] = $result;
            $this->logTableExportResult($table, $result);
        }

        return $table_counts;
    }

    private function logTableExportResult(string $table, array $result): void {
        if ($result['success']) {
            $this->log(LogLevelType::Debug->value, 'Exported table', array(
                'table' => $table,
                'rows' => $result['rows'],
                'bytes' => PathHelper::formatBytes($result['bytes']),
            ));
        } else {
            $this->log(LogLevelType::Error->value, 'Failed to export table', array(
                'table' => $table,
                'error' => $result['error'],
            ));
        }
    }

    private function buildExportResult(int $snapshotId, string $filepath, array $tables, array $tableCounts, float $startTime): array {
        $total_rows = 0;
        $total_bytes = 0;
        foreach ($tableCounts as $table => $result) {
            $total_rows += $result['rows'];
            $total_bytes += $result['bytes'];
        }

        $duration = microtime(true) - $startTime;
        $this->log(LogLevelType::Info->value, 'Snapshot complete', array(
            'id' => $snapshotId,
            'tables' => count($tables),
            'rows' => $total_rows,
            'bytes' => PathHelper::formatBytes($total_bytes),
            'duration' => round($duration, 2) . 's',
        ));

        $this->updateSnapshotStatus(
            $snapshotId,
            SnapshotStatusType::Complete->value,
            sprintf('Exported %d tables (%s)', count($tables), PathHelper::formatBytes($total_bytes))
        );

        return array(
            'success' => true,
            'tables' => count($tables),
            'rows' => $total_rows,
            'bytes' => $total_bytes,
            'filepath' => $filepath,
        );
    }
}
