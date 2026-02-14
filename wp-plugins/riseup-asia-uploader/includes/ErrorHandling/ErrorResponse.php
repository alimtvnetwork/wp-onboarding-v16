<?php
/**
 * ErrorResponse — Consolidates catch-block logging and standardized error returns.
 *
 * @package RiseupAsiaUploader
 * @since   1.60.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

class ErrorResponse {

    /** Log exception and return standardized error array. */
    public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {
        $logger->logException($e, $context);

        return array(
            'success' => false,
            'error'   => $e->getMessage(),
        );
    }

    /** Log with manual backtrace (skipping wrapper frames) and return error array. */
    public static function logAndReturnWithTrace(RiseupFileLogger $logger, Throwable $e, string $context = '', int $skipFrames = 1): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $frame = $trace[$skipFrames] ?? $trace[0];
        $file = $frame['file'] ?? $e->getFile();
        $line = $frame['line'] ?? $e->getLine();
        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();

        $logger->logAt(LogLevelType::Error->value, $message, $file, $line);

        return array(
            'success' => false,
            'error'   => $e->getMessage(),
        );
    }
}
