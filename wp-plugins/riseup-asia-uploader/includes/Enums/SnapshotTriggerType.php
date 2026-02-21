<?php
/**
 * SnapshotTriggerType — Snapshot trigger source values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot trigger source values.
 */
enum SnapshotTriggerType: string
{
    case Manual    = 'Manual';
    case Scheduled = 'Scheduled';
    case Cron      = 'Cron';
    case Api       = 'Api';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    public function isManual(): bool { return $this->isEqual(self::Manual); }
    public function isCron(): bool   { return $this->isEqual(self::Cron); }
    public function isApi(): bool    { return $this->isEqual(self::Api); }
}
