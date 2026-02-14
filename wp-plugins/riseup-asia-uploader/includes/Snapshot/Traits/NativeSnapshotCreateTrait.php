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

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;

require_once dirname(__FILE__) . '/NativeSnapshotExecTrait.php';

trait NativeSnapshotCreateTrait {

    use NativeSnapshotExecTrait;

    /**
     * Create a snapshot (schedules via cron).
     */
    public function createSnapshot($options) {
        $this->log(LogLevelType::Info->value, 'Snapshot creation requested', $options);

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

    /** Guard conditions for snapshot creation. */
    private function guardCreateSnapshot(): ?array {
        if (!$this->ensureSnapshotsDir()) {
            return array('success' => false, 'error' => 'Failed to create snapshots directory');
        }
        if ($this->isLocked()) {
            $this->log(LogLevelType::Warn->value, 'Snapshot already in progress (locked)');
            return array('success' => false, 'error' => 'Another snapshot operation is in progress', 'code' => SnapshotErrorType::LockExists->value);
        }
        return null;
    }

    /** Resolve tables from options scope. */
    private function resolveSnapshotTables(array $options): array {
        $scope = $options['scope'] ?? SnapshotScopeType::WordPress->value;
        return $this->getTablesForScope($scope, $options['tables'] ?? array());
    }

    /** Create snapshot record and return its ID. */
    private function initSnapshotRecord(array $options, array $tables) {
        $sequence = $this->getNextSequence();
        $filename = $this->generateSnapshotFilename($sequence);
        $filepath = RiseupPathUtils::join($this->getSnapshotsDir(), $filename . '.sqlite');
        $trigger = $options['trigger'] ?? 'api';
        $scope = $options['scope'] ?? SnapshotScopeType::WordPress->value;
        return $this->createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger);
    }

    /** Schedule snapshot via cron or execute directly as fallback. */
    private function scheduleOrExecute(int $snapshot_id, array $tables, string $filename): array {
        $scheduled = wp_schedule_single_event(
            time() + 5,
            HookType::CronSnapshotImmediate->value,
            array(array('snapshot_id' => $snapshot_id, 'tables' => $tables))
        );

        if ($scheduled === false) {
            $this->log(LogLevelType::Warn->value, 'Cron scheduling failed, executing directly');
            return $this->executeSnapshot($snapshot_id, $tables);
        }

        $this->log(LogLevelType::Info->value, 'Snapshot scheduled via cron', array(
            'snapshot_id' => $snapshot_id, 'filename' => $filename, 'tables' => count($tables),
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshot_id,
            'filename' => $filename . '.sqlite', 'status' => SnapshotStatusType::Scheduled->value,
            'tables' => count($tables), 'scheduled_at' => date('c', time() + 5),
        );
    }
}
