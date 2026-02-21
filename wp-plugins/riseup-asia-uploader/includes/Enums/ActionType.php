<?php
/**
 * ActionType — Transaction logging action identifiers.
 *
 * Backed enum replacing all ACTION_* define() constants.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction logging action types.
 */
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

    // Update actions
    case UpdateCheck      = 'UpdateCheck';
    case UpdateResolve    = 'UpdateResolve';
    case UpdateDownload   = 'UpdateDownload';
    case UpdateInstall    = 'UpdateInstall';

    // Agent actions
    case AgentAdd           = 'AgentAdd';
    case AgentRemove        = 'AgentRemove';
    case AgentTest          = 'AgentTest';
    case AgentSync          = 'AgentSync';
    case AgentPluginEnable  = 'AgentPluginEnable';
    case AgentPluginDisable = 'AgentPluginDisable';
    case AgentPluginDelete  = 'AgentPluginDelete';
    case AgentPluginUpdate  = 'AgentPluginUpdate';
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
    case SnapshotRestorePerTable = 'SnapshotRestorePerTable';
    case SnapshotImportPerTable  = 'SnapshotImportPerTable';
    case SnapshotSettingsUpdate  = 'SnapshotSettingsUpdate';
    case SnapshotZipBuild        = 'SnapshotZipBuild';
    case SnapshotZipExpire       = 'SnapshotZipExpire';
    case SnapshotZipDownload     = 'SnapshotZipDownload';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** Check if this is a snapshot-related action. */
    public function isSnapshot(): bool
    {
        return str_starts_with($this->value, 'Snapshot');
    }

    /** Check if this is an agent-related action. */
    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'Agent');
    }

    /** Check if this is an update-related action. */
    public function isUpdate(): bool
    {
        return str_starts_with($this->value, 'Update');
    }

    /** Check if this is a plugin lifecycle action (enable/disable/delete). */
    public function isLifecycle(): bool
    {
        return $this->isAnyOf(self::Enable, self::Disable, self::Delete);
    }
}
