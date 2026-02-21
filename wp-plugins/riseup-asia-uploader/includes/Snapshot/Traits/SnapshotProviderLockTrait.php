<?php
/**
 * SnapshotProviderLockTrait — Lock management for snapshot providers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Helpers\PathHelper;

trait SnapshotProviderLockTrait {
    protected function isLocked(): bool {
        $lock_file = PathHelper::join($this->getSnapshotsDir(), '.lock');

        if (PathHelper::isFileMissing($lock_file)) {
            return false;
        }

        return $this->isLockFresh($lock_file);
    }

    private function isLockFresh(string $lock_file): bool {
        $lock_time = filemtime($lock_file);
        $age = time() - $lock_time;

        $isStaleByAge = ($age > SnapshotConfigType::LockTimeoutSeconds->value);
        if ($isStaleByAge) {
            PathHelper::deleteFile($lock_file);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file (age exceeded)', array('age_minutes' => round($age / 60)));

            return false;
        }

        $isStaleByPid = $this->isLockPidDead($lock_file);
        if ($isStaleByPid) {
            PathHelper::deleteFile($lock_file);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file (PID dead)', array('age_minutes' => round($age / 60)));

            return false;
        }

        return true;
    }

    /**
     * Check whether the process that created the lock file is still alive.
     */
    private function isLockPidDead(string $lock_file): bool {
        $contents = @file_get_contents($lock_file);
        if ($contents === false) {
            return false;
        }

        $data = json_decode($contents, true);
        $pid = $data['pid'] ?? null;
        $isPidMissing = ($pid === null);
        if ($isPidMissing) {
            return false;
        }

        // posix_kill with signal 0 checks process existence without sending a signal
        if (function_exists('posix_kill')) {
            $isProcessAlive = posix_kill((int) $pid, 0);

            return ($isProcessAlive === false);
        }

        return false;
    }

    protected function acquireLock(): bool {
        if ($this->isLocked()) {
            return false;
        }

        $isDirEnsureFailed = ($this->ensureSnapshotsDir() === false);
        if ($isDirEnsureFailed) {
            $this->log(LogLevelType::Error->value, 'Cannot acquire lock - directory creation failed');

            return false;
        }

        return $this->writeLockFile();
    }

    private function writeLockFile(): bool {
        $lock_file = PathHelper::join($this->getSnapshotsDir(), '.lock');
        $lock_data = json_encode(array(
            'locked_at' => date('c'),
            'locked_by' => $this->provider_id,
            'pid' => getmypid(),
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

    protected function releaseLock(): void {
        $lock_file = PathHelper::join($this->getSnapshotsDir(), '.lock');

        if (PathHelper::fileExists($lock_file)) {
            PathHelper::deleteFile($lock_file);
            $this->log(LogLevelType::Debug->value, 'Lock released');
        }
    }
}
