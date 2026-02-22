<?php
/**
 * Scheduler Timing Trait
 *
 * Time calculation helpers for cron schedule planning.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotFrequencyType;

trait SchedulerTimingTrait {
    private function calculateNextRunTime(
        string $frequency,
        string $time,
        int $day,
    ): int {
        list($hour, $minute) = explode(':', $time);
        $hour = intval($hour);
        $minute = intval($minute);

        switch ($frequency) {
            case SnapshotFrequencyType::Hourly->value:  return $this->nextHourlyRun();
            case SnapshotFrequencyType::Daily->value:   return $this->nextDailyRun($hour, $minute);
            case SnapshotFrequencyType::Weekly->value:  return $this->nextWeeklyRun($hour, $minute, $day);
            case SnapshotFrequencyType::Monthly->value: return $this->nextMonthlyRun($hour, $minute, $day);
            default:                                    return strtotime("tomorrow {$hour}:{$minute}:00");
        }
    }

    private function nextHourlyRun(): int {
        $now = current_time('timestamp');
        $next = strtotime('+1 hour', $now);

        return $next;
    }

    private function nextDailyRun(int $hour, int $minute): int {
        $now = current_time('timestamp');
        $next = strtotime("today {$hour}:{$minute}:00");

        return ($next <= $now) ? strtotime("tomorrow {$hour}:{$minute}:00") : $next;
    }

    private function nextWeeklyRun(
        int $hour,
        int $minute,
        int $day,
    ): int {
        $now = current_time('timestamp');
        $day_name = $this->getDayName($day);
        $next = strtotime("next {$day_name} {$hour}:{$minute}:00");

        if (date('N') == $day) {
            $today = strtotime("today {$hour}:{$minute}:00");

            if ($today > $now) {

                return $today;
            }
        }

        return $next;
    }

    private function nextMonthlyRun(
        int $hour,
        int $minute,
        int $day,
    ): int {
        $now = current_time('timestamp');
        $day = min(28, max(1, $day));
        $current_month = date('Y-m');
        $next = strtotime("{$current_month}-{$day} {$hour}:{$minute}:00");

        if ($next <= $now) {
            $next_month = date('Y-m', strtotime('+1 month'));
            $next = strtotime("{$next_month}-{$day} {$hour}:{$minute}:00");
        }

        return $next;
    }

    private function getDayName(int $day): string {
        $days = array(
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        );

        return isset($days[$day]) ? $days[$day] : 'Monday';
    }

    private function mapFrequencyToRecurrence(string $frequency): string {
        switch ($frequency) {
            case SnapshotFrequencyType::Hourly->value:  return SnapshotFrequencyType::Hourly->value;
            case SnapshotFrequencyType::Daily->value:   return 'daily';
            case SnapshotFrequencyType::Weekly->value:  return SnapshotFrequencyType::Weekly->value;
            case SnapshotFrequencyType::Monthly->value: return SnapshotFrequencyType::Monthly->value;
            default:                                    return 'daily';
        }
    }
}
