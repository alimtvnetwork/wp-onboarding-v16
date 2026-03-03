<?php
/**
 * ErrorLogHelper — Static error_log wrapper for QUpload.
 *
 * @package QUpload\Helpers
 * @since   1.0.0
 */

namespace QUpload\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

class ErrorLogHelper {
    /**
     * Log an exception with context message to PHP's native error_log.
     *
     * Internally appends $e->getMessage() and $e->getTraceAsString().
     * Use this in catch blocks where FileLogger is not available.
     */
    public static function errorLog(Throwable $e, string $context): void {
        error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
