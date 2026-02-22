<?php
/**
 * SnapshotFrequencyType — Snapshot schedule frequency values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotFrequencyType: string
{
    case Manual  = 'Manual';
    case Hourly  = 'Hourly';
    case Daily   = 'Daily';
    case Weekly  = 'Weekly';
    case Monthly = 'Monthly';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isManual(): bool  { return $this->isEqual(self::Manual); }
    public function isHourly(): bool  { return $this->isEqual(self::Hourly); }
    public function isDaily(): bool   { return $this->isEqual(self::Daily); }
    public function isWeekly(): bool  { return $this->isEqual(self::Weekly); }
    public function isMonthly(): bool { return $this->isEqual(self::Monthly); }
}
