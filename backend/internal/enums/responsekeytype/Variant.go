package responsekeytype

// Variant represents standardized response array keys.
type Variant uint16

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
	Deactivated
	PluginSlug
	IsUpdate
	IsSelfUpdate
	PluginVersion
	ActivationError
	Inventory
	PluginFile

	// Self-update diagnostics keys.
	SelfUpdateStatus
	RollbackReason
	RollbackAttempted
	RollbackSuccess
	RestoredVersion
	RootCause
	Validation
	HealthCheck
	Passed
	ErrorCount
	Healthy
	IssueCount
	Issues
	BootErrors

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
	InfoLog
	FullLog
	StacktraceLog
	Exists
	Content
	Truncated
	Lines
	TotalLines
	RequestedAt

	// Log-clearing token keys.
	ConfirmationRequired
	ConfirmEndpoint
	ExpiresIn

	// Dedup registry keys.
	DedupRegistry
	EntryCount
	Entries
	FileSizeBytes

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
	ChecksumMd5
	PluginName

	// Status payload keys.
	Route
	Methods
	Result
	Results

	// Cloud storage keys.
	Account
	AccountId
	AccountLabel
	Accounts
	ConnectionStatus
	RemotePath
	RemoteUrl
	ProviderSettings
	RotationApplied
	CurrentCount
	CurrentSizeMB
	MaxCount
	MaxSizeMB
	IsOverLimit
	OAuthState
	OAuthUrl

	// Backup and history keys.
	Backup
	BackupHistory
	BaseFullBackupId

	// Upload result keys.
	UploadResult
	DeletedFiles
	FilesMoved
	MovedFiles
	ArchiveFileSizeBytes

	// User management keys.
	AppPassword
	DisplayName
	Email
	Username
	UserId
	Users
	Uuid
	Role
	Revoked
	TokenMask
	ReassignedTo

	// Git/repository keys.
	BranchName
	Branches
	CommitSha
	Repositories

	// Miscellaneous keys.
	Created
	Updated
	Skipped
	Meta
	Pagination
	NextAction
	FieldsModified

	// Log status keys (PHP log status/retrieval responses).
	File
	LogFile
	ErrorFile
	StacktraceFile
	ArchiveCount
	SizeBytes
	LastModified
	LineCount
	IncludeInfoLog
	IncludeErrorLog
	IncludeStacktrace
	MaxLines
	Database
	TransactionCount
	ErrorSessionCount

	// Log clearing keys.
	Cleared
	ClearedBy
	Machine
	Ip
	ActivityLog
	ErrorSessions

	// Log rotation status keys.
	Rotation
	Config
	OldestArchive
	NewestArchive

	// Cross-plugin clear-all keys.
	Riseup
	Qupload

	// Debug route keys.
	Namespace
	TotalRoutes
	Routes
	Pattern

	// QUpload-specific keys.
	Api
	DbAvailable
	MemoryLimit
	PostMaxSize
	PostMaxSizeBytes
	PreviousVersion
	RolledBack
	ServerTime
	UploadMaxFilesize
	UploadMaxFilesizeBytes
)
