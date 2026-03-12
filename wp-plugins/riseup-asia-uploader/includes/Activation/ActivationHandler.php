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

use Throwable;

use RiseupAsia\Enums\PathLogFileType;
use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Helpers\PathHelper;

class ActivationHandler
{
    private const DIAGNOSTICS_TRANSIENT = 'riseup_boot_diagnostics';
    private const DIAGNOSTICS_EXPIRY = DAY_IN_SECONDS;

    public static function activate(): void {
        InitHelpers::errorLogWithPrefix('ActivationHandler::activate() — starting activation');
        try {
            $dirs = self::resolveDirs();

            if ($dirs === null) {
                return;
            }

            self::ensureDirs($dirs['base'], $dirs['logs']);
            InitHelpers::errorLogWithPrefix('ActivationHandler::activate() — directories ensured');
            self::writeLogFiles($dirs['logs']);
            self::ensureSecurity($dirs['base']);
            self::runBootDiagnostics();
            InitHelpers::errorLogWithPrefix('ActivationHandler::activate() — activation complete');
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, PluginConfigType::LogPrefix->value . ' Activation hook failed:');
        }
    }

    /**
     * Run autoloader diagnostics and store results in a transient.
     * Uses native error_log() since FileLogger may not be available.
     */
    private static function runBootDiagnostics(): void {
        $isAutoloaderMissing = !class_exists('RiseupAsiaAutoloader', false);

        if ($isAutoloaderMissing) {
            InitHelpers::errorLogWithPrefix('ActivationHandler: autoloader class not available for diagnostics');

            return;
        }

        $result = \RiseupAsiaAutoloader::runDiagnostics();
        $hasFailures = (count($result['failed']) > 0);

        $diagnosticData = [
            'timestamp'    => DateHelper::nowUtc(),
            'loaded_count' => count($result['loaded']),
            'failed_count' => count($result['failed']),
            'failures'     => $result['failed'],
            'runtime_failures' => \RiseupAsiaAutoloader::getFailedClasses(),
        ];

        set_transient(self::DIAGNOSTICS_TRANSIENT, $diagnosticData, self::DIAGNOSTICS_EXPIRY);

        if ($hasFailures) {
            InitHelpers::errorLogWithPrefix('ActivationHandler: boot diagnostics found ' . count($result['failed']) . ' file(s) with errors');
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
            'base' => $uploadDir['basedir'] . '/' . PluginConfigType::uploadsSubdir(),
            'logs' => $uploadDir['basedir'] . '/' . PluginConfigType::uploadsSubdir() . PathSubdirType::Logs->value,
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
        $timestamp = DateHelper::nowUtc();
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
        $isWriteFailed = (file_put_contents($logFile, sprintf(
            "[%s] [INFO] Plugin activated (activation hook) (riseup-asia-uploader.php:0) {\"version\":\"%s\",\"php\":\"%s\",\"wp\":\"%s\"}\n",
            $timestamp, $version, phpversion(), get_bloginfo('version')
        ), FILE_APPEND | LOCK_EX) === false);

        if ($isWriteFailed) {
            InitHelpers::errorLogWithPrefix('Failed to write main log file: ' . $logFile);
        }
    }

    private static function writeErrorLog(
        string $logsDir,
        string $timestamp,
        string $version,
    ): void {
        $errorFile = $logsDir . PathLogFileType::Error->value;
        $isWriteFailed = (file_put_contents($errorFile, sprintf(
            "[%s] [INFO] Plugin activated — error log initialized (v%s)\n",
            $timestamp, $version
        ), FILE_APPEND | LOCK_EX) === false);

        if ($isWriteFailed) {
            InitHelpers::errorLogWithPrefix('Failed to write error log file: ' . $errorFile);
        }
    }

    private static function writeStacktraceLog(string $logsDir, string $timestamp): void {
        $stacktraceFile = $logsDir . PathLogFileType::Stacktrace->value;

        if (PathHelper::isFileMissing($stacktraceFile)) {
            $isWriteFailed = (file_put_contents($stacktraceFile, sprintf(
                '# ' . PluginConfigType::Name->value . " - Stack Trace Log (initialized %s)\n\n",
                $timestamp
            )) === false);

            if ($isWriteFailed) {
                InitHelpers::errorLogWithPrefix('Failed to write stacktrace log file: ' . $stacktraceFile);
            }
        }
    }

    private static function ensureSecurity(string $baseDir): void {
        InitHelpers::addSecurityFiles($baseDir);
    }
}
