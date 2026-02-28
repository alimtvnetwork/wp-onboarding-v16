package responsekeytype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents standardized response array keys.
type Variant byte

const (
	Invalid Variant = iota

	// Envelope keys.
	Success
	Error
	Message
	Data
	Code
	Valid
	Errors
	Cached
	Phase
	Reason

	// Domain collection keys.
	Total
	Agents
	Actions
	Logs
	Snapshots
	Sql
	Params
	Sets
	Plugins
	Tables
	Settings
	Providers
	Dependencies

	// File and size keys.
	Rows
	Bytes
	Size
	FileSize
	Path
	Filename
	Checksum
	Duration
	Count
	Files
	Directory
	Scope
	Exported
	Entry
	Computed
	Removed

	// Pagination keys.
	Limit
	Offset

	// Domain entity keys.
	Posts
	Categories
	Category
	Export
	Incrementals
	TotalSize
	Applied
	Folder

	// Snapshot-domain keys.
	SnapshotId
	Sequence
	FolderName
	TablesChanged
	TotalRows
	TotalNewRows
	ZipPath
	ZipSize
	BackupId
	ZipFailed
	SkipAudit
	TablesRestored

	// Cleanup-pipeline keys.
	DeletedByPolicy
	DeletedOrphans
	DeletedFailed
	SpaceFreedBytes
	Retention
	Orphans
	Stuck
	DryRun
	BytesFreed
	Deleted
	Cleaned

	// Plugin lifecycle keys.
	Activated
	PluginSlug
	IsUpdate
	IsSelfUpdate
	PluginVersion
	ActivationError
	Inventory
	PluginFile

	// General-purpose entity keys.
	Slug
	Title
	Type
	Action
	Status
	Percent
	Plugin

	// Log/diagnostic keys.
	ErrorLog
	FullLog
	StacktraceLog
	Exists
	Content
	Truncated
	Lines
	TotalLines

	// Internal/domain-specific keys.
	Ids
	TotalSnapshots
	TotalSizeBytes
	TempFile
	Stmt
	Columns

	// Temporal keys.
	CreatedAt
	UpdatedAt
	Timestamp

	// Analysis and dependency keys.
	ParentTable
	ChildTable
	FkColumn
	RefColumn
	SeedOrder
	TableCount
	DepCount
	NewRows
	PluginDetails
	IncludedIds
	IncrementalCount

	// Detection and provider keys.
	DetectionMethod
	SqliteVersion
	IsCore

	// Scheduler keys.
	ScheduleEnabled
	NextScheduledSnapshot
	NextCleanup
	RetentionType
	RetentionDays
	RetentionCount
	SnapshotType

	// Error enrichment keys.
	ErrorCategory
	LogHint

	// Sync keys.
	FilesUpdated
	FilesDeleted
	FilesIgnored
	IgnoredFiles

	// Export and plugin keys.
	PluginZip
	ResolvedUrl
	TraceLines

	// Snapshot progress and worker keys.
	CompletedAt
	ExportedAt
	Format
	FormatVersion
	JobId
	TotalTables
	TablesExported
	PoolSize
	TotalBatches
	CurrentBatch
	TableProgress
	IncrementalsApplied
	SkippedMaster
	ExportedTables
	SnapshotDir
	DirName
	RowCount

	// Cron and audit keys.
	TriggeredBy
	AuditData
	LogDataKey

	// Manifest and import metadata keys.
	OriginalId
	OriginalCreatedAt
	SourceSite
	OriginalTitle
	OriginalType
	WpVersion
	PhpVersion
	MysqlVersion
	SiteUrl
	DbPrefix
	PluginCount
	DurationMs
	TableCounts

	// Sync manifest keys.
	DownloadUrl
	FileCount
	GeneratedAt
	CacheStats
	FromCache

	// Statistics keys.
	TotalTransactions
	ByAction
	ByStatus
	Last24h

	// Backup option keys.
	IncludePlugins
	PluginSelection
	Compression
	Async
	Trigger
	MasterSnapshotId
	MasterDir
	Confirm
	CreateBackup
	RequireBackup
	Mode

	// Scheduler response keys.
	Frequency
	Time
	Day
	Scheduled
	Trace
	Options

	// Storage stats keys.
	TotalSizeFormatted
	OldestTimestamp
	NewestTimestamp
	DiskFreeBytes
	DiskFreeFormatted
	SnapshotsCount
	BytesFormatted

	// Progress envelope keys.
	IsSuccess
	HasAnyErrors

	// Cleanup detail keys.
	Details
	Order

	// Internal passing keys.
	Graph
	InDegree
	Manifest
	SqlitePath
	RealPath
	FilePath
	PkColumn
	TableName

	// Provider and plugin info keys.
	Id
	Name
	Available
	Capabilities
	Version
	Author
	Description
	Active
	TotalFiles
	LastSeenId
	FileType
	Provider
	Snapshot
	Source

	// Capability sub-keys.
	FullSite
	DatabaseOnly
	Selective
	Restore
	Import

	// Restore option keys.
	Strict
	ApplyIncrementals
	Sqlite
	SqliteFile
	InternalMode

	// OPcache status keys.
	OpcacheAvailable
	OpcacheReset
	FilesInvalidated

	// Plugin archive keys.
	Zip
	ZipFile
	FileSizeBytes
	ChecksumMd5
	PluginName

	// Status payload keys.
	Route
	Methods
	Result
	Results
)

