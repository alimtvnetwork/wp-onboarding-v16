<?php
/**
 * Handles plugin activation: directory creation, log initialization, and security file placement.
 *
 * @package RiseupAsia\Activation
 * @since   1.57.0
 */

namespace RiseupAsia\Activation;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathLogFileType;
use RiseupAsia\Enums\PluginConfigType;
use Throwable;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\InitHelpers;

class ActivationHandler
{
    private const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s';
    private const VERSION_UNKNOWN = 'unknown';

    public static function activate(): void {
        try {
            self::loadDependencies();
            $dirs = self::resolveDirs();

            if ($dirs === null) {
                return;
            }

            self::ensureDirs($dirs['base'], $dirs['logs']);
            self::writeLogFiles($dirs['logs']);
            self::ensureSecurity($dirs['base']);
        } catch (Throwable $e) {
            error_log('[Riseup Asia] Activation hook failed: ' . $e->getMessage());
        }
    }

    private static function loadDependencies(): void {
        $pluginDir = dirname(__DIR__, 2);

        $constantsFile = $pluginDir . '/includes/constants.php';
        if (file_exists($constantsFile)) {
            require_once $constantsFile;
        }

        $helpersFile = $pluginDir . '/includes/Helpers/BooleanHelpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }
    }

    /**
     * @return array{base: string, logs: string}|null
     */
    private static function resolveDirs(): ?array {
        $uploadDir = wp_upload_dir();
        $hasError = isset($uploadDir['error']) && $uploadDir['error'];

        if ($hasError) {
            return null;
        }

        return array(
            'base' => $uploadDir['basedir'] . '/' . PluginConfigType::UploadsSubdir->value,
            'logs' => $uploadDir['basedir'] . '/' . PluginConfigType::UploadsSubdir->value . PathSubdirType::Logs->value,
        );
    }

    private static function ensureDirs(string $baseDir, string $logsDir): void {
        if (PathHelper::isDirMissing($baseDir)) {
            wp_mkdir_p($baseDir);
        }
        if (PathHelper::isDirMissing($logsDir)) {
            wp_mkdir_p($logsDir);
        }
    }

    private static function writeLogFiles(string $logsDir): void {
        $timestamp = gmdate(self::TIMESTAMP_FORMAT) . 'Z';
        $version = PluginConfigType::Version->value;

        self::writeMainLog($logsDir, $timestamp, $version);
        self::writeErrorLog($logsDir, $timestamp, $version);
        self::writeStacktraceLog($logsDir, $timestamp);
    }

    private static function writeMainLog(
        string $logsDir,
        string $timestamp,
        string $version,
    ): void {
        $logFile = $logsDir . PathLogFileType::Log->value;
        @file_put_contents($logFile, sprintf(
            "[%s] [INFO] Plugin activated (activation hook) (riseup-asia-uploader.php:0) {\"version\":\"%s\",\"php\":\"%s\",\"wp\":\"%s\"}\n",
            $timestamp, $version, phpversion(), get_bloginfo('version')
        ), FILE_APPEND | LOCK_EX);
    }

    private static function writeErrorLog(
        string $logsDir,
        string $timestamp,
        string $version,
    ): void {
        $errorFile = $logsDir . PathLogFileType::Error->value;
        @file_put_contents($errorFile, sprintf(
            "[%s] [INFO] Plugin activated — error log initialized (v%s)\n",
            $timestamp, $version
        ), FILE_APPEND | LOCK_EX);
    }

    private static function writeStacktraceLog(string $logsDir, string $timestamp): void {
        $stacktraceFile = $logsDir . PathLogFileType::Stacktrace->value;
        if (PathHelper::isFileMissing($stacktraceFile)) {
            @file_put_contents($stacktraceFile, sprintf(
                "# Riseup Asia Uploader - Stack Trace Log (initialized %s)\n\n",
                $timestamp
            ));
        }
    }

    private static function ensureSecurity(string $baseDir): void {
        if (class_exists(InitHelpers::class)) {
            InitHelpers::addSecurityFiles($baseDir);
            return;
        }

        $htaccess = $baseDir . '/.htaccess';
        if (PathHelper::isFileMissing($htaccess)) {
            @file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n");
        }

        $index = $baseDir . '/index.php';
        if (PathHelper::isFileMissing($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }
}
