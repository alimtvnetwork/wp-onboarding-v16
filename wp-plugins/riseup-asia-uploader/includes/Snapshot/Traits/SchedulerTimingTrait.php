<?php
/**
 * Scheduler Timing Trait
 *
 * Time calculation helpers for cron schedule planning.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SchedulerTimingTrait {

    /**
     * Calculate next run timestamp based on settings.
     *
     * @param string $frequency Frequency type.
     * @param string $time      Time in HH:MM format.
     * @param int    $day       Day of week (1-7) or month (1-28).
     * @return int Unix timestamp.
     */
    private function calculateNextRunTime($frequency, $time, $day) {
        list($hour, $minute) = explode(':', $time);
        $hour = intval($hour);
        $minute = intval($minute);

        switch ($frequency) {
            case SNAPSHOT_FREQ_DAILY:   return $this->nextDailyRun($hour, $minute);
            case SNAPSHOT_FREQ_WEEKLY:  return $this->nextWeeklyRun($hour, $minute, $day);
            case SNAPSHOT_FREQ_MONTHLY: return $this->nextMonthlyRun($hour, $minute, $day);
            default:                    return strtotime("tomorrow {$hour}:{$minute}:00");
        }
    }

    /**
     * Calculate next daily run timestamp.
     *
     * @param int $hour   Hour (0-23).
     * @param int $minute Minute (0-59).
     * @return int Unix timestamp.
     */
    private function nextDailyRun(int $hour, int $minute): int {
        $now = current_time('timestamp');
        $next = strtotime("today {$hour}:{$minute}:00");
        return ($next <= $now) ? strtotime("tomorrow {$hour}:{$minute}:00") : $next;
    }

    /**
     * Calculate next weekly run timestamp.
     *
     * @param int $hour   Hour (0-23).
     * @param int $minute Minute (0-59).
     * @param int $day    ISO day number (1=Monday, 7=Sunday).
     * @return int Unix timestamp.
     */
    private function nextWeeklyRun(int $hour, int $minute, int $day): int {
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

    /**
     * Calculate next monthly run timestamp.
     *
     * @param int $hour   Hour (0-23).
     * @param int $minute Minute (0-59).
     * @param int $day    Day of month (1-28).
     * @return int Unix timestamp.
     */
    private function nextMonthlyRun(int $hour, int $minute, int $day): int {
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

    /**
     * Get day name from ISO day number.
     *
     * @param int $day Day number (1-7, Monday-Sunday).
     * @return string Day name.
     */
    private function getDayName($day) {
        $days = array(
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        );
        return isset($days[$day]) ? $days[$day] : 'Monday';
    }

    /**
     * Map frequency constant to WP cron recurrence.
     *
     * @param string $frequency Frequency constant.
     * @return string WP cron recurrence name.
     */
    private function mapFrequencyToRecurrence($frequency) {
        switch ($frequency) {
            case SNAPSHOT_FREQ_DAILY:   return 'daily';
            case SNAPSHOT_FREQ_WEEKLY:  return SNAPSHOT_FREQ_WEEKLY;
            case SNAPSHOT_FREQ_MONTHLY: return SNAPSHOT_FREQ_MONTHLY;
            default:                    return 'daily';
        }
    }
}
