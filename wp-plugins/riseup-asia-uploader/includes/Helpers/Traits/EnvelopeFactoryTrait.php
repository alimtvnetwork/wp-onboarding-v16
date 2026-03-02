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
use RiseupAsia\Logging\FileLogger;

trait EnvelopeFactoryTrait {
    public static function success(string $message = 'OK', int $code = HttpStatusType::Ok->value): static {
        $builder = new self();
        $builder->isSuccess = true;
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
        $builder->isSuccess = false;
        $builder->code = $code;
        $builder->message = $message;
        $builder->hasErrors = true;

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
        $traceLines = explode("\n", $exception->getTraceAsString());
        $errors['DelegatedServiceErrorStack'] = $traceLines;

        if (class_exists(FrameBuilder::class)) {
            $frames = FrameBuilder::exceptionToFrames($exception);
            $errors['Backend'] = self::framesToLines($frames);
        }

        return $errors;
    }

    private static function buildBacktraceErrors(array $errors): array {
        $depth = class_exists(FileLogger::class, false) ? FileLogger::getInstance()->getStackTraceDepth() : 15;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth > 0 ? $depth : 15);

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
