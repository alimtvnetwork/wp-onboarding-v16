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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isSnapshot(): bool { return str_starts_with($this->value, 'Snapshot'); }
    public function isAgent(): bool    { return str_starts_with($this->value, 'Agent'); }
    

    public function isLifecycle(): bool
    {
        return $this->isAnyOf(self::Enable, self::Disable, self::Delete);
    }
}
