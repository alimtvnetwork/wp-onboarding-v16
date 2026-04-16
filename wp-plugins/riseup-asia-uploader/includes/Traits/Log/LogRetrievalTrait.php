<?php
/**
 * LogRetrievalTrait — Returns log file contents for remote retrieval.
 *
 * Returns a flat JSON response (NOT envelope-wrapped) to match the Go backend's
 * direct unmarshalling into LogsRetrievePhpResponse.
 *
 * @package RiseupAsia\Traits\Log
 * @since   2.18.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;

trait LogRetrievalTrait
{
    /** Handle GET /logs/retrieve — return log file contents. */
    public function handleLogsRetrieve(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Logs retrieve endpoint called', ['endpoint' => 'logs/retrieve']);

            $settings = $this->resolveRetrievalSettings($request);
            $logsDir  = $this->fileLogger->getLogsDir();

            $result = [
                ResponseKeyType::Success->value     => true,
                ResponseKeyType::Version->value     => PluginConfigType::Version->value,
                'RequestedAt'                        => gmdate('Y-m-d\TH:i:s.v\Z'),
                ResponseKeyType::Settings->value     => $settings,
            ];

            $isInfoLogRequested = $settings[ResponseKeyType::IncludeInfoLog->value];

            if ($isInfoLogRequested) {
                $result['InfoLog'] = $this->readLogFileTail(
                    $logsDir . '/log.txt',
                    $settings[ResponseKeyType::MaxLines->value],
                );
            }

            $isErrorLogRequested = $settings[ResponseKeyType::IncludeErrorLog->value];

            if ($isErrorLogRequested) {
                $result[ResponseKeyType::ErrorLog->value] = $this->readLogFileTail(
                    $logsDir . '/error.txt',
                    $settings[ResponseKeyType::MaxLines->value],
                );
            }

            $isStacktraceRequested = $settings[ResponseKeyType::IncludeStacktrace->value];

            if ($isStacktraceRequested) {
                $result[ResponseKeyType::StacktraceLog->value] = $this->readLogFileTail(
                    $logsDir . '/stacktrace.txt',
                    $settings[ResponseKeyType::MaxLines->value],
                );
            }

            // Flat response — NOT envelope-wrapped (Go backend unmarshals directly)
            return new WP_REST_Response($result, HttpStatusType::Ok->value);
        }, 'logs-retrieve');
    }

    /** Resolve retrieval settings from query params with defaults. */
    private function resolveRetrievalSettings(WP_REST_Request $request): array
    {
        $includeInfoLogKey   = ResponseKeyType::IncludeInfoLog->value;
        $includeErrorLogKey  = ResponseKeyType::IncludeErrorLog->value;
        $includeStacktraceKey = ResponseKeyType::IncludeStacktrace->value;
        $maxLinesKey         = ResponseKeyType::MaxLines->value;

        $resolved = [
            $includeInfoLogKey    => true,
            $includeErrorLogKey   => true,
            $includeStacktraceKey => true,
            $maxLinesKey          => 200,
        ];

        foreach ([$includeInfoLogKey, $includeErrorLogKey, $includeStacktraceKey] as $key) {
            $paramValue = $request->get_param($key);
            $isParamPresent = ($paramValue !== null);

            if ($isParamPresent) {
                $resolved[$key] = filter_var($paramValue, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $maxLinesParam = $request->get_param($maxLinesKey);
        $isMaxLinesPresent = ($maxLinesParam !== null);

        if ($isMaxLinesPresent) {
            $resolved[$maxLinesKey] = max(10, min(5000, (int) $maxLinesParam));
        }

        return $resolved;
    }

    /** Read the last N lines of a log file and return metadata + content. */
    private function readLogFileTail(string $filePath, int $maxLines): array
    {
        $result = [
            ResponseKeyType::Exists->value    => false,
            ResponseKeyType::File->value      => basename($filePath),
            ResponseKeyType::Path->value      => $filePath,
            ResponseKeyType::Content->value   => '',
            ResponseKeyType::Lines->value     => 0,
            ResponseKeyType::TotalLines->value => 0,
            ResponseKeyType::TotalSize->value  => 0,
            ResponseKeyType::Truncated->value  => false,
        ];

        $isFileUnreadable = !is_readable($filePath);

        if ($isFileUnreadable) {
            return $result;
        }

        $result[ResponseKeyType::Exists->value] = true;

        $fileSize = @filesize($filePath);
        $result[ResponseKeyType::TotalSize->value] = ($fileSize !== false) ? $fileSize : 0;

        $isFileTooLarge = ($fileSize !== false && $fileSize > 52428800);

        if ($isFileTooLarge) {
            $result[ResponseKeyType::Content->value] = '[File too large to read: ' . round($fileSize / 1048576, 2) . ' MB]';

            return $result;
        }

        $allLines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($allLines === false) {
            $result[ResponseKeyType::Content->value] = 'Failed to read file';

            return $result;
        }

        $totalLines = count($allLines);
        $isTruncated = ($totalLines > $maxLines);
        $lines = $isTruncated ? array_slice($allLines, -$maxLines) : $allLines;

        $result[ResponseKeyType::Lines->value]      = count($lines);
        $result[ResponseKeyType::TotalLines->value]  = $totalLines;
        $result[ResponseKeyType::Content->value]     = implode("\n", $lines);
        $result[ResponseKeyType::Truncated->value]   = $isTruncated;

        return $result;
    }
}
