<?php
/**
 * ResponseTrait — safe execution wrapper and error response helpers for QUpload.
 *
 * @package QUpload\Traits\Core
 * @since   1.0.0
 */

namespace QUpload\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Helpers\EnvelopeBuilder;

trait ResponseTrait
{
    private const MAX_BACKTRACE_DEPTH = 15;

    /**
     * Safely execute a callable with comprehensive error handling.
     * Catches both Exception and Error (Throwable) for complete coverage.
     */
    private function safeExecute(
        callable $callback,
        string $context = 'operation',
        array $logContext = [],
    ): WP_REST_Response {
        try {
            return call_user_func($callback);
        } catch (Throwable $e) {
            // Emit to PHP error_log so it's visible in WP_DEBUG / php-error.log
            @error_log(sprintf(
                '[QUpload] %s: %s in %s:%d%s%s',
                $context,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString(),
            ));

            $this->fileLogger->logException($e, "Throwable in {$context}");

            $this->fileLogger->error("safeExecute caught Throwable", array_merge($logContext, [
                'context'   => $context,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]));

            return $this->errorResponse(
                "Error in {$context}: " . $e->getMessage(),
                HttpStatusType::ServerError->value,
                $e,
            );
        }
    }

    /** Create an error response with optional exception details. */
    private function errorResponse(
        string $message,
        int $status,
        ?Throwable $exception = null,
    ): WP_REST_Response {
        $this->logErrorWithBacktrace($message, $status, $exception);

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return EnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requestedAt)
            ->toResponse();
    }

    /** Log an error with backtrace context. */
    private function logErrorWithBacktrace(
        string $message,
        int $status,
        ?Throwable $exception,
    ): void {
        if ($exception instanceof Throwable) {
            // Emit to PHP error_log so it's visible in WP_DEBUG / php-error.log
            @error_log(sprintf(
                '[QUpload] %s (HTTP %d): %s%s%s',
                $message,
                $status,
                $exception->getMessage(),
                PHP_EOL,
                $exception->getTraceAsString(),
            ));

            $this->fileLogger->logException($exception, $message);

            return;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_BACKTRACE_DEPTH);
        $this->fileLogger->error('Error response', [
            'message'    => $message,
            'status'     => $status,
            'stackTrace' => implode("\n", array_map(function ($i, $f) {
                $file  = isset($f['file']) ? basename($f['file']) : '[internal]';
                $line  = isset($f['line']) ? $f['line'] : '?';
                $func  = isset($f['function']) ? $f['function'] : '';
                $class = isset($f['class']) ? $f['class'] . $f['type'] : '';

                return "#{$i} {$file}({$line}): {$class}{$func}()";
            }, array_keys($backtrace), $backtrace)),
        ]);
    }

    /** Get a meaningful error code from an exception. */
    private function getExceptionCode(Throwable $exception): string {
        $code = $exception->getCode();

        if (is_int($code) && $code > 0) {
            return 'E' . $code;
        }

        $class = get_class($exception);
        $short = str_replace(['Exception', 'Error'], '', $class);

        if (empty($short)) {
            return 'EXCEPTION';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $short));
    }
}
