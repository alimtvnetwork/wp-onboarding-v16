<?php
/**
 * LogRetrievalTrait — Returns log file contents for remote retrieval.
 *
 * Returns a flat JSON response (NOT envelope-wrapped) to match the Go backend's
 * direct unmarshalling into LogsRetrievePhpResponse.
 *
 * @package QUpload\Traits\Log
 * @since   2.18.0
 */

namespace QUpload\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\EnvelopeBuilder;
use QUpload\Helpers\PathHelper;

trait LogRetrievalTrait
{
    /** Handle GET /logs/retrieve — return log file contents. */
    public function handleLogsRetrieve(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('Logs retrieve endpoint called', ['endpoint' => 'logs/retrieve']);

        $settings = $this->resolveRetrievalSettings($request);
        $logsDir  = PathHelper::getLogsDir();

        $result = array(
            ResponseKeyType::Success->value     => true,
            ResponseKeyType::Version->value     => PluginConfigType::Version->value,
            ResponseKeyType::RequestedAt->value  => gmdate('Y-m-d\TH:i:s.v\Z'),
            ResponseKeyType::Settings->value     => $settings,
        );

        $isInfoLogRequested = $settings['include_info_log'];

        if ($isInfoLogRequested) {
            $result[ResponseKeyType::InfoLog->value] = $this->readLogFileTail(
                $logsDir . '/log.txt',
                $settings['max_lines'],
            );
        }

        $isErrorLogRequested = $settings['include_error_log'];

        if ($isErrorLogRequested) {
            $result[ResponseKeyType::ErrorLog->value] = $this->readLogFileTail(
                $logsDir . '/error.txt',
                $settings['max_lines'],
            );
        }

        $isStacktraceRequested = $settings['include_stacktrace'];

        if ($isStacktraceRequested) {
            $result[ResponseKeyType::StacktraceLog->value] = $this->readLogFileTail(
                $logsDir . '/stacktrace.txt',
                $settings['max_lines'],
            );
        }

        return EnvelopeBuilder::success('Log files retrieved', HttpStatusType::Ok->value)
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/logs/retrieve')
            ->setSingleResult($result)
            ->toResponse();
    }

    /** Resolve retrieval settings from query params with defaults. */
    private function resolveRetrievalSettings(WP_REST_Request $request): array
    {
        $resolved = array(
            'include_info_log'   => true,
            'include_error_log'  => true,
            'include_stacktrace' => true,
            'max_lines'          => 200,
        );

        foreach (array('include_info_log', 'include_error_log', 'include_stacktrace') as $key) {
            $paramValue = $request->get_param($key);
            $isParamPresent = ($paramValue !== null);

            if ($isParamPresent) {
                $resolved[$key] = filter_var($paramValue, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $maxLinesParam = $request->get_param('max_lines');
        $isMaxLinesPresent = ($maxLinesParam !== null);

        if ($isMaxLinesPresent) {
            $resolved['max_lines'] = max(10, min(5000, (int) $maxLinesParam));
        }

        return $resolved;
    }

    /** Read the last N lines of a log file and return metadata + content. */
    private function readLogFileTail(string $filePath, int $maxLines): array
    {
        $result = array(
            ResponseKeyType::Exists->value    => false,
            'File'                             => basename($filePath),
            ResponseKeyType::Path->value      => $filePath,
            ResponseKeyType::Content->value   => '',
            ResponseKeyType::Lines->value     => 0,
            ResponseKeyType::TotalLines->value => 0,
            ResponseKeyType::TotalSize->value  => 0,
            ResponseKeyType::Truncated->value  => false,
        );

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
