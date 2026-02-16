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
use RiseupAsia\Helpers\PathUtils;

trait SnapshotProviderLockTrait {

    protected function isLocked(): bool {
        $lock_file = PathUtils::join($this->getSnapshotsDir(), '.lock');

        if (!PathUtils::fileExists($lock_file)) {
            return false;
        }

        return $this->isLockFresh($lock_file);
    }

    private function isLockFresh(string $lock_file): bool {
        $lock_time = filemtime($lock_file);
        $age = time() - $lock_time;

        if ($age > 1800) {
            PathUtils::deleteFile($lock_file);
            $this->log(LogLevelType::Warn->value, 'Removed stale lock file', array('age_minutes' => round($age / 60)));
            return false;
        }

        return true;
    }

    protected function acquireLock(): bool {
        if ($this->isLocked()) {
            return false;
        }

        if (!$this->ensureSnapshotsDir()) {
            $this->log(LogLevelType::Error->value, 'Cannot acquire lock - directory creation failed');
            return false;
        }

        return $this->writeLockFile();
    }

    private function writeLockFile(): bool {
        $lock_file = PathUtils::join($this->getSnapshotsDir(), '.lock');
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

    protected function releaseLock(): void {
        $lock_file = PathUtils::join($this->getSnapshotsDir(), '.lock');

        if (PathUtils::fileExists($lock_file)) {
            PathUtils::deleteFile($lock_file);
            $this->log(LogLevelType::Debug->value, 'Lock released');
        }
    }
}
