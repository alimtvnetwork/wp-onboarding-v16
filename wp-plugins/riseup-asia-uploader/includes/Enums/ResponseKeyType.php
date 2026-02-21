<?php
/**
 * ResponseKeyType — Standardized response array keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Keys used in structured response arrays returned by services.
 */
enum ResponseKeyType: string
{
    /** Envelope keys — present in most response arrays. */
    case Success  = 'success';
    case Error    = 'error';
    case Message  = 'message';
    case Data     = 'data';
    case Code     = 'code';
    case Valid    = 'valid';
    case Errors   = 'errors';
    case Cached   = 'cached';
    case Phase    = 'phase';
    case Reason   = 'reason';

    /** Domain collection keys. */
    case Total     = 'total';
    case Agents    = 'agents';
    case Actions   = 'actions';
    case Logs      = 'logs';
    case Snapshots = 'snapshots';
    case Sql       = 'sql';
    case Params    = 'params';
    case Sets      = 'sets';
    case Plugins   = 'plugins';
    case Tables    = 'tables';

    /** File and size keys. */
    case Rows      = 'rows';
    case Bytes     = 'bytes';
    case Size      = 'size';
    case FileSize  = 'file_size';
    case Path      = 'path';
    case Filename  = 'filename';
    case Checksum  = 'checksum';
    case Duration  = 'duration';
    case Count     = 'count';
    case Files     = 'files';
    case Directory = 'directory';
    case Scope     = 'scope';
    case Exported  = 'exported';
    case Entry     = 'entry';
    case Computed  = 'computed';
    case Removed   = 'removed';

    /** Pagination keys. */
    case Limit  = 'limit';
    case Offset = 'offset';

    /** Domain entity keys. */
    case Posts        = 'posts';
    case Categories   = 'categories';
    case Category     = 'category';
    case Export       = 'export';
    case Incrementals = 'incrementals';
    case TotalSize    = 'total_size';
    case Applied      = 'applied';
    case Folder       = 'folder';

    /** Snapshot-domain keys. */
    case SnapshotId    = 'snapshot_id';
    case Sequence      = 'sequence';
    case FolderName    = 'folder_name';
    case TablesChanged = 'tables_changed';
    case TotalRows     = 'total_rows';
    case TotalNewRows  = 'total_new_rows';
    case ZipSize       = 'zip_size';
    case BackupId      = 'backup_id';
    case ZipFailed     = 'zip_failed';
    case SkipAudit     = 'skip_audit';
    case TablesRestored = 'tables_restored';

    /** Cleanup-pipeline keys. */
    case DeletedByPolicy = 'deleted_by_policy';
    case DeletedOrphans  = 'deleted_orphans';
    case DeletedFailed   = 'deleted_failed';
    case SpaceFreedBytes = 'space_freed_bytes';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
