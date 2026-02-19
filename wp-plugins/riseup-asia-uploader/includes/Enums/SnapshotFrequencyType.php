<?php
/**
 * SnapshotFrequencyType — Snapshot schedule frequency values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot schedule frequency values.
 */
enum SnapshotFrequencyType: string
{
    case Manual  = 'manual';
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isManual(): bool  { return $this->isEqual(self::Manual); }
    public function isDaily(): bool   { return $this->isEqual(self::Daily); }
    public function isWeekly(): bool  { return $this->isEqual(self::Weekly); }
    public function isMonthly(): bool { return $this->isEqual(self::Monthly); }
}