var variantLabels = [...]string{
	Invalid:               "Invalid",
	Success:               "Success",
	Error:                 "Error",
	Message:               "Message",
	Data:                  "Data",
	Code:                  "Code",
	Valid:                 "Valid",
	Errors:                "Errors",
	Cached:                "Cached",
	Phase:                 "Phase",
	Reason:                "Reason",
	Total:                 "Total",
	Agents:                "Agents",
	Actions:               "Actions",
	Logs:                  "Logs",
	Snapshots:             "Snapshots",
	Sql:                   "Sql",
	Params:                "Params",
	Sets:                  "Sets",
	Plugins:               "Plugins",
	Tables:                "Tables",
	Settings:              "Settings",
	Providers:             "Providers",
	Dependencies:          "Dependencies",
	Rows:                  "Rows",
	Bytes:                 "Bytes",
	Size:                  "Size",
	FileSize:              "FileSize",
	Path:                  "Path",
	Filename:              "Filename",
	Checksum:              "Checksum",
	Duration:              "Duration",
	Count:                 "Count",
	Files:                 "Files",
	Directory:             "Directory",
	Scope:                 "Scope",
	Exported:              "Exported",
	Entry:                 "Entry",
	Computed:              "Computed",
	Removed:               "Removed",
	Limit:                 "Limit",
	Offset:                "Offset",
	Posts:                  "Posts",
	Categories:            "Categories",
	Category:              "Category",
	Export:                 "Export",
	Incrementals:          "Incrementals",
	TotalSize:             "TotalSize",
	Applied:               "Applied",
	Folder:                "Folder",
	SnapshotId:            "SnapshotId",
	Sequence:              "Sequence",
	FolderName:            "FolderName",
	TablesChanged:         "TablesChanged",
	TotalRows:             "TotalRows",
	TotalNewRows:          "TotalNewRows",
	ZipPath:               "ZipPath",
	ZipSize:               "ZipSize",
	BackupId:              "BackupId",
	ZipFailed:             "ZipFailed",
	SkipAudit:             "SkipAudit",
	TablesRestored:        "TablesRestored",
	DeletedByPolicy:       "DeletedByPolicy",
	DeletedOrphans:        "DeletedOrphans",
	DeletedFailed:         "DeletedFailed",
	SpaceFreedBytes:       "SpaceFreedBytes",
	Retention:             "Retention",
	Orphans:               "Orphans",
	Stuck:                 "Stuck",
	DryRun:                "DryRun",
	BytesFreed:            "BytesFreed",
	Deleted:               "Deleted",
	Cleaned:               "Cleaned",
	Activated:             "Activated",
	PluginSlug:            "PluginSlug",
	IsUpdate:              "IsUpdate",
	IsSelfUpdate:          "IsSelfUpdate",
	PluginVersion:         "PluginVersion",
	ActivationError:       "ActivationError",
	Inventory:             "Inventory",
	PluginFile:            "PluginFile",
	Slug:                  "Slug",
	Title:                 "Title",
	Type:                  "Type",
	Action:                "Action",
	Status:                "Status",
	Percent:               "Percent",
	Plugin:                "Plugin",
	ErrorLog:              "ErrorLog",
	FullLog:               "FullLog",
	StacktraceLog:         "StacktraceLog",
	Exists:                "Exists",
	Content:               "Content",
	Truncated:             "Truncated",
	Lines:                 "Lines",
	TotalLines:            "TotalLines",
	Ids:                   "Ids",
	TotalSnapshots:        "TotalSnapshots",
	TotalSizeBytes:        "TotalSizeBytes",
	TempFile:              "TempFile",
	Stmt:                  "Stmt",
	Columns:               "Columns",
	CreatedAt:             "CreatedAt",
	UpdatedAt:             "UpdatedAt",
	Timestamp:             "Timestamp",
	ParentTable:           "ParentTable",
	ChildTable:            "ChildTable",
	FkColumn:              "FkColumn",
	RefColumn:             "RefColumn",
	SeedOrder:             "SeedOrder",
	TableCount:            "TableCount",
	DepCount:              "DepCount",
	NewRows:               "NewRows",
	PluginDetails:         "PluginDetails",
	IncludedIds:           "IncludedIds",
	IncrementalCount:      "IncrementalCount",
	DetectionMethod:       "DetectionMethod",
	SqliteVersion:         "SqliteVersion",
	IsCore:                "IsCore",
	ScheduleEnabled:       "ScheduleEnabled",
	NextScheduledSnapshot: "NextScheduledSnapshot",
	NextCleanup:           "NextCleanup",
	RetentionType:         "RetentionType",
	RetentionDays:         "RetentionDays",
	RetentionCount:        "RetentionCount",
	SnapshotType:          "SnapshotType",
	ErrorCategory:         "ErrorCategory",
	LogHint:               "LogHint",
	FilesUpdated:          "FilesUpdated",
	FilesDeleted:          "FilesDeleted",
	FilesIgnored:          "FilesIgnored",
	IgnoredFiles:          "IgnoredFiles",
	PluginZip:             "PluginZip",
	ResolvedUrl:           "ResolvedUrl",
	TraceLines:            "TraceLines",
	CompletedAt:           "CompletedAt",
	ExportedAt:            "ExportedAt",
	Format:                "Format",
	FormatVersion:         "FormatVersion",
	JobId:                 "JobId",
	TotalTables:           "TotalTables",
	TablesExported:        "TablesExported",
	PoolSize:              "PoolSize",
	TotalBatches:          "TotalBatches",
	CurrentBatch:          "CurrentBatch",
	TableProgress:         "TableProgress",
	IncrementalsApplied:   "IncrementalsApplied",
	SkippedMaster:         "SkippedMaster",
	ExportedTables:        "ExportedTables",
	SnapshotDir:           "SnapshotDir",
	DirName:               "DirName",
	RowCount:              "RowCount",
	TriggeredBy:           "TriggeredBy",
	AuditData:             "AuditData",
	LogDataKey:            "LogDataKey",
	OriginalId:            "OriginalId",
	OriginalCreatedAt:     "OriginalCreatedAt",
	SourceSite:            "SourceSite",
	OriginalTitle:         "OriginalTitle",
	OriginalType:          "OriginalType",
	WpVersion:             "WpVersion",
	PhpVersion:            "PhpVersion",
	MysqlVersion:          "MysqlVersion",
	SiteUrl:               "SiteUrl",
	DbPrefix:              "DbPrefix",
	PluginCount:           "PluginCount",
	DurationMs:            "DurationMs",
	TableCounts:           "TableCounts",
	DownloadUrl:           "DownloadUrl",
	FileCount:             "FileCount",
	GeneratedAt:           "GeneratedAt",
	CacheStats:            "CacheStats",
	FromCache:             "FromCache",
	TotalTransactions:     "TotalTransactions",
	ByAction:              "ByAction",
	ByStatus:              "ByStatus",
	Last24h:               "Last24h",
	IncludePlugins:        "IncludePlugins",
	PluginSelection:       "PluginSelection",
	Compression:           "Compression",
	Async:                 "Async",
	Trigger:               "Trigger",
	MasterSnapshotId:      "MasterSnapshotId",
	MasterDir:             "MasterDir",
	Confirm:               "Confirm",
	CreateBackup:          "CreateBackup",
	RequireBackup:         "RequireBackup",
	Mode:                  "Mode",
	Frequency:             "Frequency",
	Time:                  "Time",
	Day:                   "Day",
	Scheduled:             "Scheduled",
	Trace:                 "Trace",
	Options:               "Options",
	TotalSizeFormatted:    "TotalSizeFormatted",
	OldestTimestamp:       "OldestTimestamp",
	NewestTimestamp:       "NewestTimestamp",
	DiskFreeBytes:         "DiskFreeBytes",
	DiskFreeFormatted:     "DiskFreeFormatted",
	SnapshotsCount:        "SnapshotsCount",
	BytesFormatted:        "BytesFormatted",
	IsSuccess:             "IsSuccess",
	HasAnyErrors:          "HasAnyErrors",
	Details:               "Details",
	Order:                 "Order",
	Graph:                 "Graph",
	InDegree:              "InDegree",
	Manifest:              "Manifest",
	SqlitePath:            "SqlitePath",
	RealPath:              "RealPath",
	FilePath:              "FilePath",
	PkColumn:              "PkColumn",
	TableName:             "TableName",
	Id:                    "Id",
	Name:                  "Name",
	Available:             "Available",
	Capabilities:          "Capabilities",
	Version:               "Version",
	Author:                "Author",
	Description:           "Description",
	Active:                "Active",
	TotalFiles:            "TotalFiles",
	LastSeenId:            "LastSeenId",
	FileType:              "FileType",
	Provider:              "Provider",
	Snapshot:              "Snapshot",
	Source:                "Source",
	FullSite:              "FullSite",
	DatabaseOnly:          "DatabaseOnly",
	Selective:             "Selective",
	Restore:               "Restore",
	Import:                "Import",
	Strict:                "Strict",
	ApplyIncrementals:     "ApplyIncrementals",
	Sqlite:                "Sqlite",
	SqliteFile:            "SqliteFile",
	InternalMode:          "InternalMode",
	OpcacheAvailable:      "OpcacheAvailable",
	OpcacheReset:          "OpcacheReset",
	FilesInvalidated:      "FilesInvalidated",
	Zip:                   "Zip",
	ZipFile:               "ZipFile",
	FileSizeBytes:         "FileSizeBytes",
	ChecksumMd5:           "ChecksumMd5",
	PluginName:            "PluginName",
	Route:                 "Route",
	Methods:               "Methods",
	Result:                "Result",
	Results:               "Results",
}

