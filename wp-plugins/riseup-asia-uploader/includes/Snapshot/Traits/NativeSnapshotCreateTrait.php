<?php
/**
 * NativeSnapshotCreateTrait — snapshot creation, guard checks, and scheduling.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\BooleanHelpers;

trait NativeSnapshotCreateTrait {

    use NativeSnapshotExecTrait;

    /**
     * Creates a new snapshot.
     *
     * @param string $scope  The scope of the snapshot (full, database, plugins).
     * @param string $trigger The trigger for the snapshot (manual, scheduled).
     *
     * @return array The result of the snapshot creation.
     */
    public function createSnapshot(string $scope, string $trigger): array {
        $this->log(LogLevelType::Info->value, 'Snapshot creation requested', array('scope' => $scope, 'trigger' => $trigger));

        $isScopeInvalid = BooleanHelpers::isAbsentFromList($scope, array(SnapshotScopeType::Full->value, SnapshotScopeType::Database->value, SnapshotScopeType::Plugins->value));
        if ($isScopeInvalid) {
            return $this->error(SnapshotErrorType::InvalidScope, 'Invalid snapshot scope: ' . $scope);
        }

        $isTriggerInvalid = BooleanHelpers::isAbsentFromList($trigger, array('manual', 'scheduled', 'api'));
        if ($isTriggerInvalid) {
            return $this->error(SnapshotErrorType::InvalidTrigger, 'Invalid snapshot trigger: ' . $trigger);
        }

        $isProviderUnavailable = ($this->isAvailable() === false);
        if ($isProviderUnavailable) {
            return $this->error(SnapshotErrorType::ProviderMissing, 'Native SQLite provider is not available');
        }

        if ($this->isSnapshotRunning()) {
            return $this->error(SnapshotErrorType::AlreadyRunning, 'A snapshot is already running');
        }

        return $this->scheduleOrExecute($scope, $trigger);
    }

    /**
     * Schedules or executes the snapshot creation based on the scope.
     *
     * @param string $scope   The scope of the snapshot (full, database, plugins).
     * @param string $trigger The trigger for the snapshot (manual, scheduled).
     *
     * @return array The result of the snapshot creation.
     */
    private function scheduleOrExecute(string $scope, string $trigger): array {
        if ($scope === SnapshotScopeType::Full->value) {
            return $this->error(SnapshotErrorType::InvalidScope, 'Full snapshot scope is not supported for native provider');
        }

        return $this->executeSnapshot($scope, $trigger);
    }

    /**
     * Checks if a snapshot is currently running.
     *
     * @return bool True if a snapshot is running, false otherwise.
     */
    private function isSnapshotRunning(): bool {
        $lock = $this->getLock();

        return $lock->isLocked();
    }

    /**
     * Schedules a cron job to delete old snapshots.
     *
     * @return void
     */
    private function scheduleSnapshotCleanup(): void {
        if (BooleanHelpers::isWpScheduleMissing(HookType::CleanupSnapshots->value)) {
            wp_schedule_event(time(), 'daily', HookType::CleanupSnapshots->value);
        }
    }
}
