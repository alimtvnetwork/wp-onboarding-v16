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

        if ($this->is_duplicate(LogLevelType::Debug->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->format_entry(LogLevelType::Debug->value, $message, $file, $line, $context);
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

        if ($this->is_duplicate(LogLevelType::Info->value, $message, $file, $line)) {
            return true;
        }

        $entry = $this->format_entry(LogLevelType::Info->value, $message, $file, $line, $context);
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

        if ($this->is_duplicate(LogLevelType::Warn->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepare_context($context, $trace, true);
        $formatted_trace = $this->format_backtrace($trace);

        $entry = $this->format_entry(LogLevelType::Warn->value, $message, $file, $line, $context);
        $this->persist_to_error_sessions(LogLevelType::Warn->value, $message, $file, $line, $context, $formatted_trace);

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

        if ($this->is_duplicate(LogLevelType::Error->value, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepare_context($context, $trace, true);

        $entry = $this->format_entry(LogLevelType::Error->value, $message, $file, $line, $context);
        $formatted_trace = $this->format_backtrace($trace);
        $this->persist_to_error_sessions(LogLevelType::Error->value, $message, $file, $line, $context, $formatted_trace);
        $this->write_stacktrace($message, $file, $line, $formatted_trace);

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
    public function log_at($level, $message, $file, $line, $context = array()) {
        if ($this->is_duplicate($level, $message, $file, $line)) {
            return true;
        }

        $context = $this->prepare_context($context);
        $is_error = ($level === LogLevelType::Error->value);
        $entry = $this->format_entry($level, $message, $file, $line, $context);

        return $this->write($entry, $is_error);
    }

    /**
     * Log an exception.
     *
     * @param \Throwable $e       Exception to log.
     * @param string     $context Additional context message.
     * @return bool
     */
    public function log_exception($e, $context = '') {
        if ($this->is_duplicate(LogLevelType::Error->value, $e->getMessage(), $e->getFile(), $e->getLine())) {
            return true;
        }

        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();
        $ctx = $this->prepare_context(array('trace' => $e->getTraceAsString()));
        $entry = $this->format_entry(LogLevelType::Error->value, $message, $e->getFile(), $e->getLine(), $ctx);
        $this->persist_to_error_sessions(LogLevelType::Error->value, $message, $e->getFile(), $e->getLine(), array(), $e->getTraceAsString());
        $this->write_stacktrace($message, $e->getFile(), $e->getLine(), $e->getTraceAsString());

        return $this->write($entry, true);
    }
}
