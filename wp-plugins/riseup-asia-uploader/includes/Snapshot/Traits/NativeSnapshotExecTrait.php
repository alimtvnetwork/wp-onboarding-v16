<?php
/**
 * NativeSnapshotExecTrait — snapshot execution, export loop, and result building.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotStatusType;

trait NativeSnapshotExecTrait {

    /**
     * Execute the actual snapshot export (called by cron).
     */
    public function executeSnapshot($snapshot_id, $tables) {
        $start_time = microtime(true);

        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot record not found');
        }

        if (!$this->acquireLock()) {
            $this->updateSnapshotStatus($snapshot_id, SnapshotStatusType::Failed->value, 'Failed to acquire lock');
            return array('success' => false, 'error' => 'Failed to acquire lock');
        }

        try {
            return $this->runSnapshotExport($snapshot_id, $snapshot['filepath'], $tables, $start_time);
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->updateSnapshotStatus($snapshot_id, SnapshotStatusType::Failed->value, $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        } finally {
            $this->releaseLock();
        }
    }

    /** Run the core snapshot export loop. */
    private function runSnapshotExport(int $snapshot_id, string $filepath, array $tables, float $start_time): array {
        $this->updateSnapshotStatus($snapshot_id, SnapshotStatusType::Running->value);
        $this->log(LogLevelType::Info->value, 'Starting snapshot export', array(
            'snapshot_id' => $snapshot_id, 'filepath' => $filepath, 'tables' => count($tables),
        ));

        $sqlite = $this->createSqliteDatabase($filepath);
        if (!$sqlite) {
            throw new Exception('Failed to create SQLite database');
        }

        $table_counts = $this->exportAllTables($sqlite, $tables, $snapshot_id);
        $sqlite = null;

        return $this->buildExportResult($snapshot_id, $filepath, $tables, $table_counts, $start_time);
    }

    /** Export all tables and return row counts map. */
    private function exportAllTables(PDO $sqlite, array $tables, int $snapshot_id): array {
        $table_counts = array();
        foreach ($tables as $table) {
            $this->log(LogLevelType::Debug->value, 'Exporting table: ' . $table);
            $result = $this->exportTable($sqlite, $table, $snapshot_id);
            $this->logTableExportResult($table, $result);

            if ($result['success']) {
                $table_counts[$table] = $result['rows'];
            }
        }

        return $table_counts;
    }

    /** Log export result for a single table. */
    private function logTableExportResult(string $table, array $result) {
        if ($result['success']) {
            $this->log(LogLevelType::Info->value, sprintf('%s complete (%d rows, %s)', $table, $result['rows'], $this->formatBytes($result['bytes'])));

            return;
        }

        $this->log(LogLevelType::Error->value, 'Failed to export table: ' . $table, array('error' => $result['error']));
    }

    /** Build final export result array and finalize snapshot record. */
    private function buildExportResult(int $snapshot_id, string $filepath, array $tables, array $table_counts, float $start_time): array {
        $total_rows = array_sum($table_counts);
        $file_size = filesize($filepath);
        $duration = microtime(true) - $start_time;

        $this->finalizeSnapshot($snapshot_id, array(
            'status' => SnapshotStatusType::Complete->value, 'file_size' => $file_size,
            'total_rows' => $total_rows, 'table_counts' => $table_counts,
            'duration_ms' => (int)($duration * 1000),
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshot_id, 'filename' => basename($filepath),
            'filepath' => $filepath, 'size' => $file_size, 'tables' => count($tables),
            'rows' => $total_rows, 'duration' => $duration,
        );
    }
}
