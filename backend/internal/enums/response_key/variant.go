package responsekey

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents standardized response array keys.
type Variant byte

const (
	Invalid        Variant = iota
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
	Limit
	Offset
	Posts
	Categories
	Category
	Export
	Incrementals
	TotalSize
	Applied
	Folder
	SnapshotId
	Sequence
	FolderName
	TablesChanged
	TotalRows
	TotalNewRows
	ZipSize
	BackupId
	ZipFailed
	SkipAudit
	TablesRestored
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
	Activated
	PluginSlug
	IsUpdate
	IsSelfUpdate
	PluginVersion
	ActivationError
	Inventory
	ErrorLog
	FullLog
	StacktraceLog
	Exists
	Content
	Truncated
	Lines
	TotalLines
	Ids
	TotalSnapshots
	TotalSizeBytes
	TempFile
	Stmt
	Columns
	CreatedAt
	UpdatedAt
	Timestamp
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
	DetectionMethod
	SqliteVersion
	IsCore
	ScheduleEnabled
	NextScheduledSnapshot
	NextCleanup
	RetentionType
	RetentionDays
	RetentionCount
	SnapshotType
	ErrorCategory
	LogHint
	FilesUpdated
	FilesDeleted
	FilesIgnored
	IgnoredFiles
	PluginZip
	ResolvedUrl
	TraceLines
	CompletedAt
	ExportedAt
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
	TriggeredBy
	AuditData
	LogDataKey
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
	Settings
	Providers
	Dependencies
	Slug
	Title
	Type
	PluginFile
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
	Settings:              "Settings",
	Providers:             "Providers",
	Dependencies:          "Dependencies",
	Slug:                  "Slug",
	Title:                 "Title",
	Type:                  "Type",
	PluginFile:            "PluginFile",
}

