<?php
/**
 * Logger Level Methods Trait — Public convenience methods for each log level.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Logging\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;

trait LoggerLevelMethodsTrait {
    /** Resolve caller file and line from a backtrace. */
    private function resolveCaller(array $trace): array {
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];

        return array(
            isset($caller['file']) ? $caller['file'] : __FILE__,
            isset($caller['line']) ? $caller['line'] : __LINE__,
        );
    }

    /** Log a debug message. */
    public function debug(string $message, array $context = array()): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        [$file, $line] = $this->resolveCaller($trace);

        if ($this->isDuplicate(LogLevelType::Debug->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->formatEntry(
            LogLevelType::Debug->value,
            $message,
            $file,
            $line,
            $context,
        );

        return $this->write($entry, false);
    }

    /** Log an info message. */
    public function info(string $message, array $context = array()): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        [$file, $line] = $this->resolveCaller($trace);

        if ($this->isDuplicate(LogLevelType::Info->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->formatEntry(
            LogLevelType::Info->value,
            $message,
            $file,
            $line,
            $context,
        );

        return $this->write($entry, false);
    }

    /** Log a warning message. */
    public function warn(string $message, array $context = array()): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        [$file, $line] = $this->resolveCaller($trace);

        if ($this->isDuplicate(LogLevelType::Warn->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context, $trace, true);
        $formattedTrace = $this->formatBacktrace($trace);

        $entry = $this->formatEntry(
            LogLevelType::Warn->value,
            $message,
            $file,
            $line,
            $context,
        );

        $this->persistToErrorSessions(
            LogLevelType::Warn->value,
            $message,
            $file,
            $line,
            $context,
            $formattedTrace,
        );

        return $this->write($entry, false);
    }

    /** Log an error message. */
    public function error(string $message, array $context = array()): bool {
        $depth = $this->stackTraceDepth > 0 ? $this->stackTraceDepth : 0;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
        [$file, $line] = $this->resolveCaller($trace);

        if ($this->isDuplicate(LogLevelType::Error->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context, $trace, true);

        $entry = $this->formatEntry(
            LogLevelType::Error->value,
            $message,
            $file,
            $line,
            $context,
        );

        $formattedTrace = $this->formatBacktrace($trace);

        $this->persistToErrorSessions(
            LogLevelType::Error->value,
            $message,
            $file,
            $line,
            $context,
            $formattedTrace,
        );

        $this->writeStacktrace(
            $message,
            $file,
            $line,
            $formattedTrace,
        );

        return $this->write($entry, true);
    }

    /** Log with explicit file and line. */
    public function logAt(
        string $level,
        string $message,
        string $file,
        int $line,
        array $context = array(),
    ): bool {
        if ($this->isDuplicate($level, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context);
        $levelEnum = LogLevelType::from($level);
        $isError = $levelEnum->isError();

        $entry = $this->formatEntry(
            $level,
            $message,
            $file,
            $line,
            $context,
        );

        return $this->write($entry, $isError);
    }

    /** Log an exception. */
    public function logException(Throwable $e, string $context = ''): bool {
        if ($this->isDuplicate(
            LogLevelType::Error->value,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        )) {
            return true;
        }

        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $ctx = $this->prepareContext(array('trace' => $e->getTraceAsString()));

        $entry = $this->formatEntry(
            LogLevelType::Error->value,
            $message,
            $e->getFile(),
            $e->getLine(),
            $ctx,
        );

        $this->persistToErrorSessions(
            LogLevelType::Error->value,
            $message,
            $e->getFile(),
            $e->getLine(),
            array(),
            $e->getTraceAsString(),
        );

        $this->writeStacktrace(
            $message,
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );

        return $this->write($entry, true);
    }

    /**
     * Log an exception and re-throw it.
     *
     * Use this in boot, route registration, migration, and infrastructure catch blocks
     * where silent failure causes cascading breakage. The throw happens internally —
     * call sites do not need a separate `throw $e;` statement.
     *
     * @throws Throwable Always re-throws the original exception after logging.
     */
    public function logCriticalException(Throwable $e, string $context = ''): never {
        $this->logException($e, $context);

        throw $e;
    }

    /** Log an exception at debug level (for expected/recoverable exceptions). */
    public function logDebugException(Throwable $e, string $context = ''): bool {
        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        [$file, $line] = $this->resolveCaller($trace);
        $ctx = $this->prepareContext(array('trace' => $e->getTraceAsString()));

        $entry = $this->formatEntry(
            LogLevelType::Debug->value,
            $message,
            $file,
            $line,
            $ctx,
        );

        return $this->write($entry, false);
    }
}
