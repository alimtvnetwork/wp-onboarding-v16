<?php
/**
 * SnapshotStatusType — Snapshot lifecycle status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot lifecycle status values.
 */
enum SnapshotStatusType: string
{
    case Pending   = 'pending';
    case Scheduled = 'scheduled';
    case Running   = 'running';
    case Complete  = 'complete';
    case Failed    = 'failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isPending(): bool   { return $this->isEqual(self::Pending); }
    public function isScheduled(): bool { return $this->isEqual(self::Scheduled); }
    public function isRunning(): bool   { return $this->isEqual(self::Running); }
    public function isComplete(): bool  { return $this->isEqual(self::Complete); }
    public function isFailed(): bool    { return $this->isEqual(self::Failed); }

    public function isActive(): bool
    {
        return $this->isEqual(self::Pending)
            || $this->isEqual(self::Scheduled)
            || $this->isEqual(self::Running);
    }
}
