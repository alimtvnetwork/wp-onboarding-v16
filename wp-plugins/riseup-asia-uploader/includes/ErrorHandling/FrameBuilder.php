<?php
namespace RiseupAsia\ErrorHandling;

use Throwable;
use RiseupAsia\Enums\ResponseKeyType;

/**
 * Stack trace frame construction utilities for error diagnostics.
 *
 * @since 1.57.0
 */
class FrameBuilder
{
    private const INTERNAL_LABEL = '[internal]';

    /**
     * @return array{file: string, fileBase: string, line: int, function: string, class: string}
     */
    public static function buildSingleFrame(array $frame): array {
        return array(
            'file'     => $frame['file'] ?? self::INTERNAL_LABEL,
            'fileBase' => isset($frame['file']) ? basename($frame['file']) : self::INTERNAL_LABEL,
            'line'     => $frame['line'] ?? 0,
            'function' => $frame['function'] ?? '',
            'class'    => $frame['class'] ?? '',
        );
    }

    /**
     * @return array<int, array{file: string, fileBase: string, line: int, function: string, class: string}>
     */
    public static function exceptionToFrames(Throwable $exception): array {
        $frames = array();

        $frames[] = array(
            'file'     => $exception->getFile(),
            'fileBase' => basename($exception->getFile()),
            'line'     => $exception->getLine(),
            'function' => '',
            'class'    => '',
        );

        foreach ($exception->getTrace() as $frame) {
            $frames[] = self::buildSingleFrame($frame);
        }

        return $frames;
    }

    /**
     * @return array<int, array{file: string, fileBase: string, line: int, function: string, class: string}>
     */
    public static function backtraceToFrames(array $backtrace): array {
        $frames = array();

        foreach ($backtrace as $frame) {
            $frames[] = self::buildSingleFrame($frame);
        }

        return $frames;
    }

    /**
     * @return array{trace_lines: string[], frames: array}
     */
    public static function buildFatalFrames(array $error): array {
        $backtrace = null;

        if (function_exists('debug_backtrace')) {
            $backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        }

        return array(
            ResponseKeyType::TraceLines->value => self::buildTraceLines($error, $backtrace),
            'frames'      => self::buildStructuredFrames($error, $backtrace),
        );
    }

    /**
     * @return string[]
     */
    public static function buildTraceLines(array $error, ?array $backtrace): array {
        $traceLines = array();
        $traceLines[] = sprintf("#0 %s(%d): Fatal error occurred", $error['file'], $error['line']);

        if (is_array($backtrace)) {
            foreach ($backtrace as $i => $frame) {
                $file  = $frame['file'] ?? self::INTERNAL_LABEL;
                $line  = $frame['line'] ?? 0;
                $func  = $frame['function'] ?? '';
                $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
                $traceLines[] = sprintf("#%d %s(%d): %s%s()", $i + 1, $file, $line, $class, $func);
            }
        }

        $traceLines[] = sprintf("#%d [internal function]: PHP shutdown handler", count($traceLines));

        return $traceLines;
    }

    public static function buildStructuredFrames(array $error, ?array $backtrace): array {
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
                $frames[] = self::buildSingleFrame($frame);
            }
        }

        $frames[] = array(
            'file'     => self::INTERNAL_LABEL,
            'fileBase' => self::INTERNAL_LABEL,
            'line'     => 0,
            'function' => 'shutdown_handler',
            'class'    => 'PHP',
        );

        return $frames;
    }

    public static function buildFatalDetails(
        array $error,
        array $traceLines,
        array $frames,
    ): array {
        return array(
            'type'             => $error['type'],
            'typeName'         => FatalErrorHandler::errorTypeToString($error['type']),
            'message'          => $error['message'],
            'file'             => basename($error['file']),
            'fileFull'         => $error['file'],
            'line'             => $error['line'],
            'stackTrace'       => implode("\n", $traceLines),
            'stackTraceFrames' => $frames,
            'phpVersion'       => phpversion(),
            'wpVersion'        => defined('WP_VERSION') ? WP_VERSION : 'unknown',
            'memoryUsage'      => memory_get_usage(true),
            'memoryPeak'       => memory_get_peak_usage(true),
            'memoryLimit'      => ini_get('memory_limit'),
            'requestUri'       => $_SERVER['REQUEST_URI'] ?? '',
            'requestMethod'    => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        );
    }
}
