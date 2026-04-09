# SelfUpdateStatusType — Reference Implementation

**Version:** 1.1.0
**Updated:** 2026-04-09

> **Purpose:** Full reference enum using the info-object pattern. Used by Phase 10 deployment and self-update system.

---

## EnumInfo Value Object

```php
namespace PluginName\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * EnumInfo — Immutable metadata for enum cases.
 *
 * @package PluginName\Enums
 * @since   1.0.0
 */
final readonly class EnumInfo
{
    public function __construct(
        public string $label,
    ) {}
}
```

---

## SelfUpdateStatusType Enum

```php
namespace PluginName\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SelfUpdateStatusType: string
{
    // ── Outcomes ────────────────────────────────────────────
    case Success              = 'SelfUpdateSuccess';
    case RolledBack           = 'SelfUpdateRolledBack';
    case RollbackFailed       = 'SelfUpdateRollbackFailed';

    // ── Rollback Reasons ────────────────────────────────────
    case BackupCreationFailed = 'BackupCreationFailed';
    case ExtractionFailed     = 'ExtractionFailed';
    case ValidationFailed     = 'ValidationFailed';
    case ActivationException  = 'ActivationException';
    case ActivationWpError    = 'ActivationWpError';
    case HealthCheckFailed    = 'HealthCheckFailed';
    case PluginFileNotFound   = 'PluginFileNotFound';

    // ── Validation Errors ───────────────────────────────────
    case CriticalFileMissing  = 'CriticalFileMissing';
    case SyntaxError          = 'SyntaxError';
    case FileUnreadable       = 'FileUnreadable';
    case DirectoryMissing     = 'DirectoryMissing';

    // ── Health Check Errors ─────────────────────────────────
    case BootErrorDetected    = 'BootErrorDetected';
    case CriticalClassMissing = 'CriticalClassMissing';
    case RestHookMissing      = 'RestHookMissing';

    // ── Info Map ────────────────────────────────────────────

    /**
     * @return array<string, EnumInfo>
     */
    private static function infoMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [
                self::Success->value              => new EnumInfo(label: 'Self-update completed successfully'),
                self::RolledBack->value           => new EnumInfo(label: 'Self-update failed; previous version restored'),
                self::RollbackFailed->value       => new EnumInfo(label: 'Self-update failed; rollback also failed'),
                self::BackupCreationFailed->value => new EnumInfo(label: 'Failed to create pre-update backup'),
                self::ExtractionFailed->value     => new EnumInfo(label: 'ZIP extraction failed during self-update'),
                self::ValidationFailed->value     => new EnumInfo(label: 'Pre-activation validation failed'),
                self::ActivationException->value  => new EnumInfo(label: 'Plugin activation threw an uncaught exception'),
                self::ActivationWpError->value    => new EnumInfo(label: 'Plugin activation returned a WordPress error'),
                self::HealthCheckFailed->value    => new EnumInfo(label: 'Post-activation health check detected issues'),
                self::PluginFileNotFound->value   => new EnumInfo(label: 'Main plugin file not found after extraction'),
                self::CriticalFileMissing->value  => new EnumInfo(label: 'A critical file is missing from the new version'),
                self::SyntaxError->value          => new EnumInfo(label: 'PHP syntax error detected in the new version'),
                self::FileUnreadable->value       => new EnumInfo(label: 'A PHP file could not be read for validation'),
                self::DirectoryMissing->value     => new EnumInfo(label: 'Plugin directory missing after extraction'),
                self::BootErrorDetected->value    => new EnumInfo(label: 'Boot errors captured during activation'),
                self::CriticalClassMissing->value => new EnumInfo(label: 'A critical class was not loaded after activation'),
                self::RestHookMissing->value      => new EnumInfo(label: 'REST API hooks not registered after activation'),
            ];
        }

        return $map;
    }

    // ── Public API ──────────────────────────────────────────

    public function info(): EnumInfo
    {
        return self::infoMap()[$this->value];
    }

    public function label(): string
    {
        return $this->info()->label;
    }

    // ── Per-Case Helpers ────────────────────────────────────

    public function isSuccess(): bool              { return $this->isEqual(self::Success); }
    public function isRolledBack(): bool           { return $this->isEqual(self::RolledBack); }
    public function isRollbackFailed(): bool       { return $this->isEqual(self::RollbackFailed); }
    public function isBackupCreationFailed(): bool { return $this->isEqual(self::BackupCreationFailed); }
    public function isExtractionFailed(): bool     { return $this->isEqual(self::ExtractionFailed); }
    public function isValidationFailed(): bool     { return $this->isEqual(self::ValidationFailed); }
    public function isActivationException(): bool  { return $this->isEqual(self::ActivationException); }
    public function isActivationWpError(): bool    { return $this->isEqual(self::ActivationWpError); }
    public function isHealthCheckFailed(): bool    { return $this->isEqual(self::HealthCheckFailed); }
    public function isPluginFileNotFound(): bool   { return $this->isEqual(self::PluginFileNotFound); }
    public function isCriticalFileMissing(): bool  { return $this->isEqual(self::CriticalFileMissing); }
    public function isSyntaxError(): bool          { return $this->isEqual(self::SyntaxError); }
    public function isFileUnreadable(): bool       { return $this->isEqual(self::FileUnreadable); }
    public function isDirectoryMissing(): bool     { return $this->isEqual(self::DirectoryMissing); }
    public function isBootErrorDetected(): bool    { return $this->isEqual(self::BootErrorDetected); }
    public function isCriticalClassMissing(): bool { return $this->isEqual(self::CriticalClassMissing); }
    public function isRestHookMissing(): bool      { return $this->isEqual(self::RestHookMissing); }

    // ── Group Helpers ───────────────────────────────────────

    public function isRollbackReason(): bool
    {
        return $this->isAnyOf(
            self::ExtractionFailed,
            self::ValidationFailed,
            self::ActivationException,
            self::ActivationWpError,
            self::HealthCheckFailed,
            self::PluginFileNotFound,
        );
    }

    public function isValidationError(): bool
    {
        return $this->isAnyOf(
            self::CriticalFileMissing,
            self::SyntaxError,
            self::FileUnreadable,
            self::DirectoryMissing,
        );
    }

    public function isHealthCheckError(): bool
    {
        return $this->isAnyOf(
            self::BootErrorDetected,
            self::CriticalClassMissing,
            self::RestHookMissing,
        );
    }

    // ── Standard Comparison Methods ─────────────────────────

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
```

---

## Usage

```php
use PluginName\Enums\SelfUpdateStatusType;

$status = SelfUpdateStatusType::HealthCheckFailed;

// Per-case helper
$status->isHealthCheckFailed();  // true
$status->isSuccess();            // false

// Group helper
$status->isRollbackReason();     // true
$status->isHealthCheckError();   // true

// Label via info delegation
$status->label();
// → "Post-activation health check detected issues"
```

---

## Cross-References

- [02-enum-info-object-pattern.md](02-enum-info-object-pattern.md) — the pattern this enum follows
- [Phase 10 — Deployment Patterns](../10-deployment-patterns.md#105-self-update-with-rollback) — uses this enum

---

*Reference implementation of the info-object enum pattern.*
