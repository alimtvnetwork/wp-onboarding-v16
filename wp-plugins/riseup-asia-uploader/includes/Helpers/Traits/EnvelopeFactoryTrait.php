<?php
/**
 * EnvelopeFactoryTrait — static factory methods and error block construction.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\ErrorHandling\FrameBuilder;
use RiseupAsia\Enums\HttpStatusType;

trait EnvelopeFactoryTrait {

    public static function success(string $message = 'OK', int $code = HttpStatusType::Ok->value): static {
        $builder = new self();
        $builder->is_success = true;
        $builder->code = $code;
        $builder->message = $message;

        return $builder;
    }

    public static function error(
        string $message,
        int $code = HttpStatusType::ServerError->value,
        ?Throwable $exception = null,
    ): static {
        $builder = new self();
        $builder->is_success = false;
        $builder->code = $code;
        $builder->message = $message;
        $builder->has_errors = true;

        $errors = array(
            'BackendMessage' => $message,
            'DelegatedServiceErrorStack' => array(),
            'Backend' => array(),
            'Frontend' => array(),
        );

        if ($exception instanceof Throwable) {
            $errors = self::buildExceptionErrors($errors, $exception);
        } else {
            $errors = self::buildBacktraceErrors($errors);
        }

        $builder->errors = $errors;

        return $builder;
    }

    private static function buildExceptionErrors(array $errors, Throwable $exception): array {
        $trace_lines = explode("\n", $exception->getTraceAsString());
        $errors['DelegatedServiceErrorStack'] = $trace_lines;

        if (class_exists(FrameBuilder::class)) {
            $frames = FrameBuilder::exceptionToFrames($exception);
            $errors['Backend'] = self::framesToLines($frames);
        }

        return $errors;
    }

    private static function buildBacktraceErrors(array $errors): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);

        if (class_exists(FrameBuilder::class)) {
            $frames = FrameBuilder::backtraceToFrames($backtrace);
            $errors['Backend'] = self::framesToLines($frames);
        }

        return $errors;
    }

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
