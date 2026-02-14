<?php
/**
 * ErrorChecker — Centralized Error Type Inspection
 *
 * Encapsulates raw E_* constant checks so callers never need to
 * remember the specific list. Delegates to ErrorType for the
 * actual constant groupings.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ErrorType;

/**
 * Centralized error-type inspection.
 *
 * WHY THIS CLASS EXISTS:
 * - Inline `in_array($error['type'], [E_ERROR, ...])` is duplicated
 *   across shutdown handlers, loggers, and health checks.
 * - A single is_fatal_error() call is self-documenting for AI and humans.
 * - Adding a new fatal type requires changing ONE place (ErrorType).
 */
class ErrorChecker {

    /**
     * Determine whether the given error array represents a fatal PHP error.
     *
     * @param array|null $error Value returned by error_get_last().
     * @return bool True when $error is non-null and its type is fatal.
     */
    public static function is_fatal_error($error) {
        if ($error === null) {
            return false;
        }

        return in_array($error['type'], ErrorType::FATAL_TYPES, true);
    }

    /**
     * Determine whether the given error array is a warning-level error.
     *
     * @param array|null $error Value returned by error_get_last().
     * @return bool
     */
    public static function is_warning($error) {
        if ($error === null) {
            return false;
        }

        return in_array($error['type'], ErrorType::WARNING_TYPES, true);
    }

    /**
     * Determine whether the given error is recoverable.
     *
     * @param array|null $error Value returned by error_get_last().
     * @return bool
     */
    public static function is_recoverable($error) {
        if ($error === null) {
            return false;
        }

        return in_array($error['type'], ErrorType::RECOVERABLE_TYPES, true);
    }

    /**
     * Get a human-readable label for the error severity.
     *
     * @param array|null $error Value returned by error_get_last().
     * @return string 'fatal', 'warning', 'recoverable', or 'unknown'.
     */
    public static function get_severity_label($error) {
        if ($error === null) {
            return 'unknown';
        }

        if (self::is_fatal_error($error)) {
            return 'fatal';
        }

        if (self::is_warning($error)) {
            return 'warning';
        }

        if (self::is_recoverable($error)) {
            return 'recoverable';
        }

        return 'unknown';
    }

    /**
     * Convert an E_* integer to a human-readable string.
     *
     * Replaces all inline type-mapping arrays.
     *
     * @param int $type PHP error type constant (e.g., E_ERROR).
     * @return string Human-readable label (e.g., 'E_ERROR') or 'UNKNOWN_ERROR_TYPE'.
     */
    public static function get_type_label($type) {
        if (isset(ErrorType::TYPE_LABELS[$type])) {
            return ErrorType::TYPE_LABELS[$type];
        }

        return 'UNKNOWN_ERROR_TYPE';
    }

    /**
     * Check if PDO and pdo_sqlite extensions are NOT available.
     *
     * Centralizes the extension check so inline `class_exists('PDO')`
     * or `extension_loaded('pdo_sqlite')` are never needed in business logic.
     *
     * @return bool True when PDO/SQLite is NOT available (invalid state).
     */
    public static function is_invalid_pdo_extension() {
        return RiseupBooleanHelpers::isClassMissing('PDO') || RiseupBooleanHelpers::isExtensionMissing('pdo_sqlite');
    }
}
