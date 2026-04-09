# ActionType — Info-Object Reference Implementation

**Version:** 1.0.0
**Updated:** 2026-04-09

> **Purpose:** Second reference implementation of the info-object pattern, demonstrating a large enum (30+ cases) with grouped metadata.

---

## EnumInfo Value Object

> Shared with `SelfUpdateStatusType` — defined once per plugin in `includes/Enums/EnumInfo.php`.

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
        public string $details = '',
    ) {}
}
```

---

## ActionType Enum

```php
namespace PluginName\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ActionType: string
{
    // ── Core Plugin Actions ─────────────────────────────────
    case Upload           = 'Upload';
    case UploadActive     = 'UploadActive';
    case UploadInitiated  = 'UploadInitiated';
    case Enable           = 'Enable';
    case Disable          = 'Disable';
    case Delete           = 'Delete';
    case FileReplace      = 'FileReplace';
    case FileDelete       = 'FileDelete';
    case Sync             = 'Sync';
    case SyncDelete       = 'SyncDelete';

    // ── Post/Content Actions ────────────────────────────────
    case PostCreate       = 'PostCreate';
    case PostUpdate       = 'PostUpdate';
    case CategoryCreate   = 'CategoryCreate';
    case MediaUpload      = 'MediaUpload';

    // ── Auth ────────────────────────────────────────────────
    case AuthFailed       = 'AuthFailed';

    // ── Export Actions ──────────────────────────────────────
    case ExportSelf       = 'ExportSelf';
    case ExportPlugin     = 'ExportPlugin';

    // ── Plugin Backup Actions ───────────────────────────────
    case PluginBackup        = 'PluginBackup';
    case PluginBackupRestore = 'PluginBackupRestore';
    case PluginBackupDelete  = 'PluginBackupDelete';

    // ── Agent Actions ───────────────────────────────────────
    case AgentAdd           = 'AgentAdd';
    case AgentRemove        = 'AgentRemove';
    case AgentTest          = 'AgentTest';
    case AgentSync          = 'AgentSync';
    case AgentApiError      = 'AgentApiError';

    // ── Snapshot Actions ────────────────────────────────────
    case SnapshotCreate          = 'SnapshotCreate';
    case SnapshotRestore         = 'SnapshotRestore';
    case SnapshotDelete          = 'SnapshotDelete';
    case SnapshotExport          = 'SnapshotExport';
    case SnapshotImport          = 'SnapshotImport';
    case SnapshotCleanup         = 'SnapshotCleanup';
    case SnapshotFullBackup      = 'SnapshotFullBackup';
    case SnapshotIncremental     = 'SnapshotIncremental';
    case SnapshotSettingsUpdate  = 'SnapshotSettingsUpdate';
    case SnapshotZipBuild        = 'SnapshotZipBuild';
    case SnapshotZipExpire       = 'SnapshotZipExpire';
    case SnapshotZipDownload     = 'SnapshotZipDownload';

    // ── Cloud Storage Actions ───────────────────────────────
    case CloudStorageUpload        = 'CloudStorageUpload';
    case CloudStorageDelete        = 'CloudStorageDelete';
    case CloudStorageRotation      = 'CloudStorageRotation';
    case CloudStorageAccountAdd    = 'CloudStorageAccountAdd';
    case CloudStorageAccountRemove = 'CloudStorageAccountRemove';

    // ── Info Map ────────────────────────────────────────────

