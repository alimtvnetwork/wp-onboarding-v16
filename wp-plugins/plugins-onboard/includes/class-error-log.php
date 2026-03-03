<?php
/**
 * Error log helper for Plugins Onboard.
 *
 * Provides a static method to log exceptions with context and full stack trace.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnboardErrorLog {
    /**
     * Log an exception with context message to PHP's native error_log.
     *
     * Internally appends $e->getMessage() and $e->getTraceAsString().
     * Use this in catch blocks where OnboardLogger is not available or
     * as a supplementary log alongside OnboardLogger.
     *
     * @param Throwable $e       The exception to log.
     * @param string    $context Human-readable context message.
     */
    public static function log(Throwable $e, string $context): void {
        error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