var variantValues = [...]string{
	Invalid:               "invalid",
	Success:               "Success",
	Error:                 "Error",
	Message:               "Message",
	Data:                  "Data",
	Code:                  "Code",
	Valid:                 "Valid",
	Errors:                "Errors",
	Cached:                "Cached",
	Phase:                 "Phase",
	Reason:                "Reason",
	Total:                 "Total",
	Agents:                "Agents",
	Actions:               "Actions",
	Logs:                  "Logs",
	Snapshots:             "Snapshots",
	Sql:                   "Sql",
	Params:                "Params",
	Sets:                  "Sets",
	Plugins:               "Plugins",
	Tables:                "Tables",
	Settings:              "Settings",
	Providers:             "Providers",
	Dependencies:          "Dependencies",
	Rows:                  "Rows",
	Bytes:                 "Bytes",
	Size:                  "Size",
	FileSize:              "FileSize",
	Path:                  "Path",
	Filename:              "Filename",
	Checksum:              "Checksum",
	Duration:              "Duration",
	Count:                 "Count",
	Files:                 "Files",
	Directory:             "Directory",
	Scope:                 "Scope",
	Exported:              "Exported",
	Entry:                 "Entry",
	Computed:              "Computed",
	Removed:               "Removed",
	Limit:                 "Limit",
	Offset:                "Offset",
	Posts:                  "Posts",
	Categories:            "Categories",
	Category:              "Category",
	Export:                 "Export",
	Incrementals:          "Incrementals",
	TotalSize:             "TotalSize",
	Applied:               "Applied",
	Folder:                "Folder",
	SnapshotId:            "SnapshotId",
	Sequence:              "Sequence",
	FolderName:            "FolderName",
	TablesChanged:         "TablesChanged",
	TotalRows:             "TotalRows",
	TotalNewRows:          "TotalNewRows",
	ZipPath:               "ZipPath",
	ZipSize:               "ZipSize",
	BackupId:              "BackupId",
	ZipFailed:             "ZipFailed",
	SkipAudit:             "SkipAudit",
	TablesRestored:        "TablesRestored",
	DeletedByPolicy:       "DeletedByPolicy",
	DeletedOrphans:        "DeletedOrphans",
	DeletedFailed:         "DeletedFailed",
	SpaceFreedBytes:       "SpaceFreedBytes",
	Retention:             "Retention",
	Orphans:               "Orphans",
	Stuck:                 "Stuck",
	DryRun:                "DryRun",
	BytesFreed:            "BytesFreed",
	Deleted:               "Deleted",
	Cleaned:               "Cleaned",
	Activated:             "Activated",
	PluginSlug:            "PluginSlug",
	IsUpdate:              "IsUpdate",
	IsSelfUpdate:          "IsSelfUpdate",
	PluginVersion:         "PluginVersion",
	ActivationError:       "ActivationError",
	Inventory:             "Inventory",
	PluginFile:            "PluginFile",
	Slug:                  "Slug",
	Title:                 "Title",
	Type:                  "Type",
	Action:                "Action",
	Status:                "Status",
	Percent:               "Percent",
	Plugin:                "Plugin",
	ErrorLog:              "ErrorLog",
	FullLog:               "FullLog",
	StacktraceLog:         "StacktraceLog",
	Exists:                "Exists",
	Content:               "Content",
	Truncated:             "Truncated",
	Lines:                 "Lines",
	TotalLines:            "TotalLines",
	Ids:                   "Ids",
	TotalSnapshots:        "TotalSnapshots",
	TotalSizeBytes:        "TotalSizeBytes",
	TempFile:              "TempFile",
	Stmt:                  "Stmt",
	Columns:               "Columns",
	CreatedAt:             "CreatedAt",
	UpdatedAt:             "UpdatedAt",
	Timestamp:             "Timestamp",
	ParentTable:           "ParentTable",
	ChildTable:            "ChildTable",
	FkColumn:              "FkColumn",
	RefColumn:             "RefColumn",
	SeedOrder:             "SeedOrder",
	TableCount:            "TableCount",
	DepCount:              "DepCount",
	NewRows:               "NewRows",
	PluginDetails:         "PluginDetails",
	IncludedIds:           "IncludedIds",
	IncrementalCount:      "IncrementalCount",
	DetectionMethod:       "DetectionMethod",
	SqliteVersion:         "SqliteVersion",
	IsCore:                "IsCore",
	ScheduleEnabled:       "ScheduleEnabled",
	NextScheduledSnapshot: "NextScheduledSnapshot",
	NextCleanup:           "NextCleanup",
	RetentionType:         "RetentionType",
	RetentionDays:         "RetentionDays",
	RetentionCount:        "RetentionCount",
	SnapshotType:          "SnapshotType",
	ErrorCategory:         "ErrorCategory",
	LogHint:               "LogHint",
	FilesUpdated:          "FilesUpdated",
	FilesDeleted:          "FilesDeleted",
	FilesIgnored:          "FilesIgnored",
	IgnoredFiles:          "IgnoredFiles",
	PluginZip:             "PluginZip",
	ResolvedUrl:           "ResolvedUrl",
	TraceLines:            "TraceLines",
	CompletedAt:           "CompletedAt",
	ExportedAt:            "ExportedAt",
	Format:                "Format",
	FormatVersion:         "FormatVersion",
	JobId:                 "JobId",
	TotalTables:           "TotalTables",
	TablesExported:        "TablesExported",
	PoolSize:              "PoolSize",
	TotalBatches:          "TotalBatches",
	CurrentBatch:          "CurrentBatch",
	TableProgress:         "TableProgress",
	IncrementalsApplied:   "IncrementalsApplied",
	SkippedMaster:         "SkippedMaster",
	ExportedTables:        "ExportedTables",
	SnapshotDir:           "SnapshotDir",
	DirName:               "DirName",
	RowCount:              "RowCount",
	TriggeredBy:           "TriggeredBy",
	AuditData:             "AuditData",
	LogDataKey:            "LogData",
	OriginalId:            "OriginalId",
	OriginalCreatedAt:     "OriginalCreatedAt",
	SourceSite:            "SourceSite",
	OriginalTitle:         "OriginalTitle",
	OriginalType:          "OriginalType",
	WpVersion:             "WpVersion",
	PhpVersion:            "PhpVersion",
	MysqlVersion:          "MysqlVersion",
	SiteUrl:               "SiteUrl",
	DbPrefix:              "DbPrefix",
	PluginCount:           "PluginCount",
	DurationMs:            "DurationMs",
	TableCounts:           "TableCounts",
	DownloadUrl:           "DownloadUrl",
	FileCount:             "FileCount",
	GeneratedAt:           "GeneratedAt",
	CacheStats:            "CacheStats",
	FromCache:             "FromCache",
	TotalTransactions:     "TotalTransactions",
	ByAction:              "ByAction",
	ByStatus:              "ByStatus",
	Last24h:               "Last24h",
	IncludePlugins:        "IncludePlugins",
	PluginSelection:       "PluginSelection",
	Compression:           "Compression",
	Async:                 "Async",
	Trigger:               "Trigger",
	MasterSnapshotId:      "MasterSnapshotId",
	MasterDir:             "MasterDir",
	Confirm:               "Confirm",
	CreateBackup:          "CreateBackup",
	RequireBackup:         "RequireBackup",
	Mode:                  "Mode",
	Frequency:             "Frequency",
	Time:                  "Time",
	Day:                   "Day",
	Scheduled:             "Scheduled",
	Trace:                 "Trace",
	Options:               "Options",
	TotalSizeFormatted:    "TotalSizeFormatted",
	OldestTimestamp:       "OldestTimestamp",
	NewestTimestamp:       "NewestTimestamp",
	DiskFreeBytes:         "DiskFreeBytes",
	DiskFreeFormatted:     "DiskFreeFormatted",
	SnapshotsCount:        "SnapshotsCount",
	BytesFormatted:        "BytesFormatted",
	IsSuccess:             "IsSuccess",
	HasAnyErrors:          "HasAnyErrors",
	Details:               "Details",
	Order:                 "Order",
	Graph:                 "Graph",
	InDegree:              "InDegree",
	Manifest:              "Manifest",
	SqlitePath:            "SqlitePath",
	RealPath:              "RealPath",
	FilePath:              "FilePath",
	PkColumn:              "PkColumn",
	TableName:             "TableName",
	Id:                    "Id",
	Name:                  "Name",
	Available:             "Available",
	Capabilities:          "Capabilities",
	Version:               "Version",
	Author:                "Author",
	Description:           "Description",
	Active:                "Active",
	TotalFiles:            "TotalFiles",
	LastSeenId:            "LastSeenId",
	FileType:              "FileType",
	Provider:              "Provider",
	Snapshot:              "Snapshot",
	Source:                "Source",
	FullSite:              "FullSite",
	DatabaseOnly:          "DatabaseOnly",
	Selective:             "Selective",
	Restore:               "Restore",
	Import:                "Import",
	Strict:                "Strict",
	ApplyIncrementals:     "ApplyIncrementals",
	Sqlite:                "Sqlite",
	SqliteFile:            "SqliteFile",
	InternalMode:          "_Mode",
	OpcacheAvailable:      "OpcacheAvailable",
	OpcacheReset:          "OpcacheReset",
	FilesInvalidated:      "FilesInvalidated",
	Zip:                   "Zip",
	ZipFile:               "ZipFile",
	FileSizeBytes:         "FileSizeBytes",
	ChecksumMd5:           "ChecksumMd5",
	PluginName:            "PluginName",
	Route:                 "Route",
	Methods:               "Methods",
	Result:                "Result",
	Results:               "Results",
}

