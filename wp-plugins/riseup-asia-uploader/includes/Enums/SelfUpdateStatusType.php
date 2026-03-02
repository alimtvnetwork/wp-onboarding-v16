<?php
/**
 * SelfUpdateStatusType — Standardized status codes for self-update rollback and diagnostics.
 *
 * Used in REST API responses to provide machine-readable reason codes
 * for self-update outcomes: success, validation failures, activation failures,
 * health check failures, and rollback results.
 *
 * @package RiseupAsia\Enums
 * @since   2.4.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SelfUpdateStatusType: string
{
    // ── Outcome ─────────────────────────────────────────────────────
    case Success                = 'SelfUpdateSuccess';
    case RolledBack             = 'SelfUpdateRolledBack';
    case RollbackFailed         = 'SelfUpdateRollbackFailed';

    // ── Rollback Reasons ────────────────────────────────────────────
    case BackupCreationFailed   = 'BackupCreationFailed';
    case ExtractionFailed       = 'ExtractionFailed';
    case ValidationFailed       = 'ValidationFailed';
    case ActivationException    = 'ActivationException';
    case ActivationWpError      = 'ActivationWpError';
    case HealthCheckFailed      = 'HealthCheckFailed';
    case PluginFileNotFound     = 'PluginFileNotFound';

    // ── Validation Error Codes ──────────────────────────────────────
    case CriticalFileMissing    = 'CriticalFileMissing';
    case SyntaxError            = 'SyntaxError';
    case FileUnreadable         = 'FileUnreadable';
    case DirectoryMissing       = 'DirectoryMissing';

    // ── Health Check Error Codes ────────────────────────────────────
    case BootErrorDetected      = 'BootErrorDetected';
    case CriticalClassMissing   = 'CriticalClassMissing';
    case RestHookMissing        = 'RestHookMissing';

    /**
     * Whether this status represents a failure that triggered or should trigger rollback.
     */
    public function isRollbackReason(): bool
    {
        return match ($this) {
            self::ExtractionFailed,
            self::ValidationFailed,
            self::ActivationException,
            self::ActivationWpError,
            self::HealthCheckFailed,
            self::PluginFileNotFound => true,
            default => false,
        };
    }

    /**
     * Whether this status represents a validation-phase error code.
     */
    public function isValidationError(): bool
    {
        return match ($this) {
            self::CriticalFileMissing,
            self::SyntaxError,
            self::FileUnreadable,
            self::DirectoryMissing => true,
            default => false,
        };
    }

    /**
     * Whether this status represents a health-check-phase error code.
     */
    public function isHealthCheckError(): bool
    {
        return match ($this) {
            self::BootErrorDetected,
            self::CriticalClassMissing,
            self::RestHookMissing => true,
            default => false,
        };
    }

    /**
     * Whether this status indicates the self-update completed successfully.
     */
    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    /**
     * Get a human-readable label for REST API responses.
     */
    public function label(): string
    {
        return match ($this) {
            self::Success                => 'Self-update completed successfully',
            self::RolledBack             => 'Self-update failed and previous version was restored',
            self::RollbackFailed         => 'Self-update failed and rollback also failed',
            self::BackupCreationFailed   => 'Failed to create pre-update backup',
            self::ExtractionFailed       => 'ZIP extraction failed during self-update',
            self::ValidationFailed       => 'Pre-activation validation failed',
            self::ActivationException    => 'Plugin activation threw an uncaught exception',
            self::ActivationWpError      => 'Plugin activation returned a WordPress error',
            self::HealthCheckFailed      => 'Post-activation health check detected issues',
            self::PluginFileNotFound     => 'Main plugin file not found after extraction',
            self::CriticalFileMissing    => 'A critical file is missing from the new version',
            self::SyntaxError            => 'A PHP syntax error was detected in the new version',
            self::FileUnreadable         => 'A PHP file could not be read for validation',
            self::DirectoryMissing       => 'Plugin directory does not exist after extraction',
            self::BootErrorDetected      => 'BootErrorCollector captured errors during activation',
            self::CriticalClassMissing   => 'A critical class was not loaded after activation',
            self::RestHookMissing        => 'REST API hooks were not registered after activation',
        };
    }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
