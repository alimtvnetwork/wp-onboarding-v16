<?php
/**
 * FrameBuilder — Stack trace frame construction utilities.
 *
 * Standalone functions for converting exceptions and backtraces
 * into structured frame arrays for error diagnostics.
 *
 * @package RiseupAsia\ErrorHandling
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build a single structured frame from a backtrace entry.
 *
 * @param array $frame Backtrace frame.
 * @return array Structured frame object.
 */
function riseup_build_single_frame($frame) {
    return array(
        'file'     => isset($frame['file']) ? $frame['file'] : '[internal]',
        'fileBase' => isset($frame['file']) ? basename($frame['file']) : '[internal]',
        'line'     => isset($frame['line']) ? $frame['line'] : 0,
        'function' => isset($frame['function']) ? $frame['function'] : '',
        'class'    => isset($frame['class']) ? $frame['class'] : '',
    );
}

/**
 * Convert a Throwable trace to a structured frames array.
 *
 * @param Throwable $exception The exception/error.
 * @return array Array of frame objects with file, line, function, class.
 */
function riseup_exception_to_frames($exception) {
    $frames = array();

    $frames[] = array(
        'file'     => $exception->getFile(),
        'fileBase' => basename($exception->getFile()),
        'line'     => $exception->getLine(),
        'function' => '',
        'class'    => '',
    );

    foreach ($exception->getTrace() as $frame) {
        $frames[] = riseup_build_single_frame($frame);
    }

    return $frames;
}

/**
 * Convert a debug_backtrace array to a structured frames array.
 *
 * @param array $backtrace The backtrace from debug_backtrace().
 * @return array Array of frame objects.
 */
function riseup_backtrace_to_frames($backtrace) {
    $frames = array();
    foreach ($backtrace as $frame) {
        $frames[] = riseup_build_single_frame($frame);
    }

    return $frames;
}

/**
 * Build structured stack trace lines and frames from a fatal error.
 *
 * @param array $error The error from error_get_last().
 * @return array{trace_lines: string[], frames: array}
 */
function riseup_build_fatal_frames($error) {
    $backtrace = null;
    if (function_exists('debug_backtrace')) {
        $backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
    }

    return array(
        'trace_lines' => riseup_build_trace_lines($error, $backtrace),
        'frames'      => riseup_build_structured_frames($error, $backtrace),
    );
}

/**
 * Build trace lines from a fatal error and optional backtrace.
 *
 * @param array      $error     The error from error_get_last().
 * @param array|null $backtrace Debug backtrace or null.
 * @return string[] Formatted trace lines.
 */
function riseup_build_trace_lines($error, $backtrace) {
    $trace_lines = array();
    $trace_lines[] = sprintf("#0 %s(%d): Fatal error occurred", $error['file'], $error['line']);

    if (is_array($backtrace)) {
        foreach ($backtrace as $i => $frame) {
            $file  = isset($frame['file']) ? $frame['file'] : '[internal]';
            $line  = isset($frame['line']) ? $frame['line'] : 0;
            $func  = isset($frame['function']) ? $frame['function'] : '';
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $trace_lines[] = sprintf("#%d %s(%d): %s%s()", $i + 1, $file, $line, $class, $func);
        }
    }

    $trace_lines[] = sprintf("#%d [internal function]: PHP shutdown handler", count($trace_lines));

    return $trace_lines;
}

/**
 * Build structured frames from a fatal error and optional backtrace.
 *
 * @param array      $error     The error from error_get_last().
 * @param array|null $backtrace Debug backtrace or null.
 * @return array Structured frame objects.
 */
function riseup_build_structured_frames($error, $backtrace) {
    $frames = array(
        array(
            'file'     => $error['file'],
            'fileBase' => basename($error['file']),
            'line'     => $error['line'],
            'function' => 'fatal_error',
            'class'    => '',
        ),
    );

    if (is_array($backtrace)) {
        foreach ($backtrace as $frame) {
            $frames[] = riseup_build_single_frame($frame);
        }
    }

    $frames[] = array(
        'file'     => '[internal]',
        'fileBase' => '[internal]',
        'line'     => 0,
        'function' => 'shutdown_handler',
        'class'    => 'PHP',
    );

    return $frames;
}

/**
 * Build the details sub-array for a fatal error response.
 *
 * @param array    $error       The error from error_get_last().
 * @param string[] $trace_lines Formatted stack trace lines.
 * @param array    $frames      Structured frame objects.
 * @return array Details array.
 */
function riseup_build_fatal_details($error, $trace_lines, $frames) {
    return array(
        'type'             => $error['type'],
        'typeName'         => riseup_error_type_to_string($error['type']),
        'message'          => $error['message'],
        'file'             => basename($error['file']),
        'fileFull'         => $error['file'],
        'line'             => $error['line'],
        'stackTrace'       => implode("\n", $trace_lines),
        'stackTraceFrames' => $frames,
        'phpVersion'       => phpversion(),
        'wpVersion'        => defined('WP_VERSION') ? WP_VERSION : 'unknown',
        'memoryUsage'      => memory_get_usage(true),
        'memoryPeak'       => memory_get_peak_usage(true),
        'memoryLimit'      => ini_get('memory_limit'),
        'requestUri'       => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
        'requestMethod'    => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN',
    );
}
