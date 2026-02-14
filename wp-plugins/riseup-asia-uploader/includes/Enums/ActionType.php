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
    case Upload           = 'upload';
    case UploadActive     = 'upload_active';
    case UploadInitiated  = 'upload_initiated';
    case Enable           = 'enable';
    case Disable          = 'disable';
    case Delete           = 'delete';
    case FileReplace      = 'file_replace';
    case FileDelete       = 'file_delete';
    case Sync             = 'sync';
    case SyncDelete       = 'sync_delete';

    // Post/content actions
    case PostCreate       = 'post_create';
    case PostUpdate       = 'post_update';
    case CategoryCreate   = 'category_create';
    case MediaUpload      = 'media_upload';

    // Auth
    case AuthFailed       = 'auth_failed';

    // Export actions
    case ExportSelf       = 'export_self';
    case ExportPlugin     = 'export_plugin';

    // Update actions
    case UpdateCheck      = 'update_check';
    case UpdateResolve    = 'update_resolve';
    case UpdateDownload   = 'update_download';
    case UpdateInstall    = 'update_install';

    // Agent actions
    case AgentAdd           = 'agent_add';
    case AgentRemove        = 'agent_remove';
    case AgentTest          = 'agent_test';
    case AgentSync          = 'agent_sync';
    case AgentPluginEnable  = 'agent_plugin_enable';
    case AgentPluginDisable = 'agent_plugin_disable';
    case AgentPluginDelete  = 'agent_plugin_delete';
    case AgentPluginUpdate  = 'agent_plugin_update';

    // Snapshot actions
    case SnapshotCreate          = 'snapshot_create';
    case SnapshotRestore         = 'snapshot_restore';
    case SnapshotDelete          = 'snapshot_delete';
    case SnapshotExport          = 'snapshot_export';
    case SnapshotImport          = 'snapshot_import';
    case SnapshotCleanup         = 'snapshot_cleanup';
    case SnapshotFullBackup      = 'snapshot_full_backup';
    case SnapshotIncremental     = 'snapshot_incremental';
    case SnapshotRestorePerTable = 'snapshot_restore_pertable';
    case SnapshotImportPerTable  = 'snapshot_import_pertable';
    case SnapshotZipBuild        = 'snapshot_zip_build';
    case SnapshotZipExpire       = 'snapshot_zip_expire';
    case SnapshotZipDownload     = 'snapshot_zip_download';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this is a snapshot-related action. */
    public function isSnapshot(): bool
    {
        return str_starts_with($this->value, 'snapshot_');
    }

    /** Check if this is an agent-related action. */
    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'agent_');
    }

    /** Check if this is an update-related action. */
    public function isUpdate(): bool
    {
        return str_starts_with($this->value, 'update_');
    }

    /** Check if this is a plugin lifecycle action (enable/disable/delete). */
    public function isLifecycle(): bool
    {
        return $this->isEqual(self::Enable) || $this->isEqual(self::Disable) || $this->isEqual(self::Delete);
    }
}
