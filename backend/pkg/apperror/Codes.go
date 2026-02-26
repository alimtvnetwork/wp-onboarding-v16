// Package apperror - Typed error codes
package apperror

// ErrorCode is a typed string constant for structured error identification.
// All error codes follow the EXXXX format where X is the category prefix.
type ErrorCode string

// String returns the string representation of the error code.
func (c ErrorCode) String() string { return string(c) }

// IsValid returns true if the error code is non-empty.
func (c ErrorCode) IsValid() bool { return c != "" }

// IsOtherThan returns true if this code differs from the given code.
func (c ErrorCode) IsOtherThan(other ErrorCode) bool { return c != other }

// File/Directory error codes
const (
	ErrDirRead     ErrorCode = "DIR_READ_ERROR"
	ErrPathInvalid ErrorCode = "PATH_INVALID"
)

// Error code categories follow the pattern EXNNN where X is the category

// Configuration errors (E1xxx)
const (
	ErrConfigLoad     ErrorCode = "E1001" // Failed to load configuration file
	ErrConfigParse    ErrorCode = "E1002" // Failed to parse configuration
	ErrConfigValidate ErrorCode = "E1003" // Configuration validation failed
	ErrConfigSeed     ErrorCode = "E1004" // Failed to seed from configuration
)

// Database errors (E2xxx)
const (
	ErrDatabaseConnect ErrorCode = "E2001" // Failed to connect to database
	ErrDatabaseMigrate ErrorCode = "E2002" // Failed to run migrations
	ErrDatabaseQuery   ErrorCode = "E2003" // Query execution failed
	ErrDatabaseInsert  ErrorCode = "E2004" // Insert operation failed
	ErrDatabaseUpdate  ErrorCode = "E2005" // Update operation failed
	ErrDatabaseDelete  ErrorCode = "E2006" // Delete operation failed
	ErrDatabaseScan    ErrorCode = "E2007" // Failed to scan query result
	ErrDatabaseExec    ErrorCode = "E2008" // Failed to execute statement
	ErrDuplicate       ErrorCode = "E2009" // Duplicate entry exists
)

// WordPress API errors (E3xxx)
const (
	ErrWPConnection     ErrorCode = "E3001" // Failed to connect to WordPress
	ErrWPAuth           ErrorCode = "E3002" // Authentication failed
	ErrWPAPIDisabled    ErrorCode = "E3003" // REST API is disabled
	ErrWPPluginList     ErrorCode = "E3004" // Failed to list plugins
	ErrWPPluginGet      ErrorCode = "E3005" // Failed to get plugin info
	ErrWPPluginUpload   ErrorCode = "E3006" // Failed to upload plugin
	ErrWPPluginActivate ErrorCode = "E3007" // Failed to activate plugin
	ErrWPTimeout        ErrorCode = "E3008" // Request timed out
	ErrWPUploadFailed   ErrorCode = "E3009" // Plugin upload to WordPress failed
	ErrWPPluginDelete   ErrorCode = "E3010" // Failed to delete plugin
)

// File system errors (E4xxx)
const (
	ErrFSRead       ErrorCode = "E4001" // Failed to read file
	ErrFSWrite      ErrorCode = "E4002" // Failed to write file
	ErrFSDelete     ErrorCode = "E4003" // Failed to delete file
	ErrFSNotFound   ErrorCode = "E4004" // File or directory not found
	ErrFSPermission ErrorCode = "E4005" // Permission denied
	ErrFSWatch      ErrorCode = "E4006" // Failed to watch directory
	ErrFSZip        ErrorCode = "E4007" // Failed to create/extract zip
	ErrFSHash       ErrorCode = "E4008" // Failed to calculate hash
	ErrFSScan       ErrorCode = "E4009" // Failed to scan directory
	ErrFSInvalid    ErrorCode = "E4010" // Invalid file or directory
)

