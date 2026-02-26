<?php
/**
 * ResponseKeyType — Standardized response array keys (PascalCase values).
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
    case Success  = 'Success';
    case Error    = 'Error';
    case Message  = 'Message';
    case Data     = 'Data';
    case Code     = 'Code';
    case Valid    = 'Valid';
    case Errors   = 'Errors';
    case Cached   = 'Cached';
    case Phase    = 'Phase';
    case Reason   = 'Reason';

    /** Domain collection keys. */
    case Total        = 'Total';
    case Agents       = 'Agents';
    case Actions      = 'Actions';
    case Logs         = 'Logs';
    case Snapshots    = 'Snapshots';
    case Sql          = 'Sql';
    case Params       = 'Params';
    case Sets         = 'Sets';
    case Plugins      = 'Plugins';
    case Tables       = 'Tables';
    case Settings     = 'Settings';
    case Providers    = 'Providers';
    case Dependencies = 'Dependencies';

    /** File and size keys. */
    case Rows      = 'Rows';
    case Bytes     = 'Bytes';
    case Size      = 'Size';
    case FileSize  = 'FileSize';
    case Path      = 'Path';
    case Filename  = 'Filename';
    case Checksum  = 'Checksum';
    case Duration  = 'Duration';
    case Count     = 'Count';
    case Files     = 'Files';
    case Directory = 'Directory';
    case Scope     = 'Scope';
    case Exported  = 'Exported';
    case Entry     = 'Entry';
    case Computed  = 'Computed';
    case Removed   = 'Removed';

    /** Pagination keys. */
    case Limit  = 'Limit';
    case Offset = 'Offset';

    /** Domain entity keys. */
    case Posts          = 'Posts';
    case Categories    = 'Categories';
    case Category      = 'Category';
    case Export        = 'Export';
    case Incrementals  = 'Incrementals';
    case TotalSize     = 'TotalSize';
    case Applied       = 'Applied';
    case Folder        = 'Folder';

    /** Snapshot-domain keys. */
    case SnapshotId      = 'SnapshotId';
    case Sequence        = 'Sequence';
    case FolderName      = 'FolderName';
    case TablesChanged   = 'TablesChanged';
    case TotalRows       = 'TotalRows';
    case TotalNewRows    = 'TotalNewRows';
    case ZipPath         = 'ZipPath';
    case ZipSize         = 'ZipSize';
    case BackupId        = 'BackupId';
    case ZipFailed       = 'ZipFailed';
    case SkipAudit       = 'SkipAudit';
    case TablesRestored  = 'TablesRestored';

    /** Cleanup-pipeline keys. */
    case DeletedByPolicy = 'DeletedByPolicy';
    case DeletedOrphans  = 'DeletedOrphans';
    case DeletedFailed   = 'DeletedFailed';
    case SpaceFreedBytes = 'SpaceFreedBytes';
    case Retention       = 'Retention';
    case Orphans         = 'Orphans';
    case Stuck           = 'Stuck';
    case DryRun          = 'DryRun';
    case BytesFreed      = 'BytesFreed';
    case Deleted         = 'Deleted';
    case Cleaned         = 'Cleaned';

    /** Plugin lifecycle keys. */
    case Activated       = 'Activated';
    case PluginSlug      = 'PluginSlug';
    case IsUpdate        = 'IsUpdate';
    case IsSelfUpdate    = 'IsSelfUpdate';
    case PluginVersion   = 'PluginVersion';
    case ActivationError = 'ActivationError';
    case Inventory       = 'Inventory';
    case PluginFile      = 'PluginFile';

    /** General-purpose entity keys. */
    case Slug    = 'Slug';
    case Title   = 'Title';
    case Type    = 'Type';
    case Action  = 'Action';
    case Status  = 'Status';
    case Percent = 'Percent';
    case Plugin  = 'Plugin';

    /** Log/diagnostic keys. */
    case ErrorLog      = 'ErrorLog';
    case FullLog       = 'FullLog';
    case StacktraceLog = 'StacktraceLog';
    case Exists        = 'Exists';
    case Content       = 'Content';
    case Truncated     = 'Truncated';
    case Lines         = 'Lines';
    case TotalLines    = 'TotalLines';

    /** Internal/domain-specific keys. */
    case Ids            = 'Ids';
    case TotalSnapshots = 'TotalSnapshots';
    case TotalSizeBytes = 'TotalSizeBytes';
    case TempFile       = 'TempFile';
    case Stmt           = 'Stmt';
    case Columns        = 'Columns';

    /** Temporal keys. */
    case CreatedAt  = 'CreatedAt';
    case UpdatedAt  = 'UpdatedAt';
    case Timestamp  = 'Timestamp';

    /** Analysis and dependency keys. */
    case ParentTable      = 'ParentTable';
    case ChildTable       = 'ChildTable';
    case FkColumn         = 'FkColumn';
    case RefColumn        = 'RefColumn';
    case SeedOrder        = 'SeedOrder';
    case TableCount       = 'TableCount';
    case DepCount         = 'DepCount';
    case NewRows          = 'NewRows';
    case PluginDetails    = 'PluginDetails';
    case IncludedIds      = 'IncludedIds';
    case IncrementalCount = 'IncrementalCount';

    /** Detection and provider keys. */
    case DetectionMethod = 'DetectionMethod';
    case SqliteVersion   = 'SqliteVersion';
    case IsCore          = 'IsCore';

    /** Scheduler keys. */
    case ScheduleEnabled       = 'ScheduleEnabled';
    case NextScheduledSnapshot = 'NextScheduledSnapshot';
    case NextCleanup           = 'NextCleanup';
    case RetentionType         = 'RetentionType';
    case RetentionDays         = 'RetentionDays';
    case RetentionCount        = 'RetentionCount';
    case SnapshotType          = 'SnapshotType';

    /** Error enrichment keys. */
    case ErrorCategory = 'ErrorCategory';
    case LogHint       = 'LogHint';

    /** Sync keys. */
    case FilesUpdated = 'FilesUpdated';
    case FilesDeleted = 'FilesDeleted';
    case FilesIgnored = 'FilesIgnored';
    case IgnoredFiles = 'IgnoredFiles';

    /** Export and plugin keys. */
    case PluginZip   = 'PluginZip';
    case ResolvedUrl = 'ResolvedUrl';
    case TraceLines  = 'TraceLines';

    /** Snapshot progress and worker keys. */
    case CompletedAt         = 'CompletedAt';
    case ExportedAt          = 'ExportedAt';
    case Format              = 'Format';
    case FormatVersion       = 'FormatVersion';
    case JobId               = 'JobId';
    case TotalTables         = 'TotalTables';
    case TablesExported      = 'TablesExported';
    case PoolSize            = 'PoolSize';
    case TotalBatches        = 'TotalBatches';
    case CurrentBatch        = 'CurrentBatch';
    case TableProgress       = 'TableProgress';
    case IncrementalsApplied = 'IncrementalsApplied';
    case SkippedMaster       = 'SkippedMaster';
    case ExportedTables      = 'ExportedTables';
    case SnapshotDir         = 'SnapshotDir';
    case DirName             = 'DirName';
    case RowCount            = 'RowCount';

    /** Cron and audit keys. */
    case TriggeredBy = 'TriggeredBy';
    case AuditData   = 'AuditData';
    case LogDataKey  = 'LogData';

    /** Manifest and import metadata keys. */
    case OriginalId        = 'OriginalId';
    case OriginalCreatedAt = 'OriginalCreatedAt';
    case SourceSite        = 'SourceSite';
    case OriginalTitle     = 'OriginalTitle';
    case OriginalType      = 'OriginalType';
    case WpVersion         = 'WpVersion';
    case PhpVersion        = 'PhpVersion';
    case MysqlVersion      = 'MysqlVersion';
    case SiteUrl           = 'SiteUrl';
    case DbPrefix          = 'DbPrefix';
    case PluginCount       = 'PluginCount';
    case DurationMs        = 'DurationMs';
    case TableCounts       = 'TableCounts';

    /** Sync manifest keys. */
    case DownloadUrl = 'DownloadUrl';
    case FileCount   = 'FileCount';
    case GeneratedAt = 'GeneratedAt';
    case CacheStats  = 'CacheStats';
    case FromCache   = 'FromCache';

    /** Statistics keys. */
    case TotalTransactions = 'TotalTransactions';
    case ByAction          = 'ByAction';
    case ByStatus          = 'ByStatus';
    case Last24h           = 'Last24h';

    /** Backup option keys. */
    case IncludePlugins   = 'IncludePlugins';
    case PluginSelection  = 'PluginSelection';
    case Compression      = 'Compression';
    case Async            = 'Async';
    case Trigger          = 'Trigger';
    case MasterSnapshotId = 'MasterSnapshotId';
    case MasterDir        = 'MasterDir';
    case Confirm          = 'Confirm';
    case CreateBackup     = 'CreateBackup';
    case RequireBackup    = 'RequireBackup';
    case Mode             = 'Mode';

    /** Scheduler response keys. */
    case Frequency  = 'Frequency';
    case Time       = 'Time';
    case Day        = 'Day';
    case Scheduled  = 'Scheduled';
    case Trace      = 'Trace';
    case Options    = 'Options';

    /** Storage stats keys. */
    case TotalSizeFormatted = 'TotalSizeFormatted';
    case OldestTimestamp    = 'OldestTimestamp';
    case NewestTimestamp    = 'NewestTimestamp';
    case DiskFreeBytes      = 'DiskFreeBytes';
    case DiskFreeFormatted  = 'DiskFreeFormatted';
    case SnapshotsCount     = 'SnapshotsCount';
    case BytesFormatted     = 'BytesFormatted';

    /** Progress envelope keys. */
    case IsSuccess    = 'IsSuccess';
    case HasAnyErrors = 'HasAnyErrors';

    /** Cleanup detail keys. */
    case Details = 'Details';
    case Order   = 'Order';

    /** Internal passing keys. */
    case Graph      = 'Graph';
    case InDegree   = 'InDegree';
    case Manifest   = 'Manifest';
    case SqlitePath = 'SqlitePath';
    case RealPath   = 'RealPath';
    case FilePath   = 'FilePath';
    case PkColumn   = 'PkColumn';
    case TableName  = 'TableName';

    /** Provider and plugin info keys. */
    case Id           = 'Id';
    case Name         = 'Name';
    case Available    = 'Available';
    case Capabilities = 'Capabilities';
    case Version      = 'Version';
    case Author       = 'Author';
    case Description  = 'Description';
    case Active       = 'Active';
    case TotalFiles   = 'TotalFiles';
    case LastSeenId   = 'LastSeenId';
    case FileType     = 'FileType';
    case Provider     = 'Provider';
    case Snapshot     = 'Snapshot';
    case Source       = 'Source';

    /** Capability sub-keys. */
    case FullSite     = 'FullSite';
    case DatabaseOnly = 'DatabaseOnly';
    case Selective    = 'Selective';
    case Restore      = 'Restore';
    case Import       = 'Import';

    /** Restore option keys. */
    case Strict             = 'Strict';
    case ApplyIncrementals  = 'ApplyIncrementals';
    case Sqlite             = 'Sqlite';
    case SqliteFile         = 'SqliteFile';
    case InternalMode       = '_Mode';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
