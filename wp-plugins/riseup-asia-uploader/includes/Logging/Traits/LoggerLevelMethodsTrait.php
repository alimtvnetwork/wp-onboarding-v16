<?php
/**
 * Logger Level Methods Trait
 *
 * Public convenience methods for each log level (debug, info, warn, error).
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait LoggerLevelMethodsTrait {

    /**
     * Log a debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return bool
     */
    public function debug($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->isDuplicate(LogLevelType::Debug->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->formatEntry(LogLevelType::Debug->value, $message, $file, $line, $context);
        return $this->write($entry, false);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return bool
     */
    public function info($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->isDuplicate(LogLevelType::Info->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->formatEntry(LogLevelType::Info->value, $message, $file, $line, $context);
        return $this->write($entry, false);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return bool
     */
    public function warn($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->isDuplicate(LogLevelType::Warn->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context, $trace, true);
        $formattedTrace = $this->formatBacktrace($trace);

        $entry = $this->formatEntry(LogLevelType::Warn->value, $message, $file, $line, $context);
        $this->persistToErrorSessions(LogLevelType::Warn->value, $message, $file, $line, $context, $formattedTrace);

        return $this->write($entry, false);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     * @return bool
     */
    public function error($message, $context = array()) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
        $caller = isset($trace[1]) ? $trace[1] : $trace[0];
        $file = isset($caller['file']) ? $caller['file'] : __FILE__;
        $line = isset($caller['line']) ? $caller['line'] : __LINE__;

        if ($this->isDuplicate(LogLevelType::Error->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context, $trace, true);

        $entry = $this->formatEntry(LogLevelType::Error->value, $message, $file, $line, $context);
        $formattedTrace = $this->formatBacktrace($trace);
        $this->persistToErrorSessions(LogLevelType::Error->value, $message, $file, $line, $context, $formattedTrace);
        $this->writeStacktrace($message, $file, $line, $formattedTrace);

        return $this->write($entry, true);
    }

    /**
     * Log with explicit file and line.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line.
     * @param array  $context Additional context.
     * @return bool
     */
    public function logAt($level, $message, $file, $line, $context = array()) {
        if ($this->isDuplicate($level, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepareContext($context);
        $levelEnum = LogLevelType::from($level);
        $isError = $levelEnum->isError();
        $entry = $this->formatEntry($level, $message, $file, $line, $context);

        return $this->write($entry, $isError);
    }

    /**
     * Log an exception.
     *
     * @param Throwable $e       Exception to log.
     * @param string     $context Additional context message.
     * @return bool
     */
    public function logException($e, $context = '') {
        if ($this->isDuplicate(LogLevelType::Error->value, $e->getMessage(), $e->getFile(), $e->getLine())) {
            return true;
        }

        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $ctx = $this->prepareContext(array('trace' => $e->getTraceAsString()));
        $entry = $this->formatEntry(LogLevelType::Error->value, $message, $e->getFile(), $e->getLine(), $ctx);
        $this->persistToErrorSessions(LogLevelType::Error->value, $message, $e->getFile(), $e->getLine(), array(), $e->getTraceAsString());
        $this->writeStacktrace($message, $e->getFile(), $e->getLine(), $e->getTraceAsString());

        return $this->write($entry, true);
    }
}
