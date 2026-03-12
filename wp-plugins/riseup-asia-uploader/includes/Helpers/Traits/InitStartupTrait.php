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
            InitHelpers::errorLogAndThrow($e, 'InitStartupTrait::trackInit(' . $name . ') failed:');
        }

        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        self::$startupResults[] = array(
            'name'                          => $name,
            ResponseKeyType::Success->value => $error === null,
            ResponseKeyType::Error->value   => $error,
            'timeMs'                        => $elapsedMs,
        );

        return $result;
    }

    public static function getStartupResults(): array { return self::$startupResults; }

    public static function getFailedStartups(): array {
        return array_filter(self::$startupResults, function (array $r): bool { $isStartupFailed = ($r[ResponseKeyType::Success->value] === false); return $isStartupFailed; });
    }

    public static function allStartupsSucceeded(): bool { return empty(self::getFailedStartups()); }

    public static function getTotalStartupTime(): float {
        $total = 0;
        foreach (self::$startupResults as $r) { $total += $r['timeMs']; }

        return round($total, 2);
    }

    public static function logStartupSummary(FileLogger $logger): void {
        $total = count(self::$startupResults);
        $failed = count(self::getFailedStartups());
        $time = self::getTotalStartupTime();

        if ($failed > 0) {
            $logger->warn('[INIT] Startup complete with failures', array(
                'total'    => $total,
                'failed'   => $failed,
                'timeMs'   => $time,
                'failures' => array_map(function (array $r): string {
                    return $r['name'] . ': ' . $r[ResponseKeyType::Error->value];
                }, self::getFailedStartups()),
            ));

            return;
        }

        $logger->info('[INIT] All components started successfully', array('total' => $total, 'timeMs' => $time));
    }
}
