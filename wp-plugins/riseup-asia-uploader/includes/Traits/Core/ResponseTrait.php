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

trait ResponseTrait {

    /**
     * Safely execute a callable with comprehensive error handling.
     * Catches both Exception and Error (Throwable) for complete coverage.
     */
    private function safeExecute(callable $callback, string $context = 'operation', array $logContext = array()): WP_REST_Response {
        try {
            return call_user_func($callback);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, "Throwable in {$context}");

            $this->fileLogger->error("safeExecute caught Throwable", array_merge($logContext, array(
                'context'   => $context,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            )));

            return $this->errorResponse(
                "Error in {$context}: " . $e->getMessage(),
                HttpStatusType::ServerError->value,
                $e
            );
        }
    }

    /** Create an error response with optional exception details. */
    private function errorResponse(string $message, int $status, ?Throwable $exception = null): WP_REST_Response {
        $this->logErrorWithBacktrace($message, $status, $exception);

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return \RiseupEnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requestedAt)
            ->setDelegatedAt(home_url())
            ->toResponse();
    }

    /** Log an error with backtrace context. */
    private function logErrorWithBacktrace(string $message, int $status, ?Throwable $exception): void {
        if ($exception instanceof Throwable) {
            $this->fileLogger->logException($exception, $message);

            return;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
        $this->fileLogger->error('Error response', array(
            'message'    => $message,
            'status'     => $status,
            'stackTrace' => implode("\n", array_map(function($i, $f) {
                $file = isset($f['file']) ? basename($f['file']) : '[internal]';
                $line = isset($f['line']) ? $f['line'] : '?';
                $func = isset($f['function']) ? $f['function'] : '';
                $class = isset($f['class']) ? $f['class'] . $f['type'] : '';

                return "#{$i} {$file}({$line}): {$class}{$func}()";
            }, array_keys($backtrace), $backtrace)),
        ));
    }

    /** Get a meaningful error code from an exception. */
    private function getExceptionCode(Throwable $exception): string {
        $code = $exception->getCode();
        if (is_int($code) && $code > 0) {
            return 'E' . $code;
        }

        $class = get_class($exception);
        $short = str_replace(array('Exception', 'Error'), '', $class);
        if (empty($short)) {
            return 'EXCEPTION';
        }
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $short));
    }
}