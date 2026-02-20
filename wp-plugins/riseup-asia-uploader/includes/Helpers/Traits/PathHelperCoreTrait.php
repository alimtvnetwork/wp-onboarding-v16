<?php
/**
 * PathHelperCoreTrait — core path operations.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Logging\FileLogger;

trait PathHelperCoreTrait {

    private static ?FileLogger $logger = null;
    private static bool $isBootstrapping = false;

    private static function getLogger(): ?FileLogger {
        if (self::$isBootstrapping) { return null; }
        if (self::$logger !== null) { return self::$logger; }
        if (BooleanHelpers::isClassUnregistered(FileLogger::class)) { return null; }

        return self::initializeLogger();
    }

    private static function initializeLogger(): ?FileLogger {
        self::$isBootstrapping = true;
        try {
            self::$logger = FileLogger::getInstance();
        } catch (Throwable $e) {
            InitHelpers::errorLogWithPrefix('[ERROR] Logger init failed: ' . $e->getMessage());
            self::$logger = null;
        }
        self::$isBootstrapping = false;

        return self::$logger;
    }

    private static function safeLog(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $upper = strtoupper($level);
        $method = strtolower($level);

        if (self::$isBootstrapping) {
            InitHelpers::errorLogWithPrefix('[' . $upper . '] ' . $message);

            return;
        }

        $logger = self::getLogger();
        if ($logger !== null) {
            $logger->$method($message, $context);
        } else {
            InitHelpers::errorLogWithPrefix('[' . $upper . '] ' . $message);
        }
    }

    public static function join(string ...$segments): string {
        $filtered = array_filter($segments, function($seg) { return $seg !== null && $seg !== ''; });
        if (empty($filtered)) { return ''; }
        $path = implode('/', $filtered);
        $path = str_replace('\\\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#^([a-zA-Z]):#', '$1:', $path);

        return $path;
    }

    public static function getBaseDir(): string {
        $uploadDir = wp_upload_dir();

        return self::join($uploadDir['basedir'], PluginConfigType::UploadsSubdir->value);
    }

    public static function getLogsDir(): string { return self::join(self::getBaseDir(), PathSubdirType::Logs->value); }
    public static function getSnapshotsDir(): string { return self::join(self::getBaseDir(), PathSubdirType::Snapshots->value); }
    public static function getTempDir(): string { return self::join(self::getBaseDir(), PathSubdirType::Temp->value); }
    public static function getDbPath(): string { return self::join(self::getBaseDir(), PathDatabaseType::Plugin->value); }
}
