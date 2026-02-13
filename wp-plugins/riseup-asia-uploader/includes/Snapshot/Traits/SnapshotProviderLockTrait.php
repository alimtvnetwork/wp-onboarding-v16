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

trait SnapshotProviderLockTrait {

    /**
     * Check if a snapshot operation is currently in progress.
     *
     * @return bool True if locked.
     */
    protected function isLocked() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');

        if (!RiseupPathUtils::file_exists($lock_file)) {
            return false;
        }

        return $this->isLockFresh($lock_file);
    }

    /**
     * Check if lock file is still fresh (not stale).
     *
     * @param string $lock_file Lock file path.
     * @return bool True if lock is fresh.
     */
    private function isLockFresh(string $lock_file): bool {
        $lock_time = filemtime($lock_file);
        $age = time() - $lock_time;

        if ($age > 1800) {
            RiseupPathUtils::delete_file($lock_file);
            $this->log(LOG_LEVEL_WARN, 'Removed stale lock file', array('age_minutes' => round($age / 60)));
            return false;
        }

        return true;
    }

    /**
     * Acquire a lock for snapshot operations.
     *
     * @return bool True if lock acquired.
     */
    protected function acquireLock() {
        if ($this->isLocked()) {
            return false;
        }

        if (!$this->ensureSnapshotsDir()) {
            $this->log(LOG_LEVEL_ERROR, 'Cannot acquire lock - directory creation failed');
            return false;
        }

        return $this->writeLockFile();
    }

    /**
     * Write the lock file to disk.
     *
     * @return bool True on success.
     */
    private function writeLockFile(): bool {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');
        $lock_data = json_encode(array(
            'locked_at' => date('c'), 'locked_by' => $this->provider_id, 'pid' => getmypid(),
        ));

        $result = @file_put_contents($lock_file, $lock_data);
        if ($result === false) {
            $error = error_get_last();
            $this->log(LOG_LEVEL_ERROR, 'Failed to acquire lock', array(
                'path' => $lock_file, 'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        $this->log(LOG_LEVEL_DEBUG, 'Lock acquired', array('path' => $lock_file));
        return true;
    }

    /**
     * Release the snapshot lock.
     */
    protected function releaseLock() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');

        if (RiseupPathUtils::file_exists($lock_file)) {
            RiseupPathUtils::delete_file($lock_file);
            $this->log(LOG_LEVEL_DEBUG, 'Lock released');
        }
    }
}
