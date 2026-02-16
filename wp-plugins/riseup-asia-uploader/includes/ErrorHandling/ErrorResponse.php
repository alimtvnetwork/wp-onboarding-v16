<?php
/**
 * ErrorResponse — Consolidates catch-block logging and standardized error returns.
 *
 * @package RiseupAsia\ErrorHandling
 * @since   1.60.0
 */

namespace RiseupAsia\ErrorHandling;

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Logging\FileLogger;

class ErrorResponse {

    /** Log exception and return standardized error array. */
    public static function logAndReturn(
        FileLogger $logger,
        Throwable $e,
        string $context = '',
    ): array {
        $logger->logException($e, $context);

        return array(
            'success' => false,
            'error'   => $e->getMessage(),
        );
    }

    /** Log with manual backtrace (skipping wrapper frames) and return error array. */
    public static function logAndReturnWithTrace(
        FileLogger $logger,
        Throwable $e,
        string $context = '',
        int $skipFrames = 1,
    ): array {
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

    /** Log exception and return a WP_REST_Response error envelope. */
    public static function logAndReturnEnvelope(
        FileLogger $logger,
        Throwable $e,
        string $context = '',
        int $status = 500,
    ): \WP_REST_Response {
        $logger->logException($e, $context);

        return new \WP_REST_Response(
            array(
                'success' => false,
                'error'   => $e->getMessage(),
            ),
            $status
        );
    }

    /** Log exception and return a WP_Error object. */
    public static function logAndReturnWpError(
        FileLogger $logger,
        Throwable $e,
        string $context = '',
        string $code = 'internal_error',
        int $status = 500,
    ): \WP_Error {
        $logger->logException($e, $context);

        return new \WP_Error(
            $code,
            $e->getMessage(),
            array('status' => $status)
        );
    }

    /** Log exception and return false. */
    public static function logAndReturnFalse(
        FileLogger $logger,
        Throwable $e,
        string $context = '',
    ): false {
        $logger->logException($e, $context);

        return false;
    }
}
