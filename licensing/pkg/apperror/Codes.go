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
	ErrKeyGeneration  ErrorCode = "EL1009" // License key generation failed
)
