// Package apperror provides structured application errors for the licensing server.
package apperror

// ErrorCode is a typed string constant for structured error identification.
type ErrorCode string

// String returns the string representation of the error code.
func (c ErrorCode) String() string { return string(c) }

// Licensing error codes (EL1xxx)
const (
	ErrDatabaseQuery  ErrorCode = "EL1001" // Query execution failed
	ErrDatabaseInsert ErrorCode = "EL1002" // Insert operation failed
	ErrDatabaseUpdate ErrorCode = "EL1003" // Update operation failed
	ErrDatabaseDelete ErrorCode = "EL1004" // Delete operation failed
	ErrDatabaseScan   ErrorCode = "EL1005" // Failed to scan query result
	ErrNotFound       ErrorCode = "EL1006" // Resource not found
	ErrInternal       ErrorCode = "EL1007" // Internal server error
	ErrMarshal        ErrorCode = "EL1008" // JSON marshaling failed
	ErrKeyGeneration      ErrorCode = "EL1009" // License key generation failed
	ErrManifestInvalid    ErrorCode = "EL1010" // Manifest validation failed
	ErrManifestMissing    ErrorCode = "EL1011" // Manifest not found or empty
	ErrManifestDecode     ErrorCode = "EL1012" // Manifest JSON decode failed
	ErrChunkMissing       ErrorCode = "EL1013" // Expected chunk file missing
	ErrChunkHashMismatch  ErrorCode = "EL1014" // Chunk SHA-256 checksum mismatch
	ErrChunkSizeMismatch  ErrorCode = "EL1015" // Chunk sizes don't sum to totalSize
)
