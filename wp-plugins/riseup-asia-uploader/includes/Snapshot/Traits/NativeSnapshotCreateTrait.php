<?php
/**
 * NativeSnapshotCreateTrait — snapshot creation, scheduling, and execution.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait NativeSnapshotCreateTrait {

    /**
     * Create a snapshot (schedules via cron).
     *
     * @param array $options Snapshot options.
     * @return array Snapshot result.
     */
    public function createSnapshot($options) {
        $this->log(LOG_LEVEL_INFO, 'Snapshot creation requested', $options);

        if (!$this->ensureSnapshotsDir()) {
            return array('success' => false, 'error' => 'Failed to create snapshots directory');
        }

        if ($this->isLocked()) {
            $this->log(LOG_LEVEL_WARN, 'Snapshot already in progress (locked)');
            return array('success' => false, 'error' => 'Another snapshot operation is in progress', 'code' => ERR_SNAPSHOT_LOCK_EXISTS);
        }

        $scope = isset($options['scope']) ? $options['scope'] : SNAPSHOT_SCOPE_WORDPRESS;
        $tables = $this->getTablesForScope($scope, isset($options['tables']) ? $options['tables'] : array());

        if (empty($tables)) {
            return array('success' => false, 'error' => 'No tables selected for snapshot');
        }

        $sequence = $this->getNextSequence();
        $filename = $this->generateSnapshotFilename($sequence);
        $filepath = RiseupPathUtils::join($this->getSnapshotsDir(), $filename . '.sqlite');

        $trigger = isset($options['trigger']) ? $options['trigger'] : 'api';
        $snapshot_id = $this->createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger);

        if (!$snapshot_id) {
            return array('success' => false, 'error' => 'Failed to create snapshot record');
        }

        return $this->scheduleOrExecute($snapshot_id, $tables, $filename);
    }

    /**
     * Schedule snapshot via cron or execute directly as fallback.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param array  $tables      Tables to export.
     * @param string $filename    Snapshot filename.
     * @return array Result.
     */
    private function scheduleOrExecute(int $snapshot_id, array $tables, string $filename): array {
        $scheduled = wp_schedule_single_event(
            time() + 5,
            CRON_SNAPSHOT_IMMEDIATE,
            array(array('snapshot_id' => $snapshot_id, 'tables' => $tables))
        );

        if ($scheduled === false) {
            $this->log(LOG_LEVEL_WARN, 'Cron scheduling failed, executing directly');
            return $this->executeSnapshot($snapshot_id, $tables);
        }

        $this->log(LOG_LEVEL_INFO, 'Snapshot scheduled via cron', array(
            'snapshot_id' => $snapshot_id, 'filename' => $filename, 'tables' => count($tables),
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshot_id,
            'filename' => $filename . '.sqlite', 'status' => SNAPSHOT_STATUS_SCHEDULED,
            'tables' => count($tables), 'scheduled_at' => date('c', time() + 5),
        );
    }

    /**
     * Execute the actual snapshot export (called by cron).
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $tables      Tables to export.
     * @return array Result.
     */
    public function executeSnapshot($snapshot_id, $tables) {
        $start_time = microtime(true);

        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot record not found');
        }

        if (!$this->acquireLock()) {
            $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_FAILED, 'Failed to acquire lock');
            return array('success' => false, 'error' => 'Failed to acquire lock');
        }

        try {
            return $this->runSnapshotExport($snapshot_id, $snapshot['filepath'], $tables, $start_time);
        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'Snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_FAILED, $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Run the core snapshot export loop.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param string $filepath    Output file path.
     * @param array  $tables      Tables to export.
     * @param float  $start_time  Start timestamp.
     * @return array Result.
     */
    private function runSnapshotExport(int $snapshot_id, string $filepath, array $tables, float $start_time): array {
        $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_RUNNING);
        $this->log(LOG_LEVEL_INFO, 'Starting snapshot export', array(
            'snapshot_id' => $snapshot_id, 'filepath' => $filepath, 'tables' => count($tables),
        ));

        $sqlite = $this->createSqliteDatabase($filepath);
        if (!$sqlite) {
            throw new Exception('Failed to create SQLite database');
        }

        $total_rows = 0;
        $table_counts = array();

        foreach ($tables as $table) {
            $this->log(LOG_LEVEL_DEBUG, 'Exporting table: ' . $table);
            $result = $this->exportTable($sqlite, $table, $snapshot_id);

            if ($result['success']) {
                $total_rows += $result['rows'];
                $table_counts[$table] = $result['rows'];
                $this->log(LOG_LEVEL_INFO, sprintf('%s complete (%d rows, %s)', $table, $result['rows'], $this->formatBytes($result['bytes'])));
            } else {
                $this->log(LOG_LEVEL_ERROR, 'Failed to export table: ' . $table, array('error' => $result['error']));
            }
        }

        $sqlite = null;
        $file_size = filesize($filepath);
        $duration = microtime(true) - $start_time;

        $this->finalizeSnapshot($snapshot_id, array(
            'status' => SNAPSHOT_STATUS_COMPLETE, 'file_size' => $file_size,
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
