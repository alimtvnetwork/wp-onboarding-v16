<?php
/**
 * ActionType — Transaction logging action identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ActionType: string
{
    // Core plugin actions
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

    // Post/content actions
    case PostCreate       = 'PostCreate';
    case PostUpdate       = 'PostUpdate';
    case CategoryCreate   = 'CategoryCreate';
    case MediaUpload      = 'MediaUpload';

    // Auth
    case AuthFailed       = 'AuthFailed';

    // Export actions
    case ExportSelf       = 'ExportSelf';
    case ExportPlugin     = 'ExportPlugin';

    // Plugin backup actions
    case PluginBackup        = 'PluginBackup';
    case PluginBackupRestore = 'PluginBackupRestore';
    case PluginBackupDelete  = 'PluginBackupDelete';

    // Agent actions
    case AgentAdd           = 'AgentAdd';
    case AgentRemove        = 'AgentRemove';
    case AgentTest          = 'AgentTest';
    case AgentSync          = 'AgentSync';
    case AgentApiError      = 'AgentApiError';

    // Snapshot actions
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

    // Cloud storage actions
    case CloudStorageUpload        = 'CloudStorageUpload';
    case CloudStorageDelete        = 'CloudStorageDelete';
    case CloudStorageRotation      = 'CloudStorageRotation';
    case CloudStorageAccountAdd    = 'CloudStorageAccountAdd';
    case CloudStorageAccountRemove = 'CloudStorageAccountRemove';

    // ── Standard Comparison Methods ─────────────────────────────────

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    // ── Per-Case is*() Helpers ──────────────────────────────────────

    // Core plugin actions
    public function isUpload(): bool          { return $this->isEqual(self::Upload); }
    public function isUploadActive(): bool     { return $this->isEqual(self::UploadActive); }
    public function isUploadInitiated(): bool  { return $this->isEqual(self::UploadInitiated); }
    public function isEnable(): bool           { return $this->isEqual(self::Enable); }
    public function isDisable(): bool          { return $this->isEqual(self::Disable); }
    public function isDelete(): bool           { return $this->isEqual(self::Delete); }
    public function isFileReplace(): bool      { return $this->isEqual(self::FileReplace); }
    public function isFileDelete(): bool       { return $this->isEqual(self::FileDelete); }
    public function isSync(): bool             { return $this->isEqual(self::Sync); }
    public function isSyncDelete(): bool       { return $this->isEqual(self::SyncDelete); }

    // Post/content actions
    public function isPostCreate(): bool       { return $this->isEqual(self::PostCreate); }
    public function isPostUpdate(): bool       { return $this->isEqual(self::PostUpdate); }
    public function isCategoryCreate(): bool   { return $this->isEqual(self::CategoryCreate); }
    public function isMediaUpload(): bool      { return $this->isEqual(self::MediaUpload); }

    // Auth
    public function isAuthFailed(): bool       { return $this->isEqual(self::AuthFailed); }

    // Export actions
    public function isExportSelf(): bool       { return $this->isEqual(self::ExportSelf); }
    public function isExportPlugin(): bool     { return $this->isEqual(self::ExportPlugin); }

    // Plugin backup actions
    public function isPluginBackup(): bool        { return $this->isEqual(self::PluginBackup); }
    public function isPluginBackupRestore(): bool  { return $this->isEqual(self::PluginBackupRestore); }
    public function isPluginBackupDelete(): bool   { return $this->isEqual(self::PluginBackupDelete); }

    // Agent actions
    public function isAgentAdd(): bool         { return $this->isEqual(self::AgentAdd); }
    public function isAgentRemove(): bool      { return $this->isEqual(self::AgentRemove); }
    public function isAgentTest(): bool        { return $this->isEqual(self::AgentTest); }
    public function isAgentSync(): bool        { return $this->isEqual(self::AgentSync); }
    public function isAgentApiError(): bool    { return $this->isEqual(self::AgentApiError); }

    // Snapshot actions
    public function isSnapshotCreate(): bool         { return $this->isEqual(self::SnapshotCreate); }
    public function isSnapshotRestore(): bool        { return $this->isEqual(self::SnapshotRestore); }
    public function isSnapshotDelete(): bool         { return $this->isEqual(self::SnapshotDelete); }
    public function isSnapshotExport(): bool         { return $this->isEqual(self::SnapshotExport); }
    public function isSnapshotImport(): bool         { return $this->isEqual(self::SnapshotImport); }
    public function isSnapshotCleanup(): bool        { return $this->isEqual(self::SnapshotCleanup); }
    public function isSnapshotFullBackup(): bool     { return $this->isEqual(self::SnapshotFullBackup); }
    public function isSnapshotIncremental(): bool    { return $this->isEqual(self::SnapshotIncremental); }
    public function isSnapshotSettingsUpdate(): bool { return $this->isEqual(self::SnapshotSettingsUpdate); }
    public function isSnapshotZipBuild(): bool       { return $this->isEqual(self::SnapshotZipBuild); }
    public function isSnapshotZipExpire(): bool      { return $this->isEqual(self::SnapshotZipExpire); }
    public function isSnapshotZipDownload(): bool    { return $this->isEqual(self::SnapshotZipDownload); }

    // Cloud storage actions
    public function isCloudStorageUpload(): bool        { return $this->isEqual(self::CloudStorageUpload); }
    public function isCloudStorageDelete(): bool        { return $this->isEqual(self::CloudStorageDelete); }
    public function isCloudStorageRotation(): bool      { return $this->isEqual(self::CloudStorageRotation); }
    public function isCloudStorageAccountAdd(): bool    { return $this->isEqual(self::CloudStorageAccountAdd); }
    public function isCloudStorageAccountRemove(): bool { return $this->isEqual(self::CloudStorageAccountRemove); }

    // ── Group is*() Helpers ─────────────────────────────────────────

    public function isSnapshot(): bool     { return str_starts_with($this->value, 'Snapshot'); }
    public function isAgent(): bool        { return str_starts_with($this->value, 'Agent'); }
    public function isCloudStorage(): bool { return str_starts_with($this->value, 'CloudStorage'); }

    public function isLifecycle(): bool
    {
        return $this->isAnyOf(self::Enable, self::Disable, self::Delete);
    }

    public function isContent(): bool
    {
        return $this->isAnyOf(self::PostCreate, self::PostUpdate, self::CategoryCreate, self::MediaUpload);
    }

    public function isExport(): bool
    {
        return $this->isAnyOf(self::ExportSelf, self::ExportPlugin);
    }

    public function isPluginBackupAction(): bool
    {
        return $this->isAnyOf(self::PluginBackup, self::PluginBackupRestore, self::PluginBackupDelete);
    }

    // ── Metadata ────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Upload                   => 'Upload',
            self::UploadActive             => 'Upload (Active)',
            self::UploadInitiated          => 'Upload Initiated',
            self::Enable                   => 'Enable',
            self::Disable                  => 'Disable',
            self::Delete                   => 'Delete',
            self::FileReplace              => 'File Replace',
            self::FileDelete               => 'File Delete',
            self::Sync                     => 'Sync',
            self::SyncDelete               => 'Sync Delete',
            self::PostCreate               => 'Post Create',
            self::PostUpdate               => 'Post Update',
            self::CategoryCreate           => 'Category Create',
            self::MediaUpload              => 'Media Upload',
            self::AuthFailed               => 'Auth Failed',
            self::ExportSelf               => 'Export Self',
            self::ExportPlugin             => 'Export Plugin',
            self::PluginBackup             => 'Plugin Backup',
            self::PluginBackupRestore      => 'Plugin Backup Restore',
            self::PluginBackupDelete       => 'Plugin Backup Delete',
            self::AgentAdd                 => 'Agent Add',
            self::AgentRemove              => 'Agent Remove',
            self::AgentTest                => 'Agent Test',
            self::AgentSync                => 'Agent Sync',
            self::AgentApiError            => 'Agent API Error',
            self::SnapshotCreate           => 'Snapshot Create',
            self::SnapshotRestore          => 'Snapshot Restore',
            self::SnapshotDelete           => 'Snapshot Delete',
            self::SnapshotExport           => 'Snapshot Export',
            self::SnapshotImport           => 'Snapshot Import',
            self::SnapshotCleanup          => 'Snapshot Cleanup',
            self::SnapshotFullBackup       => 'Snapshot Full Backup',
            self::SnapshotIncremental      => 'Snapshot Incremental',
            self::SnapshotSettingsUpdate   => 'Snapshot Settings Update',
            self::SnapshotZipBuild         => 'Snapshot ZIP Build',
            self::SnapshotZipExpire        => 'Snapshot ZIP Expire',
            self::SnapshotZipDownload      => 'Snapshot ZIP Download',
            self::CloudStorageUpload       => 'Cloud Storage Upload',
            self::CloudStorageDelete       => 'Cloud Storage Delete',
            self::CloudStorageRotation     => 'Cloud Storage Rotation',
            self::CloudStorageAccountAdd   => 'Cloud Storage Account Add',
            self::CloudStorageAccountRemove => 'Cloud Storage Account Remove',
        };
    }
}
