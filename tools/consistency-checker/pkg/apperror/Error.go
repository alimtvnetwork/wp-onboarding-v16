// Package apperror provides structured errors for the consistency checker.
package apperror

import "fmt"

// Code classifies the error.
type Code string

const (
	ErrConfig   Code = "CONFIG_ERROR"
	ErrDatabase Code = "DATABASE_ERROR"
	ErrScanner  Code = "SCANNER_ERROR"
	ErrRule     Code = "RULE_ERROR"
	ErrIO       Code = "IO_ERROR"
)

// AppError is a structured error with code and context.
type AppError struct {
	Code    Code
	Message string
	Path    string
	Cause   error
}

// Error implements the error interface.
func (e *AppError) Error() string {
	if e.Cause != nil {
		return fmt.Sprintf("[%s] %s: %v", e.Code, e.Message, e.Cause)
	}
	return fmt.Sprintf("[%s] %s", e.Code, e.Message)
}

// New creates a new AppError.
func New(code Code, message string) *AppError {
	return &AppError{Code: code, Message: message}
}

// Wrap wraps an existing error with code and message.
func Wrap(err error, code Code, message string) *AppError {
	return &AppError{Code: code, Message: message, Cause: err}
}

// WithPath attaches a file path to the error.
func (e *AppError) WithPath(path string) *AppError {
	e.Path = path
	return e
}
