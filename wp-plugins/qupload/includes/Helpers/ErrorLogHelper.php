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
    public static function log(Throwable $e, string $context): void {
        error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    /**
     * Log an exception and re-throw it.
     *
     * Use this in boot, autoloader, route registration, and infrastructure catch blocks
     * where silent failure causes cascading breakage. The throw happens internally —
     * call sites do not need a separate `throw $e;` statement.
     *
     * @throws Throwable Always re-throws the original exception after logging.
     */
    public static function logAndThrow(Throwable $e, string $context): never {
        self::log($e, $context);

        throw $e;
    }
}