    /**
     * @return array<string, EnumInfo>
     */
    private static function infoMap(): array
    {
        return [
            // Core plugin actions
            self::Upload->value           => new EnumInfo(
                label: 'Plugin uploaded',
                details: 'A plugin ZIP was uploaded and installed.',
            ),
            self::UploadActive->value     => new EnumInfo(
                label: 'Active plugin uploaded',
                details: 'An already-active plugin was re-uploaded.',
            ),
            self::UploadInitiated->value  => new EnumInfo(
                label: 'Upload initiated',
                details: 'Upload process started but not yet completed.',
            ),
            self::Enable->value           => new EnumInfo(
                label: 'Plugin enabled',
            ),
            self::Disable->value          => new EnumInfo(
                label: 'Plugin disabled',
            ),
            self::Delete->value           => new EnumInfo(
                label: 'Plugin deleted',
            ),
            self::FileReplace->value      => new EnumInfo(
                label: 'File replaced',
                details: 'A single file was replaced within the plugin.',
            ),
            self::FileDelete->value       => new EnumInfo(
                label: 'File deleted',
                details: 'A single file was removed from the plugin.',
            ),
            self::Sync->value             => new EnumInfo(
                label: 'Sync executed',
                details: 'Plugin files synced to remote agents.',
            ),
            self::SyncDelete->value       => new EnumInfo(
                label: 'Sync delete executed',
                details: 'Plugin files removed from remote agents.',
            ),

            // Post/content actions
            self::PostCreate->value       => new EnumInfo(
                label: 'Post created',
            ),
            self::PostUpdate->value       => new EnumInfo(
                label: 'Post updated',
            ),
            self::CategoryCreate->value   => new EnumInfo(
                label: 'Category created',
            ),
            self::MediaUpload->value      => new EnumInfo(
                label: 'Media uploaded',
            ),

            // Auth
            self::AuthFailed->value       => new EnumInfo(
                label: 'Authentication failed',
                details: 'An incoming API request failed authentication.',
            ),

            // Export actions
            self::ExportSelf->value       => new EnumInfo(
                label: 'Self-export executed',
                details: 'Plugin exported its own data.',
            ),
            self::ExportPlugin->value     => new EnumInfo(
                label: 'Plugin export executed',
                details: 'A managed plugin was exported.',
            ),

            // Plugin backup actions
            self::PluginBackup->value        => new EnumInfo(
                label: 'Plugin backup created',
            ),
            self::PluginBackupRestore->value => new EnumInfo(
                label: 'Plugin backup restored',
            ),
            self::PluginBackupDelete->value  => new EnumInfo(
                label: 'Plugin backup deleted',
            ),

            // Agent actions
            self::AgentAdd->value           => new EnumInfo(
                label: 'Agent added',
                details: 'A remote agent was registered.',
            ),
            self::AgentRemove->value        => new EnumInfo(
                label: 'Agent removed',
            ),
            self::AgentTest->value          => new EnumInfo(
                label: 'Agent connection tested',
            ),
            self::AgentSync->value          => new EnumInfo(
                label: 'Agent sync executed',
            ),
            self::AgentApiError->value      => new EnumInfo(
                label: 'Agent API error',
                details: 'Communication with a remote agent failed.',
            ),

            // Snapshot actions
            self::SnapshotCreate->value          => new EnumInfo(
                label: 'Snapshot created',
            ),
            self::SnapshotRestore->value         => new EnumInfo(
                label: 'Snapshot restored',
            ),
            self::SnapshotDelete->value          => new EnumInfo(
                label: 'Snapshot deleted',
            ),
            self::SnapshotExport->value          => new EnumInfo(
                label: 'Snapshot exported',
            ),
            self::SnapshotImport->value          => new EnumInfo(
                label: 'Snapshot imported',
            ),
            self::SnapshotCleanup->value         => new EnumInfo(
                label: 'Snapshot cleanup executed',
                details: 'Old or expired snapshots were removed.',
            ),
            self::SnapshotFullBackup->value      => new EnumInfo(
                label: 'Full snapshot backup created',
            ),
            self::SnapshotIncremental->value     => new EnumInfo(
                label: 'Incremental snapshot created',
            ),
            self::SnapshotSettingsUpdate->value  => new EnumInfo(
                label: 'Snapshot settings updated',
            ),
            self::SnapshotZipBuild->value        => new EnumInfo(
                label: 'Snapshot ZIP built',
            ),
            self::SnapshotZipExpire->value       => new EnumInfo(
                label: 'Snapshot ZIP expired',
                details: 'A cached ZIP file exceeded its TTL and was removed.',
            ),
            self::SnapshotZipDownload->value     => new EnumInfo(
                label: 'Snapshot ZIP downloaded',
            ),

            // Cloud storage actions
            self::CloudStorageUpload->value        => new EnumInfo(
                label: 'Cloud storage upload',
                details: 'A backup was uploaded to cloud storage.',
            ),
            self::CloudStorageDelete->value        => new EnumInfo(
                label: 'Cloud storage file deleted',
            ),
            self::CloudStorageRotation->value      => new EnumInfo(
                label: 'Cloud storage rotation executed',
                details: 'Old backups were rotated out per retention policy.',
            ),
            self::CloudStorageAccountAdd->value    => new EnumInfo(
                label: 'Cloud storage account added',
            ),
            self::CloudStorageAccountRemove->value => new EnumInfo(
                label: 'Cloud storage account removed',
            ),
        ];
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

    // ── Domain Helpers ──────────────────────────────────────

    public function isSnapshot(): bool
    {
        return str_starts_with($this->value, 'Snapshot');
    }

    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'Agent');
    }

    public function isCloudStorage(): bool
    {
        return str_starts_with($this->value, 'CloudStorage');
    }

    public function isLifecycle(): bool
    {
        return $this->isAnyOf(self::Enable, self::Disable, self::Delete);
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
use PluginName\Enums\ActionType;

$action = ActionType::SnapshotZipExpire;

// Label via info delegation
$message = $action->label();
// → "Snapshot ZIP expired"

// Full info with details
$info = $action->info();
// → EnumInfo { label: "Snapshot ZIP expired", details: "A cached ZIP file exceeded its TTL..." }

// Domain helpers still work
$isSnapshotRelated = $action->isSnapshot();  // true
$isLifecycle = $action->isLifecycle();        // false
```

---

## Why This Works for Large Enums

| Concern | Match/Switch Approach | Info-Object Approach |
|---------|----------------------|---------------------|
| Adding a new case | Update N separate match blocks | Add 1 entry to `infoMap()` |
| Adding a new metadata field | Add entire new method with 40+ branches | Add field to `EnumInfo`, update entries |
| Auditing completeness | Check N methods × M cases | Check 1 map has M entries |
| Lines of code (40 cases × 2 fields) | ~160 lines across 2 methods | ~120 lines in 1 map |

---

## Cross-References

- [02-enum-info-object-pattern.md](02-enum-info-object-pattern.md) — the pattern specification
- [03-self-update-status-enum.md](03-self-update-status-enum.md) — first reference implementation (17 cases)
- [01-enum-architecture.md](01-enum-architecture.md) — core enum rules and comparison methods

---

*Second reference implementation — large enum (40+ cases) with info-object pattern.*
