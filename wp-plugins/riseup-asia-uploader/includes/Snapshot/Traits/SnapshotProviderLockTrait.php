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
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;

trait SnapshotProviderLockTrait {
    protected function isLocked(): bool {
        $lockFile = PathHelper::join($this->getSnapshotsDir(), '.lock');

        if (PathHelper::isFileMissing($lockFile)) {
            return false;
        }

        return $this->isLockFresh($lockFile);
    }

    private function isLockFresh(string $lockFile): bool {
        $lockTime = filemtime($lockFile);
        $age = time() - $lockTime;

        $isStaleByAge = ($age > SnapshotConfigType::LockTimeoutSeconds->value);

        if ($isStaleByAge) {
            PathHelper::deleteFile($lockFile);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file (age exceeded)', array('ageMinutes' => round($age / 60)));

            return false;
        }

        $isStaleByPid = $this->isLockPidDead($lockFile);

        if ($isStaleByPid) {
            PathHelper::deleteFile($lockFile);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file (PID dead)', array('ageMinutes' => round($age / 60)));

            return false;
        }

        return true;
    }

    /**
     * Check whether the process that created the lock file is still alive.
     */
    private function isLockPidDead(string $lockFile): bool {
        $contents = @file_get_contents($lockFile);
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
        $lockFile = PathHelper::join($this->getSnapshotsDir(), '.lock');
        $lockData = json_encode(array(
            'lockedAt' => DateHelper::nowIso(),
            'lockedBy' => $this->providerId,
            'pid' => getmypid(),
        ));

        $result = @file_put_contents($lockFile, $lockData);
        if ($result === false) {
            $error = error_get_last();
            $this->log(LogLevelType::Error->value, 'Failed to acquire lock', array(
                'path' => $lockFile, 'error' => $error ? $error['message'] : 'Unknown error',
            ));

            return false;
        }

        $this->log(LogLevelType::Debug->value, 'Lock acquired', array('path' => $lockFile));

        return true;
    }

    protected function releaseLock(): void {
        $lockFile = PathHelper::join($this->getSnapshotsDir(), '.lock');

        if (PathHelper::fileExists($lockFile)) {
            PathHelper::deleteFile($lockFile);
            $this->log(LogLevelType::Debug->value, 'Lock released');
        }
    }
}
