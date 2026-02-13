<?php
/**
 * SnapshotProviderLockTrait — Lock management for snapshot providers.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait SnapshotProviderLockTrait {

    /** Check if a snapshot operation is currently in progress. */
    protected function isLocked() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');

        if (!RiseupPathUtils::file_exists($lock_file)) {
            return false;
        }

        return $this->isLockFresh($lock_file);
    }

    /** Check if lock file is still fresh (not stale). */
    private function isLockFresh(string $lock_file): bool {
        $lock_time = filemtime($lock_file);
        $age = time() - $lock_time;

        if ($age > 1800) {
            RiseupPathUtils::delete_file($lock_file);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file', array('age_minutes' => round($age / 60)));
            return false;
        }

        return true;
    }

    /** Acquire a lock for snapshot operations. */
    protected function acquireLock() {
        if ($this->isLocked()) {
            return false;
        }

        if (!$this->ensureSnapshotsDir()) {
            $this->log(LogLevelType::Error->value, 'Cannot acquire lock - directory creation failed');
            return false;
        }

        return $this->writeLockFile();
    }

    /** Write the lock file to disk. */
    private function writeLockFile(): bool {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');
        $lock_data = json_encode(array(
            'locked_at' => date('c'), 'locked_by' => $this->provider_id, 'pid' => getmypid(),
        ));

        $result = @file_put_contents($lock_file, $lock_data);
        if ($result === false) {
            $error = error_get_last();
            $this->log(LogLevelType::Error->value, 'Failed to acquire lock', array(
                'path' => $lock_file, 'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        $this->log(LogLevelType::Debug->value, 'Lock acquired', array('path' => $lock_file));
        return true;
    }

    /** Release the snapshot lock. */
    protected function releaseLock() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');

        if (RiseupPathUtils::file_exists($lock_file)) {
            RiseupPathUtils::delete_file($lock_file);
            $this->log(LogLevelType::Debug->value, 'Lock released');
        }
    }
}
