package dbutil

// Result wraps a single-item query outcome with error and stack trace.
type Result[T any] struct {
	value      T
	err        error
	stackTrace string
	defined    bool
}

// NewResult constructs a successful single-item result.
func NewResult[T any](value T) Result[T] {
	return Result[T]{value: value, defined: true}
}

// NewResultError constructs an error result with a captured stack trace.
func NewResultError[T any](err error, stack string) Result[T] {
	return Result[T]{err: err, stackTrace: stack}
}

// IsEmpty returns true when no row was found (no error, just absent).
func (r Result[T]) IsEmpty() bool { return !r.defined }

// IsDefined returns true when a row was successfully scanned.
func (r Result[T]) IsDefined() bool { return r.defined }

// HasError returns true when the query failed.
func (r Result[T]) HasError() bool { return r.err != nil }

// IsSafe returns true when a value exists and there is no error.
func (r Result[T]) IsSafe() bool { return r.defined && r.err == nil }

// Value returns the scanned value (zero-value if not defined).
func (r Result[T]) Value() T { return r.value }

// AppError returns the underlying error, or nil.
// Named AppError (not Error) to avoid confusion with Go's native error interface.
func (r Result[T]) AppError() error { return r.err }

// StackTrace returns the captured stack trace if an error occurred.
func (r Result[T]) StackTrace() string { return r.stackTrace }
