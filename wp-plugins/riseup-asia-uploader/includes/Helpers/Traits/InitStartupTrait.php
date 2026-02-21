<?php
/**
 * InitStartupTrait — Component startup tracking and diagnostics.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\ErrorHandling\BootErrorCollector;
use RiseupAsia\Logging\FileLogger;

trait InitStartupTrait {

    public static function initComponent(string $name, callable $initFn): mixed {
        $start = microtime(true);
        $result = null;
        $error = null;

        try {
            $result = $initFn();
        } catch (Throwable $e) {
            $error = $e->getMessage();
            BootErrorCollector::getInstance()->addError('component_init:' . $name, $error);
        }

        $elapsed_ms = round((microtime(true) - $start) * 1000, 2);

        self::$startup_results[] = array(
            'name' => $name, ResponseKeyType::Success->value => $error === null,
            ResponseKeyType::Error->value => $error, 'time_ms' => $elapsed_ms,
        );

        return $result;
    }

    public static function getStartupResults(): array { return self::$startup_results; }

    public static function getFailedStartups(): array {
        return array_filter(self::$startup_results, function (array $r): bool { $isStartupFailed = ($r[ResponseKeyType::Success->value] === false); return $isStartupFailed; });
    }

    public static function allStartupsSucceeded(): bool { return empty(self::getFailedStartups()); }

    public static function getTotalStartupTime(): float {
        $total = 0;
        foreach (self::$startup_results as $r) { $total += $r['time_ms']; }

        return round($total, 2);
    }

    public static function logStartupSummary(FileLogger $logger): void {
        $total = count(self::$startup_results);
        $failed = count(self::getFailedStartups());
        $time = self::getTotalStartupTime();

        if ($failed > 0) {
            $logger->warn('[INIT] Startup complete with failures', array(
                'total' => $total, 'failed' => $failed, 'time_ms' => $time,
                'failures' => array_map(function (array $r): string { return $r['name'] . ': ' . $r[ResponseKeyType::Error->value]; }, self::getFailedStartups()),
            ));

            return;
        }

        $logger->info('[INIT] All components started successfully', array('total' => $total, 'time_ms' => $time));
    }
}
