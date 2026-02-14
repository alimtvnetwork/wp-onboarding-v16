<?php
/**
 * ResponseTrait — safe execution wrapper and error response helpers.
 *
 * Extracted from riseup-asia-uploader.php (lines 3607–4046).
 *
 * @package RiseupAsiaUploader
 */

trait ResponseTrait {

    /**
     * Safely execute a callable with comprehensive error handling.
     * Catches both Exception and Error (Throwable) for complete coverage.
     *
     * @param callable $callback   The function to execute.
     * @param string   $context    Description of the operation for error messages.
     * @param array    $logContext Additional context for logging.
     *
     * @return WP_REST_Response|mixed The result of the callback or an error response.
     */
    private function safeExecute($callback, $context = 'operation', $logContext = array()) {
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
                HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    /**
     * Create an error response with optional exception details.
     *
     * @param string         $message   Error message.
     * @param int            $status    HTTP status code.
     * @param Throwable|null $exception Optional exception for stack trace.
     * @return WP_REST_Response
     */
    private function errorResponse($message, $status, $exception = null) {
        $this->logErrorWithBacktrace($message, $status, $exception);

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return RiseupEnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requestedAt)
            ->setDelegatedAt(home_url())
            ->toResponse();
    }

    /**
     * Log an error with backtrace context.
     *
     * @param string         $message   Error message.
     * @param int            $status    HTTP status code.
     * @param Throwable|null $exception Optional exception.
     */
    private function logErrorWithBacktrace(string $message, int $status, $exception) {
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

    /**
     * Get a meaningful error code from an exception.
     *
     * @param Throwable $exception The exception.
     *
     * @return string
     */
    private function getExceptionCode($exception) {
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
