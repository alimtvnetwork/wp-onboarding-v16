<?php
/**
 * ResponseKeyType — Standardized response array keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

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
    case Total        = 'total';
    case Agents       = 'agents';
    case Actions      = 'actions';
    case Logs         = 'logs';
    case Snapshots    = 'snapshots';
    case Sql          = 'sql';
    case Params       = 'params';
    case Sets         = 'sets';
    case Plugins      = 'plugins';
    case Tables       = 'tables';
    case Settings     = 'settings';
    case Providers    = 'providers';
    case Dependencies = 'dependencies';

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
    case ZipPath         = 'zipPath';
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
    case PluginFile      = 'pluginFile';

    /** General-purpose entity keys. */
    case Slug  = 'slug';
    case Title = 'title';
    case Type  = 'type';

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

    /** Analysis and dependency keys. */
    case ParentTable      = 'parentTable';
    case ChildTable       = 'childTable';
    case FkColumn         = 'fkColumn';
    case RefColumn        = 'refColumn';
    case SeedOrder        = 'seedOrder';
    case TableCount       = 'tableCount';
    case DepCount         = 'depCount';
    case NewRows          = 'newRows';
    case PluginDetails    = 'pluginDetails';
    case IncludedIds      = 'includedIds';
    case IncrementalCount = 'incrementalCount';

    /** Detection and provider keys. */
    case DetectionMethod = 'detectionMethod';
    case SqliteVersion   = 'sqliteVersion';
    case IsCore          = 'isCore';

    /** Scheduler keys. */
    case ScheduleEnabled       = 'scheduleEnabled';
    case NextScheduledSnapshot = 'nextScheduledSnapshot';
    case NextCleanup           = 'nextCleanup';
    case RetentionType         = 'retentionType';
    case RetentionDays         = 'retentionDays';
    case RetentionCount        = 'retentionCount';
    case SnapshotType          = 'snapshotType';

    /** Error enrichment keys. */
    case ErrorCategory = 'errorCategory';
    case LogHint       = 'logHint';

    /** Sync keys. */
    case FilesUpdated = 'filesUpdated';
    case FilesDeleted = 'filesDeleted';
    case FilesIgnored = 'filesIgnored';
    case IgnoredFiles = 'ignoredFiles';

    /** Export and plugin keys. */
    case PluginZip   = 'pluginZip';
    case ResolvedUrl = 'resolvedUrl';
    case TraceLines  = 'traceLines';

    /** Snapshot progress and worker keys. */
    case CompletedAt         = 'completedAt';
    case ExportedAt          = 'exportedAt';
    case Format               = 'format';
    case FormatVersion       = 'formatVersion';
    case JobId               = 'jobId';
    case TotalTables         = 'totalTables';
    case TablesExported      = 'tablesExported';
    case PoolSize            = 'poolSize';
    case TotalBatches        = 'totalBatches';
    case CurrentBatch        = 'currentBatch';
    case TableProgress       = 'tableProgress';
    case IncrementalsApplied = 'incrementalsApplied';
    case SkippedMaster       = 'skippedMaster';
    case ExportedTables      = 'exportedTables';
    case SnapshotDir         = 'snapshotDir';
    case DirName             = 'dirName';
    case RowCount            = 'rowCount';

    /** Cron and audit keys. */
    case TriggeredBy = 'triggeredBy';
    case AuditData   = 'auditData';
    case LogDataKey  = 'logData';

    /** Manifest and import metadata keys. */
    case OriginalId        = 'originalId';
    case OriginalCreatedAt = 'originalCreatedAt';
    case SourceSite        = 'sourceSite';
    case OriginalTitle     = 'originalTitle';
    case OriginalType      = 'originalType';
    case WpVersion         = 'wpVersion';
    case PhpVersion        = 'phpVersion';
    case MysqlVersion      = 'mysqlVersion';
    case SiteUrl           = 'siteUrl';
    case DbPrefix          = 'dbPrefix';
    case PluginCount       = 'pluginCount';
    case DurationMs        = 'durationMs';
    case TableCounts       = 'tableCounts';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
