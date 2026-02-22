<?php
/**
 * SnapshotStatusType — Snapshot lifecycle status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotStatusType: string
{
    case Pending   = 'Pending';
    case Scheduled = 'Scheduled';
    case Running   = 'Running';
    case Complete  = 'Complete';
    case Failed    = 'Failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isPending(): bool   { return $this->isEqual(self::Pending); }
    public function isScheduled(): bool { return $this->isEqual(self::Scheduled); }
    public function isRunning(): bool   { return $this->isEqual(self::Running); }
    public function isComplete(): bool  { return $this->isEqual(self::Complete); }
    public function isFailed(): bool    { return $this->isEqual(self::Failed); }

    public function isActive(): bool
    {
        return $this->isAnyOf(self::Pending, self::Scheduled, self::Running);
    }
}