func (v Variant) String() string {
	return v.Value()
}

func (v Variant) Label() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}

	return variantLabels[v]
}

func (v Variant) Value() string {
	if v.IsInvalid() {
		return variantValues[Invalid]
	}

	return variantValues[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsInvalid() bool         { return v == Invalid }
func (v Variant) IsDefined() bool         { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool { return v.IsDefined() && v.IsValid() }

// IsOther returns true if the receiver is NOT the given variant.
func (v Variant) IsOther(other Variant) bool { return v != other }

// IsAnyOf returns true if the receiver matches any of the given variants.
func (v Variant) IsAnyOf(others ...Variant) bool {
	for _, o := range others {
		if v == o {
			return true
		}
	}

	return false
}

func All() []Variant {
	all := make([]Variant, 0, len(variantLabels)-1)

	for i := 1; i < len(variantLabels); i++ {
		all = append(all, Variant(i))
	}

	return all
}

func ByIndex(i int) Variant {
	isOutOfRange := i < 0 || i >= len(variantLabels)

	if isOutOfRange {
		return Invalid
	}

	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)

	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	for i, str := range variantValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid response key: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)

	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}

	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string

	err := json.Unmarshal(data, &s)

	if err != nil {
		return err
	}

	parsed, err := Parse(s)
	if err != nil {
		return err
	}

	*v = parsed

	return nil
}
