<?php
/**
 * Riseup Asia Uploader - Dependency Loader
 *
 * @package RiseupAsia\Helpers
 * @since   1.21.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Logging\FileLogger;

class DependencyLoader {

    private static array $results = array();

    public static function load(string $label, string $path): bool {
        if (BooleanHelpers::isFileMissing($path)) {
            self::recordResult($label, $path, false, 'File not found: ' . $path);
            return false;
        }

        try {
            require_once $path;
            self::recordResult($label, $path, true, null);
            return true;
        } catch (\Throwable $e) {
            self::recordResult($label, $path, false, $e->getMessage());
            return false;
        }
    }

    private static function recordResult(string $label, string $path, bool $success, ?string $error) {
        self::$results[] = array(
            'label'   => $label,
            'file'    => basename($path),
            'success' => $success,
            'error'   => $error,
        );
    }

    public static function loadManifest(array $manifest): int {
        $failures = 0;
        foreach ($manifest as $entry) {
            if (!self::load($entry[0], $entry[1])) {
                $failures++;
            }
        }
        return $failures;
    }

    public static function getResults(): array { return self::$results; }

    public static function getFailures(): array {
        return array_filter(self::$results, function (array $r): bool {
            return !$r['success'];
        });
    }

    public static function allLoaded(): bool { return empty(self::getFailures()); }

    public static function logSummary(FileLogger $logger): void {
        $total  = count(self::$results);
        $failed = count(self::getFailures());

        if ($failed > 0) {
            $logger->warn('[DEPS] Dependency loading complete with failures', array(
                'total' => $total, 'failed' => $failed,
                'failures' => array_map(function (array $r): string {
                    return $r['label'] . ' (' . $r['file'] . '): ' . $r['error'];
                }, self::getFailures()),
            ));
        } else {
            $logger->debug('[DEPS] All dependencies loaded', array('total' => $total));
        }
    }

    public static function reset(): void { self::$results = array(); }
}

class_alias(DependencyLoader::class, 'RiseupDependencyLoader');
