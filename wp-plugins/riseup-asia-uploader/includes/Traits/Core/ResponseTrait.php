<?php
/**
 * ResponseTrait — safe execution wrapper and error response helpers.
 *
 * Extracted from riseup-asia-uploader.php (lines 3607–4046).
 *
 * @package RiseupAsia\Traits\Core
 */

namespace RiseupAsia\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait ResponseTrait {

    /**
     * Safely execute a callable with comprehensive error handling.
     *
     * This is the **only** place where endpoint-level exceptions are caught.
     * Individual handler traits must NOT have their own try-catch blocks
     * around the entire handler — they delegate to safeExecute().
     *
     * Two-tier logging:
     *   Tier 1 — PHP error_log() (always available, even if FileLogger fails)
     *   Tier 2 — FileLogger::logException() (structured, file-based)
     *
     * @param callable $callback The business logic to execute.
     * @param string   $context  Human-readable label for log messages.
     */
    protected function safeExecute(
        callable $callback,
        string $context = 'operation',
    ): WP_REST_Response {
        try {
            return call_user_func($callback);
        } catch (Throwable $e) {
            // Tier 1 — PHP native error_log (guaranteed available)
            error_log(sprintf(
                '[RiseupAsia] safeExecute caught %s in %s: %s in %s:%d',
                get_class($e),
                $context,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));

            // Tier 2 — FileLogger (may be null during bootstrap failures)
            if ($this->fileLogger !== null) {
                $this->fileLogger->logException($e, "Throwable in {$context}");

                $this->fileLogger->error("safeExecute caught Throwable", [
                    'context'   => $context,
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }

            return $this->errorResponse(
                "Error in {$context}: " . $e->getMessage(),
                HttpStatusType::InternalServerError->value,
                $e,
            );
        }
    }

    /** Create an error response with optional exception details. */
    protected function errorResponse(
        string $message,
        int $status,
        ?Throwable $exception = null,
    ): WP_REST_Response {
        $this->logErrorWithBacktrace($message, $status, $exception);

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return EnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requestedAt)
            ->setDelegatedAt(home_url())
            ->toResponse();
    }

    /** Log an error with backtrace context. */
    private function logErrorWithBacktrace(
        string $message,
        int $status,
        ?Throwable $exception,
    ): void {
        if ($this->fileLogger === null) {
            error_log(sprintf('[RiseupAsia] errorResponse: %s (HTTP %d)', $message, $status));

            return;
        }

        if ($exception instanceof Throwable) {
            $this->fileLogger->logException($exception, $message);

            return;
        }

        $maxDepth = $this->fileLogger->getStackTraceDepth();
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $maxDepth > 0 ? $maxDepth : 15);
        $this->fileLogger->error('Error response', [
            'message'    => $message,
            'status'     => $status,
            'stackTrace' => implode("\n", array_map(function($i, $f) {
                $file = isset($f['file']) ? basename($f['file']) : '[internal]';
                $line = isset($f['line']) ? $f['line'] : '?';
                $func = isset($f['function']) ? $f['function'] : '';
                $class = isset($f['class']) ? $f['class'] . $f['type'] : '';

                return "#{$i} {$file}({$line}): {$class}{$func}()";
            }, array_keys($backtrace), $backtrace)),
        ]);
    }

    /** Get a meaningful error code from an exception. */
    private function getExceptionCode(Throwable $exception): string {
        $code = $exception->getCode();
        if (gettype($code) === PhpNativeType::PhpInteger->value && $code > 0) {
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