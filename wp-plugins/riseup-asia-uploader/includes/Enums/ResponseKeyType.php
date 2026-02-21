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
    case FileSize  = 'fileSize';
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
    case Posts          = 'posts';
    case Categories    = 'categories';
    case Category      = 'category';
    case Export        = 'export';
    case Incrementals  = 'incrementals';
    case TotalSize     = 'totalSize';
    case Applied       = 'applied';
    case Folder        = 'folder';

    /** Snapshot-domain keys. */
    case SnapshotId      = 'snapshotId';
    case Sequence        = 'sequence';
    case FolderName      = 'folderName';
    case TablesChanged   = 'tablesChanged';
    case TotalRows       = 'totalRows';
    case TotalNewRows    = 'totalNewRows';
    case ZipSize         = 'zipSize';
    case BackupId        = 'backupId';
    case ZipFailed       = 'zipFailed';
    case SkipAudit       = 'skipAudit';
    case TablesRestored  = 'tablesRestored';

    /** Cleanup-pipeline keys. */
    case DeletedByPolicy = 'deletedByPolicy';
    case DeletedOrphans  = 'deletedOrphans';
    case DeletedFailed   = 'deletedFailed';
    case SpaceFreedBytes = 'spaceFreedBytes';
    case Retention       = 'retention';
    case Orphans         = 'orphans';
    case Stuck           = 'stuck';
    case DryRun          = 'dryRun';
    case BytesFreed      = 'bytesFreed';
    case Deleted         = 'deleted';
    case Cleaned         = 'cleaned';

    /** Plugin lifecycle keys. */
    case Activated       = 'activated';
    case PluginSlug      = 'pluginSlug';
    case IsUpdate        = 'isUpdate';
    case IsSelfUpdate    = 'isSelfUpdate';
    case PluginVersion   = 'pluginVersion';
    case ActivationError = 'activationError';
    case Inventory       = 'inventory';

    /** Log/diagnostic keys. */
    case ErrorLog      = 'errorLog';
    case FullLog       = 'fullLog';
    case StacktraceLog = 'stacktraceLog';
    case Exists        = 'exists';
    case Content       = 'content';
    case Truncated     = 'truncated';
    case Lines         = 'lines';
    case TotalLines    = 'totalLines';

    /** Internal/domain-specific keys. */
    case Ids            = 'ids';
    case TotalSnapshots = 'totalSnapshots';
    case TotalSizeBytes = 'totalSizeBytes';
    case TempFile       = 'tempFile';
    case Stmt           = 'stmt';
    case Columns        = 'columns';

    /** Temporal keys. */
    case CreatedAt = 'createdAt';
    case UpdatedAt = 'updatedAt';

    /** API response keys — analysis and dependency. */
    case ParentTable    = 'parentTable';
    case ChildTable     = 'childTable';
    case FkColumn       = 'fkColumn';
    case RefColumn      = 'refColumn';
    case SeedOrder      = 'seedOrder';
    case TableCount     = 'tableCount';
    case DepCount       = 'depCount';
    case NewRows        = 'newRows';
    case PluginDetails  = 'pluginDetails';
    case IncludedIds    = 'includedIds';
    case IncrementalCount = 'incrementalCount';

    /** API response keys — detection and provider. */
    case DetectionMethod = 'detectionMethod';
    case SqliteVersion   = 'sqliteVersion';
    case IsCore          = 'isCore';

    /** API response keys — scheduler. */
    case ScheduleEnabled      = 'scheduleEnabled';
    case NextScheduledSnapshot = 'nextScheduledSnapshot';
    case NextCleanup           = 'nextCleanup';
    case RetentionType         = 'retentionType';
    case RetentionDays         = 'retentionDays';
    case RetentionCount        = 'retentionCount';
    case SnapshotType          = 'snapshotType';

    /** API response keys — error enrichment. */
    case ErrorCategory = 'errorCategory';
    case LogHint       = 'logHint';

    /** API response keys — sync. */
    case FilesUpdated = 'filesUpdated';
    case FilesDeleted = 'filesDeleted';
    case FilesIgnored = 'filesIgnored';
    case IgnoredFiles = 'ignoredFiles';

    /** API response keys — export and plugin. */
    case PluginZip   = 'pluginZip';
    case ResolvedUrl = 'resolvedUrl';
    case TraceLines  = 'traceLines';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
