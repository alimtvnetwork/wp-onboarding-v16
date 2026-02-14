<?php
/**
 * SnapshotJobStatusType — Snapshot worker job status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot worker job status values.
 */
enum SnapshotJobStatusType: string
{
    case Queued     = 'queued';
    case Processing = 'processing';
    case Complete   = 'complete';
    case Failed     = 'failed';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isQueued(): bool     { return $this->isEqual(self::Queued); }
    public function isProcessing(): bool { return $this->isEqual(self::Processing); }
    public function isComplete(): bool   { return $this->isEqual(self::Complete); }
    public function isFailed(): bool     { return $this->isEqual(self::Failed); }
}