// Sync errors (E5xxx)
const (
	ErrSyncCompare    ErrorCode = "E5001" // Failed to compare files
	ErrSyncConflict   ErrorCode = "E5002" // Sync conflict detected
	ErrSyncAborted    ErrorCode = "E5003" // Sync operation aborted
	ErrSyncInProgress ErrorCode = "E5004" // Another sync is in progress
	ErrSyncNoChanges  ErrorCode = "E5005" // No changes to sync
	ErrSyncFailed     ErrorCode = "E5006" // Sync operation failed
	ErrSyncPartial    ErrorCode = "E5007" // Partial sync completed
	ErrSyncTimeout    ErrorCode = "E5008" // Sync operation timed out
)

// Backup errors (E6xxx)
const (
	ErrBackupCreate   ErrorCode = "E6001" // Failed to create backup
	ErrBackupRestore  ErrorCode = "E6002" // Failed to restore backup
	ErrBackupDelete   ErrorCode = "E6003" // Failed to delete backup
	ErrBackupCorrupt  ErrorCode = "E6004" // Backup file is corrupt
	ErrBackupExpired  ErrorCode = "E6005" // Backup has expired
	ErrBackupNotFound ErrorCode = "E6006" // Backup not found
)

// Git errors (E7xxx)
const (
	ErrGitNotRepo ErrorCode = "E7001" // Directory is not a git repository
	ErrGitCommand ErrorCode = "E7002" // Git command execution failed
	ErrGitPull    ErrorCode = "E7003" // Git pull failed
	ErrGitPush    ErrorCode = "E7004" // Git push failed
	ErrGitCommit  ErrorCode = "E7005" // Git commit failed
	ErrGitBranch  ErrorCode = "E7006" // Git branch operation failed
)

// Build errors (E8xxx)
const (
	ErrBuildNotConfigured ErrorCode = "E8001" // Build not configured for plugin
	ErrBuildFailed        ErrorCode = "E8002" // Build command failed
	ErrBuildTimeout       ErrorCode = "E8003" // Build command timed out
)

// General errors (E9xxx)
const (
	ErrNotFound       ErrorCode = "E9001" // Resource not found
	ErrValidation     ErrorCode = "E9002" // Validation failed
	ErrInternal       ErrorCode = "E9003" // Internal server error
	ErrNotImplemented ErrorCode = "E9004" // Feature not implemented
)

// E2E Test errors (E10xxx)
const (
	ErrE2ERunning ErrorCode = "E10001" // E2E test already running
)

// Publish errors (E11xxx)
const (
	ErrPublishPlatform ErrorCode = "E11001" // Platform not supported
	ErrPublishConfig   ErrorCode = "E11002" // Configuration marshaling failed
)

// Version errors (E12xxx)
const (
	ErrVersionNotFound ErrorCode = "E12001" // Version not found
	ErrVersionNoBackup ErrorCode = "E12002" // No backup available for version
)

// Session errors (E13xxx)
const (
	ErrSessionInit     ErrorCode = "E13001" // Failed to initialize session store
	ErrSessionStore    ErrorCode = "E13002" // Failed to persist session
	ErrSessionNotFound ErrorCode = "E13003" // Session not found
	ErrSessionList     ErrorCode = "E13004" // Failed to list sessions
	ErrSessionDelete   ErrorCode = "E13005" // Failed to delete session
	ErrSessionClear    ErrorCode = "E13006" // Failed to clear sessions
)

// Crypto errors (E14xxx)
const (
	ErrCryptoEncrypt ErrorCode = "E14001" // Encryption failed
	ErrCryptoDecrypt ErrorCode = "E14002" // Decryption failed
	ErrCryptoInvalid ErrorCode = "E14003" // Invalid cryptographic input
)

// Zip errors (E15xxx)
const (
	ErrFileOpen  ErrorCode = "E15001" // Failed to open file
	ErrZipCreate ErrorCode = "E15002" // Failed to create zip entry
	ErrZipWrite  ErrorCode = "E15003" // Failed to write to zip
)
