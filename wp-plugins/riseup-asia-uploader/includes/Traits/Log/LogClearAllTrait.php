<?php
/**
 * LogClearAllTrait — Single-call clearing of logs for both plugins (Riseup Asia + QUpload).
 *
 * Clears file logs for both plugins and database tables for Riseup Asia.
 * QUpload logs are cleared directly via its FileLogger if available on the same site.
 *
 * @package RiseupAsia\Traits\Log
 * @since   2.30.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Logging\FileLogger;

trait LogClearAllTrait
{
    /**
     * Handle DELETE /logs/clear-all — clear logs for both Riseup Asia and QUpload plugins.
     *
     * Requires machine validation (reused from LogClearingTrait).
     */
    public function handleLogsClearAll(WP_REST_Request $request): WP_REST_Response {
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $machineError = $this->validateMachineHeader($machineName);

        if ($machineError !== null) {
            return $machineError;
        }

        $riseupResult = $this->clearRiseupLogs();
        $quploadResult = $this->clearQUploadLogs();
        $clientIp = $this->resolveClientIp();

        $this->fileLogger->info('Clear-all executed for both plugins', array(
            'machine' => $machineName,
            'ip'      => $clientIp,
            'riseup'  => $riseupResult,
            'qupload' => $quploadResult,
        ));

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'riseup'                        => $riseupResult,
                'qupload'                       => $quploadResult,
                'cleared_by'                    => array(
                    'machine'   => $machineName,
                    'ip'        => $clientIp,
                    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                ),
            ),
            HttpStatusType::Ok->value,
        );
    }

    /**
     * Clear Riseup Asia logs (files + database).
     *
     * @return array{cleared: bool, files: bool, database: bool, error: string}
     */
    private function clearRiseupLogs(): array {
        $result = array('cleared' => false, 'files' => false, 'database' => false, 'error' => '');

        try {
            $logger = FileLogger::getInstance();
            $logger->clearAllLogFiles();
            $result['files'] = true;

            $dbResult = $this->executeDatabaseClearing();
            $result['database'] = ($dbResult['activity_log'] ?? false) || ($dbResult['error_sessions'] ?? false);
            $result['cleared'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Clear QUpload logs by directly calling its FileLogger if the class exists.
     *
     * @return array{cleared: bool, error: string}
     */
    private function clearQUploadLogs(): array {
        $result = array('cleared' => false, 'error' => '');

        $quploadLoggerClass = 'QUpload\\Logging\\FileLogger';
        $isQUploadAvailable = class_exists($quploadLoggerClass);

        if ($isQUploadAvailable === false) {
            $result['error'] = 'QUpload plugin not active or not installed';

            return $result;
        }

        try {
            $quploadLogger = $quploadLoggerClass::getInstance();
            $quploadLogger->clearAllLogFiles();
            $result['cleared'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
