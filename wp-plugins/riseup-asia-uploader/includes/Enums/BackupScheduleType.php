<?php
/**
 * BackupScheduleType — Cloud storage backup schedule frequency identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.16.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum BackupScheduleType: string
{
    case Hourly   = 'Hourly';
    case Daily    = 'Daily';
    case Weekly   = 'Weekly';
    case Biweekly = 'Biweekly';
    case Monthly  = 'Monthly';
    case Manual   = 'Manual';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isManual(): bool    { return $this->isEqual(self::Manual); }
    public function isAutomatic(): bool { return !$this->isManual(); }

    /** WP-Cron recurrence name for this schedule. */
    public function recurrence(): string
    {
        return match($this) {
            self::Hourly   => 'hourly',
            self::Daily    => 'daily',
            self::Weekly   => 'weekly',
            self::Biweekly => 'riseup_biweekly',
            self::Monthly  => 'riseup_monthly',
            self::Manual   => '',
        };
    }

    /** Interval in seconds. */
    public function intervalSeconds(): int
    {
        return match($this) {
            self::Hourly   => HOUR_IN_SECONDS,
            self::Daily    => DAY_IN_SECONDS,
            self::Weekly   => WEEK_IN_SECONDS,
            self::Biweekly => 2 * WEEK_IN_SECONDS,
            self::Monthly  => 30 * DAY_IN_SECONDS,
            self::Manual   => 0,
        };
    }

    /** Display label for UI. */
    public function label(): string
    {
        return match($this) {
            self::Hourly   => 'Hourly',
            self::Daily    => 'Daily',
            self::Weekly   => 'Weekly',
            self::Biweekly => 'Bi-weekly',
            self::Monthly  => 'Monthly',
            self::Manual   => 'Manual only',
        };
    }
}
