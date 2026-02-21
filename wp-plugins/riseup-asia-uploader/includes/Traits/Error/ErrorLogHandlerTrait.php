<?php
/**
 * ErrorLogHandlerTrait — error log retrieval and log tail reading.
 *
 * @package RiseupAsia\Traits\Error
 */

namespace RiseupAsia\Traits\Error;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Admin\Admin;

trait ErrorLogHandlerTrait {

    /** Handle error-logs endpoint. */
    public function handleErrorLogs(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Error logs endpoint called');
            $settings = $this->resolveLogSettings($request);
            $result = array('version' => PluginConfigType::Version->value, 'settings' => $settings);

            if ($settings['include_error_log']) {
                $result[ResponseKeyType::ErrorLog->value] = $this->readLogTail($this->fileLogger->getErrorFile(), $settings['max_lines']);
            }

            if ($settings['include_full_log']) {
                $result[ResponseKeyType::FullLog->value] = $this->readLogTail($this->fileLogger->getLogFile(), $settings['max_lines']);
            }

            if ($settings['include_stacktrace']) {
                $result[ResponseKeyType::StacktraceLog->value] = $this->readLogTail($this->fileLogger->getStacktraceFile(), $settings['max_lines']);
            }

            return EnvelopeBuilder::success()->autoDetectRequestedAt()->setSingleResult($result)->toResponse();
        }, 'error_logs');
    }

    /** Resolve log retrieval settings from admin defaults and query param overrides. */
    private function resolveLogSettings(WP_REST_Request $request): array {
        $settings     = Admin::get_settings();
        $logSettings = isset($settings['log_retrieval']) ? $settings['log_retrieval'] : array();

        $resolved = array(
            'include_error_log'  => isset($logSettings['include_error_log']) ? (bool) $logSettings['include_error_log'] : true,
            'include_full_log'   => isset($logSettings['include_full_log']) ? (bool) $logSettings['include_full_log'] : false,
            'include_stacktrace' => isset($logSettings['include_stacktrace']) ? (bool) $logSettings['include_stacktrace'] : true,
            'max_lines'          => isset($logSettings['max_lines']) ? (int) $logSettings['max_lines'] : PaginationConfigType::LogRetrievalMaxLines->value,
        );

        foreach (array('include_error_log', 'include_full_log', 'include_stacktrace') as $key) {
            if ($request->get_param($key) !== null) {
                $resolved[$key] = (bool) $request->get_param($key);
            }
        }

        if ($request->get_param('max_lines') !== null) {
            $resolved['max_lines'] = max(10, min(5000, (int) $request->get_param('max_lines')));
        }

        return $resolved;
    }

    /** Read the last N lines of a log file. */
    private function readLogTail(string $filePath, int $maxLines): array {
        $result = array(
            ResponseKeyType::Exists->value => false, 'file' => basename($filePath), ResponseKeyType::Path->value => $filePath,
            ResponseKeyType::Content->value => '', ResponseKeyType::Lines->value => 0, 'total_size' => 0, ResponseKeyType::Truncated->value => false,
        );

        $isFileUnreadable = PathHelper::isFileUnreadable($filePath);
        if ($isFileUnreadable) {
            return $result;
        }

        $result[ResponseKeyType::Exists->value]     = true;
        $result['total_size'] = filesize($filePath);

        $allLines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($allLines === false) {
            $result[ResponseKeyType::Content->value] = 'Failed to read file';

            return $result;
        }

        $totalLines = count($allLines);
        $result[ResponseKeyType::Truncated->value] = ($totalLines > $maxLines);
        $lines = ($totalLines > $maxLines) ? array_slice($allLines, -$maxLines) : $allLines;

        $result[ResponseKeyType::Lines->value]      = count($lines);
        $result[ResponseKeyType::TotalLines->value]  = $totalLines;
        $result[ResponseKeyType::Content->value]     = implode("\n", $lines);

        return $result;
    }
}
