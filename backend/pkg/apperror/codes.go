// Package apperror - Error codes
package apperror

// File/Directory error codes
const (
	ErrDirRead     = "DIR_READ_ERROR"
	ErrPathInvalid = "PATH_INVALID"
)

// Error code categories follow the pattern EXNNN where X is the category

// Configuration errors (E1xxx)
const (
	ErrConfigLoad     = "E1001" // Failed to load configuration file
	ErrConfigParse    = "E1002" // Failed to parse configuration
	ErrConfigValidate = "E1003" // Configuration validation failed
	ErrConfigSeed     = "E1004" // Failed to seed from configuration
)

// Database errors (E2xxx)
const (
	ErrDatabaseConnect = "E2001" // Failed to connect to database
	ErrDatabaseMigrate = "E2002" // Failed to run migrations
	ErrDatabaseQuery   = "E2003" // Query execution failed
	ErrDatabaseInsert  = "E2004" // Insert operation failed
	ErrDatabaseUpdate  = "E2005" // Update operation failed
	ErrDatabaseDelete  = "E2006" // Delete operation failed
	ErrDatabaseScan    = "E2007" // Failed to scan query result
	ErrDatabaseExec    = "E2008" // Failed to execute statement
	ErrDuplicate       = "E2009" // Duplicate entry exists
)

// WordPress API errors (E3xxx)
const (
	ErrWPConnection     = "E3001" // Failed to connect to WordPress
	ErrWPAuth           = "E3002" // Authentication failed
	ErrWPAPIDisabled    = "E3003" // REST API is disabled
	ErrWPPluginList     = "E3004" // Failed to list plugins
	ErrWPPluginGet      = "E3005" // Failed to get plugin info
	ErrWPPluginUpload   = "E3006" // Failed to upload plugin
	ErrWPPluginActivate = "E3007" // Failed to activate plugin
	ErrWPTimeout        = "E3008" // Request timed out
	ErrWPUploadFailed   = "E3009" // Plugin upload to WordPress failed
)

// File system errors (E4xxx)
const (
	ErrFSRead      = "E4001" // Failed to read file
	ErrFSWrite     = "E4002" // Failed to write file
	ErrFSDelete    = "E4003" // Failed to delete file
	ErrFSNotFound  = "E4004" // File or directory not found
	ErrFSPermission = "E4005" // Permission denied
	ErrFSWatch     = "E4006" // Failed to watch directory
	ErrFSZip       = "E4007" // Failed to create/extract zip
	ErrFSHash      = "E4008" // Failed to calculate hash
	ErrFSScan      = "E4009" // Failed to scan directory
	ErrFSInvalid   = "E4010" // Invalid file or directory
)

// Sync errors (E5xxx)
const (
	ErrSyncCompare   = "E5001" // Failed to compare files
	ErrSyncConflict  = "E5002" // Sync conflict detected
	ErrSyncAborted   = "E5003" // Sync operation aborted
	ErrSyncInProgress = "E5004" // Another sync is in progress
	ErrSyncNoChanges = "E5005" // No changes to sync
	ErrSyncFailed    = "E5006" // Sync operation failed
	ErrSyncPartial   = "E5007" // Partial sync completed
	ErrSyncTimeout   = "E5008" // Sync operation timed out
)

// Backup errors (E6xxx)
const (
	ErrBackupCreate  = "E6001" // Failed to create backup
	ErrBackupRestore = "E6002" // Failed to restore backup
	ErrBackupDelete  = "E6003" // Failed to delete backup
	ErrBackupCorrupt = "E6004" // Backup file is corrupt
	ErrBackupExpired = "E6005" // Backup has expired
	ErrBackupNotFound = "E6006" // Backup not found
)

// General errors (E9xxx)
const (
	ErrNotFound      = "E9001" // Resource not found
	ErrValidation    = "E9002" // Validation failed
	ErrInternal      = "E9003" // Internal server error
	ErrNotImplemented = "E9004" // Feature not implemented
)
