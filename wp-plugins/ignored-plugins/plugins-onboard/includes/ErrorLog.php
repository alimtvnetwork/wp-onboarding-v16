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
     * Log an exception with context message via OnboardLogger.
     *
     * Delegates to OnboardLogger::error() which writes to the structured
     * error log file. Replaces direct error_log() calls.
     *
     * @param Throwable $e       The exception to log.
     * @param string    $context Human-readable context message.
     */
    public static function log(Throwable $e, string $context): void {
        OnboardLogger::error($context . ' ' . $e->getMessage(), $e);
    }

    /**
     * Log an exception and re-throw it.
     *
     * Use this in boot, hook registration, and infrastructure catch blocks
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
