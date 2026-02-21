<?php
/**
 * SnapshotJobStatusType — Snapshot worker job status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum SnapshotJobStatusType: string
{
    case Queued     = 'Queued';
    case Processing = 'Processing';
    case Complete   = 'Complete';
    case Failed     = 'Failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isQueued(): bool     { return $this->isEqual(self::Queued); }
    public function isProcessing(): bool { return $this->isEqual(self::Processing); }
    public function isComplete(): bool   { return $this->isEqual(self::Complete); }
    public function isFailed(): bool     { return $this->isEqual(self::Failed); }
}
