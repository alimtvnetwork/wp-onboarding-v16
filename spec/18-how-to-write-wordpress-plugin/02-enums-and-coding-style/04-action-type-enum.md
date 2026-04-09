# ActionType — Info-Object Reference Implementation

**Version:** 1.1.0
**Updated:** 2026-04-09

> **Purpose:** Second reference implementation of the info-object pattern, demonstrating a large enum (40+ cases) with per-case and group helpers.

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
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [
            self::Upload->value           => new EnumInfo(label: 'Plugin uploaded'),
            self::UploadActive->value     => new EnumInfo(label: 'Active plugin uploaded'),
            self::UploadInitiated->value  => new EnumInfo(label: 'Upload initiated'),
            self::Enable->value           => new EnumInfo(label: 'Plugin enabled'),
            self::Disable->value          => new EnumInfo(label: 'Plugin disabled'),
            self::Delete->value           => new EnumInfo(label: 'Plugin deleted'),
            self::FileReplace->value      => new EnumInfo(label: 'File replaced'),
            self::FileDelete->value       => new EnumInfo(label: 'File deleted'),
            self::Sync->value             => new EnumInfo(label: 'Sync executed'),
            self::SyncDelete->value       => new EnumInfo(label: 'Sync delete executed'),
            self::PostCreate->value       => new EnumInfo(label: 'Post created'),
            self::PostUpdate->value       => new EnumInfo(label: 'Post updated'),
            self::CategoryCreate->value   => new EnumInfo(label: 'Category created'),
            self::MediaUpload->value      => new EnumInfo(label: 'Media uploaded'),
            self::AuthFailed->value       => new EnumInfo(label: 'Authentication failed'),
            self::ExportSelf->value       => new EnumInfo(label: 'Self-export executed'),
            self::ExportPlugin->value     => new EnumInfo(label: 'Plugin export executed'),
            self::PluginBackup->value        => new EnumInfo(label: 'Plugin backup created'),
            self::PluginBackupRestore->value => new EnumInfo(label: 'Plugin backup restored'),
            self::PluginBackupDelete->value  => new EnumInfo(label: 'Plugin backup deleted'),
            self::AgentAdd->value           => new EnumInfo(label: 'Agent added'),
            self::AgentRemove->value        => new EnumInfo(label: 'Agent removed'),
            self::AgentTest->value          => new EnumInfo(label: 'Agent connection tested'),
            self::AgentSync->value          => new EnumInfo(label: 'Agent sync executed'),
            self::AgentApiError->value      => new EnumInfo(label: 'Agent API error'),
            self::SnapshotCreate->value          => new EnumInfo(label: 'Snapshot created'),
            self::SnapshotRestore->value         => new EnumInfo(label: 'Snapshot restored'),
            self::SnapshotDelete->value          => new EnumInfo(label: 'Snapshot deleted'),
            self::SnapshotExport->value          => new EnumInfo(label: 'Snapshot exported'),
            self::SnapshotImport->value          => new EnumInfo(label: 'Snapshot imported'),
            self::SnapshotCleanup->value         => new EnumInfo(label: 'Snapshot cleanup executed'),
            self::SnapshotFullBackup->value      => new EnumInfo(label: 'Full snapshot backup created'),
            self::SnapshotIncremental->value     => new EnumInfo(label: 'Incremental snapshot created'),
            self::SnapshotSettingsUpdate->value  => new EnumInfo(label: 'Snapshot settings updated'),
            self::SnapshotZipBuild->value        => new EnumInfo(label: 'Snapshot ZIP built'),
            self::SnapshotZipExpire->value       => new EnumInfo(label: 'Snapshot ZIP expired'),
            self::SnapshotZipDownload->value     => new EnumInfo(label: 'Snapshot ZIP downloaded'),
            self::CloudStorageUpload->value        => new EnumInfo(label: 'Cloud storage upload'),
            self::CloudStorageDelete->value        => new EnumInfo(label: 'Cloud storage file deleted'),
            self::CloudStorageRotation->value      => new EnumInfo(label: 'Cloud storage rotation executed'),
            self::CloudStorageAccountAdd->value    => new EnumInfo(label: 'Cloud storage account added'),
            self::CloudStorageAccountRemove->value => new EnumInfo(label: 'Cloud storage account removed'),
        ];

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

    public function isUpload(): bool           { return $this->isEqual(self::Upload); }
    public function isUploadActive(): bool     { return $this->isEqual(self::UploadActive); }
    public function isUploadInitiated(): bool  { return $this->isEqual(self::UploadInitiated); }
    public function isEnable(): bool           { return $this->isEqual(self::Enable); }
    public function isDisable(): bool          { return $this->isEqual(self::Disable); }
    public function isDelete(): bool           { return $this->isEqual(self::Delete); }
    public function isFileReplace(): bool      { return $this->isEqual(self::FileReplace); }
    public function isFileDelete(): bool       { return $this->isEqual(self::FileDelete); }
    public function isSync(): bool             { return $this->isEqual(self::Sync); }
    public function isSyncDelete(): bool       { return $this->isEqual(self::SyncDelete); }
    public function isPostCreate(): bool       { return $this->isEqual(self::PostCreate); }
    public function isPostUpdate(): bool       { return $this->isEqual(self::PostUpdate); }
    public function isCategoryCreate(): bool   { return $this->isEqual(self::CategoryCreate); }
    public function isMediaUpload(): bool      { return $this->isEqual(self::MediaUpload); }
    public function isAuthFailed(): bool       { return $this->isEqual(self::AuthFailed); }
    public function isExportSelf(): bool       { return $this->isEqual(self::ExportSelf); }
    public function isExportPlugin(): bool     { return $this->isEqual(self::ExportPlugin); }
    public function isPluginBackup(): bool        { return $this->isEqual(self::PluginBackup); }
    public function isPluginBackupRestore(): bool { return $this->isEqual(self::PluginBackupRestore); }
    public function isPluginBackupDelete(): bool  { return $this->isEqual(self::PluginBackupDelete); }
    public function isAgentAdd(): bool           { return $this->isEqual(self::AgentAdd); }
    public function isAgentRemove(): bool        { return $this->isEqual(self::AgentRemove); }
    public function isAgentTest(): bool          { return $this->isEqual(self::AgentTest); }
    public function isAgentSync(): bool          { return $this->isEqual(self::AgentSync); }
    public function isAgentApiError(): bool      { return $this->isEqual(self::AgentApiError); }
    public function isSnapshotCreate(): bool          { return $this->isEqual(self::SnapshotCreate); }
    public function isSnapshotRestore(): bool         { return $this->isEqual(self::SnapshotRestore); }
    public function isSnapshotDelete(): bool          { return $this->isEqual(self::SnapshotDelete); }
    public function isSnapshotExport(): bool          { return $this->isEqual(self::SnapshotExport); }
    public function isSnapshotImport(): bool          { return $this->isEqual(self::SnapshotImport); }
    public function isSnapshotCleanup(): bool         { return $this->isEqual(self::SnapshotCleanup); }
    public function isSnapshotFullBackup(): bool      { return $this->isEqual(self::SnapshotFullBackup); }
    public function isSnapshotIncremental(): bool     { return $this->isEqual(self::SnapshotIncremental); }
    public function isSnapshotSettingsUpdate(): bool  { return $this->isEqual(self::SnapshotSettingsUpdate); }
    public function isSnapshotZipBuild(): bool        { return $this->isEqual(self::SnapshotZipBuild); }
    public function isSnapshotZipExpire(): bool       { return $this->isEqual(self::SnapshotZipExpire); }
    public function isSnapshotZipDownload(): bool     { return $this->isEqual(self::SnapshotZipDownload); }
    public function isCloudStorageUpload(): bool        { return $this->isEqual(self::CloudStorageUpload); }
    public function isCloudStorageDelete(): bool        { return $this->isEqual(self::CloudStorageDelete); }
    public function isCloudStorageRotation(): bool      { return $this->isEqual(self::CloudStorageRotation); }
    public function isCloudStorageAccountAdd(): bool    { return $this->isEqual(self::CloudStorageAccountAdd); }
    public function isCloudStorageAccountRemove(): bool { return $this->isEqual(self::CloudStorageAccountRemove); }

    // ── Group Helpers ───────────────────────────────────────

    public function isSnapshot(): bool     { return str_starts_with($this->value, 'Snapshot'); }
    public function isAgent(): bool        { return str_starts_with($this->value, 'Agent'); }
    public function isCloudStorage(): bool { return str_starts_with($this->value, 'CloudStorage'); }

    public function isLifecycle(): bool
    {
        return $this->isAnyOf(self::Enable, self::Disable, self::Delete);
    }

    public function isExport(): bool
    {
        return $this->isAnyOf(self::ExportSelf, self::ExportPlugin);
    }

    public function isPluginBackupAction(): bool
    {
        return $this->isAnyOf(self::PluginBackup, self::PluginBackupRestore, self::PluginBackupDelete);
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

// Per-case helper
$action->isSnapshotZipExpire();  // true
$action->isUpload();             // false

// Group helper
$action->isSnapshot();           // true
$action->isLifecycle();          // false

// Label via info delegation
$action->label();
// → "Snapshot ZIP expired"
```

---

## Cross-References

- [02-enum-info-object-pattern.md](02-enum-info-object-pattern.md) — the pattern specification
- [03-self-update-status-enum.md](03-self-update-status-enum.md) — first reference implementation (17 cases)
- [01-enum-architecture.md](01-enum-architecture.md) — core enum rules and comparison methods

---

*Second reference implementation — large enum (40+ cases) with info-object pattern.*
