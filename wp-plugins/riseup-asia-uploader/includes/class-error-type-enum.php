<?php
/**
 * ErrorTypeEnum — PHP Error Type Constants
 *
 * Centralizes the E_* error type constants used for fatal error detection.
 * Used by ErrorChecker.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PHP error type groupings.
 *
 * Centralizes E_* constant lists so that error-checking logic
 * never needs to remember which error types are "fatal."
 *
 * WHY: If PHP adds a new fatal error type in a future version,
 * you update ONE array here. ErrorChecker automatically picks it up.
 */
class ErrorTypeEnum {

    /**
     * Error types that terminate PHP execution.
     * Used by ErrorChecker::is_fatal_error().
     *
     * E_ERROR         — Fatal run-time error (out of memory, etc.)
     * E_PARSE         — Compile-time parse error (syntax error)
     * E_CORE_ERROR    — Fatal error during PHP startup
     * E_COMPILE_ERROR — Fatal compile-time error (Zend Engine)
     * E_USER_ERROR    — User-triggered fatal error via trigger_error()
     */
    public const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    /**
     * Warning-level error types (non-fatal but logged).
     */
    public const WARNING_TYPES = [
        E_WARNING,
        E_CORE_WARNING,
        E_USER_WARNING,
        E_NOTICE,
        E_USER_NOTICE,
        E_DEPRECATED,
        E_USER_DEPRECATED,
    ];

    /**
     * Recoverable error types (can be caught by error handler).
     */
    public const RECOVERABLE_TYPES = [
        E_RECOVERABLE_ERROR,
        E_STRICT,
    ];

    /**
     * Complete mapping of E_* constants to human-readable labels.
     * Used by ErrorChecker::get_type_label() for log output.
     */
    public const TYPE_LABELS = [
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_WARNING           => 'E_WARNING',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_STRICT            => 'E_STRICT',
    ];
}
