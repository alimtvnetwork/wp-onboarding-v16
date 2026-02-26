package apperror

import "errors"

// CompiledError converts the AppError into a plain Go error containing the
// full diagnostic string (code, message, details, values, stack trace, cause chain).
// This is the ONLY sanctioned way to cross the AppError→error boundary.
// Use at the outermost edge (e.g., HTTP response serialization, CLI output).
func (e *AppError) CompiledError() error {
	return errors.New(e.FullString())
}
