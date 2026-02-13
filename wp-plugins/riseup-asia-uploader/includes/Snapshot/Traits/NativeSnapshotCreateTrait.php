<?php
/**
 * NativeSnapshotCreateTrait — snapshot creation, guard checks, and scheduling.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/NativeSnapshotExecTrait.php';

trait NativeSnapshotCreateTrait {

    use NativeSnapshotExecTrait;

    /**
     * Create a snapshot (schedules via cron).
     *
     * @param array $options Snapshot options.
     * @return array Snapshot result.
     */
    public function createSnapshot($options) {
        $this->log(LOG_LEVEL_INFO, 'Snapshot creation requested', $options);

        $guardError = $this->guardCreateSnapshot();
        if ($guardError) {
            return $guardError;
        }

        $tables = $this->resolveSnapshotTables($options);
        if (empty($tables)) {
            return array('success' => false, 'error' => 'No tables selected for snapshot');
        }

        $snapshot_id = $this->initSnapshotRecord($options, $tables);
        if (!$snapshot_id) {
            return array('success' => false, 'error' => 'Failed to create snapshot record');
        }

        $filename = $this->generateSnapshotFilename($this->getNextSequence() - 1);
        return $this->scheduleOrExecute($snapshot_id, $tables, $filename);
    }

    /**
     * Guard conditions for snapshot creation.
     *
     * @return array|null Failure result or null if clear.
     */
    private function guardCreateSnapshot(): ?array {
        if (!$this->ensureSnapshotsDir()) {
            return array('success' => false, 'error' => 'Failed to create snapshots directory');
        }
        if ($this->isLocked()) {
            $this->log(LOG_LEVEL_WARN, 'Snapshot already in progress (locked)');
            return array('success' => false, 'error' => 'Another snapshot operation is in progress', 'code' => ERR_SNAPSHOT_LOCK_EXISTS);
        }
        return null;
    }

    /**
     * Resolve tables from options scope.
     *
     * @param array $options Snapshot options.
     * @return array Table names.
     */
    private function resolveSnapshotTables(array $options): array {
        $scope = $options['scope'] ?? SNAPSHOT_SCOPE_WORDPRESS;
        return $this->getTablesForScope($scope, $options['tables'] ?? array());
    }

    /**
     * Create snapshot record and return its ID.
     *
     * @param array $options Snapshot options.
     * @param array $tables  Tables.
     * @return int|false Snapshot ID or false.
     */
    private function initSnapshotRecord(array $options, array $tables) {
        $sequence = $this->getNextSequence();
        $filename = $this->generateSnapshotFilename($sequence);
        $filepath = RiseupPathUtils::join($this->getSnapshotsDir(), $filename . '.sqlite');
        $trigger = $options['trigger'] ?? 'api';
        $scope = $options['scope'] ?? SNAPSHOT_SCOPE_WORDPRESS;
        return $this->createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger);
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
}
