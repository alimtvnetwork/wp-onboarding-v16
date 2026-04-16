<?php
/**
 * LogClearAllTrait — Single-call clearing of logs for both plugins (Riseup Asia + QUpload).
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
    /** Handle DELETE /logs/clear-all — clear logs for both Riseup Asia and QUpload plugins. */
    public function handleLogsClearAll(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $machineName = $request->get_header('X-Riseup-Source-Machine');
            $machineError = $this->validateMachineHeader($machineName);

            if ($machineError !== null) {
                return $machineError;
            }

            $riseupResult = $this->clearRiseupLogs();
            $quploadResult = $this->clearQUploadLogs();
            $clientIp = $this->resolveClientIp();

            $this->fileLogger->info('Clear-all executed for both plugins', [
                'machine' => $machineName,
                'ip'      => $clientIp,
                'riseup'  => $riseupResult,
                'qupload' => $quploadResult,
            ]);

            return new WP_REST_Response(
                [
                    ResponseKeyType::Success->value    => true,
                    ResponseKeyType::Riseup->value     => $riseupResult,
                    ResponseKeyType::Qupload->value    => $quploadResult,
                    ResponseKeyType::ClearedBy->value  => [
                        ResponseKeyType::Machine->value   => $machineName,
                        ResponseKeyType::Ip->value        => $clientIp,
                        ResponseKeyType::Timestamp->value => gmdate('Y-m-d\TH:i:s\Z'),
                    ],
                ],
                HttpStatusType::Ok->value,
            );
        }, 'logs-clear-all');
    }

    /**
     * Clear Riseup Asia logs (files + database).
     *
     * @return array{cleared: bool, files: bool, database: bool, error: string}
     */
    private function clearRiseupLogs(): array {
        $result = [
            ResponseKeyType::Cleared->value  => false,
            ResponseKeyType::Files->value    => false,
            ResponseKeyType::Database->value => false,
            ResponseKeyType::Error->value    => '',
        ];

        try {
            $logger = FileLogger::getInstance();
            $logger->clearAllLogFiles();
            $result[ResponseKeyType::Files->value] = true;

            $dbResult = $this->executeDatabaseClearing();
            $result[ResponseKeyType::Database->value] = ($dbResult[ResponseKeyType::ActivityLog->value] ?? false) || ($dbResult[ResponseKeyType::ErrorSessions->value] ?? false);
            $result[ResponseKeyType::Cleared->value] = true;
        } catch (\Throwable $e) {
            $result[ResponseKeyType::Error->value] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Clear QUpload logs by directly calling its FileLogger if the class exists.
     *
     * @return array{cleared: bool, error: string}
     */
    private function clearQUploadLogs(): array {
        $result = [
            ResponseKeyType::Cleared->value => false,
            ResponseKeyType::Error->value   => '',
        ];

        $quploadLoggerClass = 'QUpload\\Logging\\FileLogger';
        $isQUploadAvailable = class_exists($quploadLoggerClass);

        if ($isQUploadAvailable === false) {
            $result[ResponseKeyType::Error->value] = 'QUpload plugin not active or not installed';

            return $result;
        }

        try {
            $quploadLogger = $quploadLoggerClass::getInstance();
            $quploadLogger->clearAllLogFiles();
            $result[ResponseKeyType::Cleared->value] = true;
        } catch (\Throwable $e) {
            $result[ResponseKeyType::Error->value] = $e->getMessage();
        }

        return $result;
    }
}