var variantValues = [...]string{
	Invalid:               "invalid",
	Success:               "success",
	Error:                 "error",
	Message:               "message",
	Data:                  "data",
	Code:                  "code",
	Valid:                 "valid",
	Errors:                "errors",
	Cached:                "cached",
	Phase:                 "phase",
	Reason:                "reason",
	Total:                 "total",
	Agents:                "agents",
	Actions:               "actions",
	Logs:                  "logs",
	Snapshots:             "snapshots",
	Sql:                   "sql",
	Params:                "params",
	Sets:                  "sets",
	Plugins:               "plugins",
	Tables:                "tables",
	Rows:                  "rows",
	Bytes:                 "bytes",
	Size:                  "size",
	FileSize:              "fileSize",
	Path:                  "path",
	Filename:              "filename",
	Checksum:              "checksum",
	Duration:              "duration",
	Count:                 "count",
	Files:                 "files",
	Directory:             "directory",
	Scope:                 "scope",
	Exported:              "exported",
	Entry:                 "entry",
	Computed:              "computed",
	Removed:               "removed",
	Limit:                 "limit",
	Offset:                "offset",
	Posts:                  "posts",
	Categories:            "categories",
	Category:              "category",
	Export:                 "export",
	Incrementals:          "incrementals",
	TotalSize:             "totalSize",
	Applied:               "applied",
	Folder:                "folder",
	SnapshotId:            "snapshotId",
	Sequence:              "sequence",
	FolderName:            "folderName",
	TablesChanged:         "tablesChanged",
	TotalRows:             "totalRows",
	TotalNewRows:          "totalNewRows",
	ZipSize:               "zipSize",
	BackupId:              "backupId",
	ZipFailed:             "zipFailed",
	SkipAudit:             "skipAudit",
	TablesRestored:        "tablesRestored",
	DeletedByPolicy:       "deletedByPolicy",
	DeletedOrphans:        "deletedOrphans",
	DeletedFailed:         "deletedFailed",
	SpaceFreedBytes:       "spaceFreedBytes",
	Retention:             "retention",
	Orphans:               "orphans",
	Stuck:                 "stuck",
	DryRun:                "dryRun",
	BytesFreed:            "bytesFreed",
	Deleted:               "deleted",
	Cleaned:               "cleaned",
	Activated:             "activated",
	PluginSlug:            "pluginSlug",
	IsUpdate:              "isUpdate",
	IsSelfUpdate:          "isSelfUpdate",
	PluginVersion:         "pluginVersion",
	ActivationError:       "activationError",
	Inventory:             "inventory",
	ErrorLog:              "errorLog",
	FullLog:               "fullLog",
	StacktraceLog:         "stacktraceLog",
	Exists:                "exists",
	Content:               "content",
	Truncated:             "truncated",
	Lines:                 "lines",
	TotalLines:            "totalLines",
	Ids:                   "ids",
	TotalSnapshots:        "totalSnapshots",
	TotalSizeBytes:        "totalSizeBytes",
	TempFile:              "tempFile",
	Stmt:                  "stmt",
	Columns:               "columns",
	CreatedAt:             "createdAt",
	UpdatedAt:             "updatedAt",
	Timestamp:             "timestamp",
	ParentTable:           "parentTable",
	ChildTable:            "childTable",
	FkColumn:              "fkColumn",
	RefColumn:             "refColumn",
	SeedOrder:             "seedOrder",
	TableCount:            "tableCount",
	DepCount:              "depCount",
	NewRows:               "newRows",
	PluginDetails:         "pluginDetails",
	IncludedIds:           "includedIds",
	IncrementalCount:      "incrementalCount",
	DetectionMethod:       "detectionMethod",
	SqliteVersion:         "sqliteVersion",
	IsCore:                "isCore",
	ScheduleEnabled:       "scheduleEnabled",
	NextScheduledSnapshot: "nextScheduledSnapshot",
	NextCleanup:           "nextCleanup",
	RetentionType:         "retentionType",
	RetentionDays:         "retentionDays",
	RetentionCount:        "retentionCount",
	SnapshotType:          "snapshotType",
	ErrorCategory:         "errorCategory",
	LogHint:               "logHint",
	FilesUpdated:          "filesUpdated",
	FilesDeleted:          "filesDeleted",
	FilesIgnored:          "filesIgnored",
	IgnoredFiles:          "ignoredFiles",
	PluginZip:             "pluginZip",
	ResolvedUrl:           "resolvedUrl",
	TraceLines:            "traceLines",
	CompletedAt:           "completedAt",
	ExportedAt:            "exportedAt",
	FormatVersion:         "formatVersion",
	JobId:                 "jobId",
	TotalTables:           "totalTables",
	TablesExported:        "tablesExported",
	PoolSize:              "poolSize",
	TotalBatches:          "totalBatches",
	CurrentBatch:          "currentBatch",
	TableProgress:         "tableProgress",
	IncrementalsApplied:   "incrementalsApplied",
	SkippedMaster:         "skippedMaster",
	ExportedTables:        "exportedTables",
	SnapshotDir:           "snapshotDir",
	DirName:               "dirName",
	RowCount:              "rowCount",
	TriggeredBy:           "triggeredBy",
	AuditData:             "auditData",
	LogDataKey:            "logData",
	OriginalId:            "originalId",
	OriginalCreatedAt:     "originalCreatedAt",
	SourceSite:            "sourceSite",
	OriginalTitle:         "originalTitle",
	OriginalType:          "originalType",
	WpVersion:             "wpVersion",
	PhpVersion:            "phpVersion",
	MysqlVersion:          "mysqlVersion",
	SiteUrl:               "siteUrl",
	DbPrefix:              "dbPrefix",
	PluginCount:           "pluginCount",
	DurationMs:            "durationMs",
	TableCounts:           "tableCounts",
	Settings:              "settings",
	Providers:             "providers",
	Dependencies:          "dependencies",
	Slug:                  "slug",
	Title:                 "title",
	Type:                  "type",
	PluginFile:            "pluginFile",
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
	if i < 0 || i >= len(variantLabels) {
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

	if err := json.Unmarshal(data, &s); err != nil {
		return err
	}

	parsed, err := Parse(s)
	if err != nil {
		return err
	}

	*v = parsed

	return nil
}
