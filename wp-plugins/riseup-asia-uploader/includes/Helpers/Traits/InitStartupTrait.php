<?php
/**
 * InitStartupTrait — Component startup tracking and diagnostics.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait InitStartupTrait {

    /**
     * Execute a component initialization with timing and error tracking.
     *
     * @param string   $name    Component name.
     * @param callable $init_fn Initialization callable.
     * @return mixed The return value of $init_fn, or null on failure.
     */
    public static function initComponent(string $name, callable $initFn): mixed {
        $start = microtime(true);
        $result = null;
        $error = null;

        try {
            $result = $initFn();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $elapsed_ms = round((microtime(true) - $start) * 1000, 2);

        self::$startup_results[] = array(
            'name'    => $name,
            'success' => $error === null,
            'error'   => $error,
            'time_ms' => $elapsed_ms,
        );

        return $result;
    }

    /**
     * Get all component startup results.
     *
     * @return array List of startup result records.
     */
    public static function getStartupResults(): array {
        return self::$startup_results;
    }

    /**
     * Get failed component startups only.
     *
     * @return array List of startup records where success === false.
     */
    public static function getFailedStartups(): array {
        return array_filter(self::$startup_results, function (array $r): bool {
            return !$r['success'];
        });
    }

    /**
     * Check if all components started successfully.
     *
     * @return bool True if no failures.
     */
    public static function allStartupsSucceeded(): bool {
        return empty(self::getFailedStartups());
    }

    /**
     * Get total startup time in milliseconds.
     *
     * @return float Total milliseconds.
     */
    public static function getTotalStartupTime(): float {
        $total = 0;
        foreach (self::$startup_results as $r) {
            $total += $r['time_ms'];
        }
        return round($total, 2);
    }

    /**
     * Log a summary of all startup results to the provided logger.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @return void
     */
    public static function logStartupSummary(RiseupFileLogger $logger): void {
        $total = count(self::$startup_results);
        $failed = count(self::getFailedStartups());
        $time = self::getTotalStartupTime();

        if ($failed > 0) {
            $logger->warn('[INIT] Startup complete with failures', array(
                'total'      => $total,
                'failed'     => $failed,
                'time_ms'    => $time,
                'failures'   => array_map(function (array $r): string {
                    return $r['name'] . ': ' . $r['error'];
                }, self::getFailedStartups()),
            ));
            return;
        }

        $logger->info('[INIT] All components started successfully', array(
            'total'   => $total,
            'time_ms' => $time,
        ));
    }
}
