<?php
/**
 * FatalErrorHandler — JSON fatal error response for REST requests.
 *
 * Standalone functions that detect fatal PHP errors during REST requests
 * and emit structured JSON responses instead of blank pages.
 *
 * @package RiseupAsia\ErrorHandling
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PathLogFileType;
/**
 * Check whether the last PHP error is a fatal REST API error for our namespace.
 *
 * @param array|null $error Result of error_get_last().
 * @return bool True if this is a fatal error on a plugin REST request.
 */
function riseup_is_fatal_rest_error($error) {
    if ($error === null) {
        return false;
    }

    $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    $isFatalType = in_array($error['type'], $fatal_types, true);

    if (!$isFatalType) {
        return false;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $isPluginRequest = strpos($request_uri, 'riseup-asia-uploader') !== false || strpos($request_uri, 'wp-json') !== false;

    return $isPluginRequest;
}

/**
 * Build the JSON response array for a fatal error.
 *
 * @param array    $error       The error from error_get_last().
 * @param string[] $trace_lines Formatted stack trace lines.
 * @param array    $frames      Structured frame objects.
 * @return array Response envelope.
 */
function riseup_build_fatal_response($error, $trace_lines, $frames) {
    return array(
        'success' => false,
        'error'   => array(
            'code'    => 'FATAL_ERROR',
            'message' => 'A fatal error occurred in the plugin: ' . $error['message'],
            'details' => riseup_build_fatal_details($error, $trace_lines, $frames),
        ),
    );
}

/**
 * Log a fatal error to the plugin's log file.
 *
 * @param array $error The error from error_get_last().
 */
function riseup_log_fatal_to_file($error) {
    $log_entry = sprintf(
        "[%s] FATAL ERROR in %s:%d - %s (type: %s)\n",
        date('Y-m-d H:i:s'),
        $error['file'],
        $error['line'],
        $error['message'],
        riseup_error_type_to_string($error['type'])
    );
    $uploads  = wp_upload_dir();
    $log_file = $uploads['basedir'] . '/riseup-asia-uploader' . PathLogFileType::FatalError->value;
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Clean all output buffers.
 */
function riseup_clean_output_buffers() {
    while (ob_get_level()) {
        @ob_end_clean();
    }
}

/**
 * Emit the fatal error JSON response and exit.
 *
 * @param array $error The error from error_get_last().
 */
function riseup_emit_fatal_json_response($error) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    $frame_data = riseup_build_fatal_frames($error);
    $response   = riseup_build_fatal_response($error, $frame_data['trace_lines'], $frame_data['frames']);

    $json = @json_encode($response, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        echo json_encode(riseup_build_fatal_fallback($error));
    } else {
        echo $json;
    }

    exit;
}

/**
 * Build a minimal fallback response when JSON encoding fails.
 *
 * @param array $error The error from error_get_last().
 * @return array Minimal response.
 */
function riseup_build_fatal_fallback($error) {
    return array(
        'success' => false,
        'error'   => array(
            'code'    => 'FATAL_ERROR_ENCODING_FAILED',
            'message' => 'Fatal error occurred and JSON encoding also failed',
            'details' => array(
                'originalMessage' => substr($error['message'], 0, 500),
                'file'            => basename($error['file']),
                'line'            => $error['line'],
                'jsonError'       => json_last_error_msg(),
            ),
        ),
    );
}

/**
 * Custom error handler to catch fatal errors and return JSON response.
 */
function riseup_fatal_error_handler() {
    $error = error_get_last();

    if (!riseup_is_fatal_rest_error($error)) {
        return;
    }

    riseup_log_fatal_to_file($error);
    riseup_clean_output_buffers();
    riseup_emit_fatal_json_response($error);
}

/**
 * Convert PHP error type to human-readable string.
 *
 * @param int $type Error type constant.
 * @return string
 */
function riseup_error_type_to_string($type) {
    $types = array(
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_WARNING           => 'E_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    );

    return isset($types[$type]) ? $types[$type] : 'UNKNOWN_ERROR_TYPE';
}

register_shutdown_function('riseup_fatal_error_handler');
