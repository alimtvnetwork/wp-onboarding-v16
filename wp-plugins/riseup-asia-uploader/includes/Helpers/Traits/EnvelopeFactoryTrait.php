<?php
/**
 * EnvelopeFactoryTrait — static factory methods and error block construction.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait EnvelopeFactoryTrait {

    /**
     * Create a success envelope.
     *
     * @param string $message Optional success message.
     * @param int    $code    HTTP status code (default 200).
     * @return static
     */
    public static function success($message = 'OK', $code = 200) {
        $builder = new self();
        $builder->is_success = true;
        $builder->code = $code;
        $builder->message = $message;
        return $builder;
    }

    /**
     * Create an error envelope.
     *
     * @param string         $message   Error message.
     * @param int            $code      HTTP status code (default 500).
     * @param Throwable|null $exception Optional exception for stack trace extraction.
     * @return static
     */
    public static function error($message, $code = 500, $exception = null) {
        $builder = new self();
        $builder->is_success = false;
        $builder->code = $code;
        $builder->message = $message;
        $builder->has_errors = true;

        $errors = array(
            'BackendMessage'              => $message,
            'DelegatedServiceErrorStack'  => array(),
            'Backend'                     => array(),
            'Frontend'                    => array(),
        );

        if ($exception instanceof Throwable) {
            $errors = self::buildExceptionErrors($errors, $exception);
        } else {
            $errors = self::buildBacktraceErrors($errors);
        }

        $builder->errors = $errors;
        return $builder;
    }

    /**
     * Build error details from an exception.
     */
    private static function buildExceptionErrors(array $errors, Throwable $exception): array {
        $trace_lines = explode("\n", $exception->getTraceAsString());
        $errors['DelegatedServiceErrorStack'] = $trace_lines;

        if (function_exists('riseup_exception_to_frames')) {
            $frames = riseup_exception_to_frames($exception);
            $errors['Backend'] = self::framesToLines($frames);
        }

        return $errors;
    }

    /**
     * Build error details from a debug backtrace.
     */
    private static function buildBacktraceErrors(array $errors): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
        if (function_exists('riseup_backtrace_to_frames')) {
            $frames = riseup_backtrace_to_frames($backtrace);
            $errors['Backend'] = self::framesToLines($frames);
        }

        return $errors;
    }

    /**
     * Convert structured frames to readable lines.
     */
    private static function framesToLines(array $frames): array {
        $lines = array();
        foreach ($frames as $frame) {
            $file = isset($frame['fileBase']) ? $frame['fileBase'] : '';
            $line = isset($frame['line']) ? $frame['line'] : 0;
            $fn = isset($frame['function']) ? $frame['function'] : '';
            $class = isset($frame['class']) ? $frame['class'] . '::' : '';
            $lines[] = "{$file}:{$line} {$class}{$fn}";
        }
        return $lines;
    }
}
