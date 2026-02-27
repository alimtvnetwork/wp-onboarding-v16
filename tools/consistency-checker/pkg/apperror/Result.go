// Package apperror — Result type for single-return functions.
package apperror

// Result wraps a value or an error (single-return pattern).
type Result[T any] struct {
	value T
	err   *AppError
}

// Ok creates a successful Result.
func Ok[T any](value T) Result[T] {
	return Result[T]{value: value}
}

// Fail creates a failed Result.
func Fail[T any](err *AppError) Result[T] {
	return Result[T]{err: err}
}

// Value returns the wrapped value.
func (r Result[T]) Value() T { return r.value }

// HasError returns true if the result contains an error.
func (r Result[T]) HasError() bool { return r.err != nil }

// AppError returns the wrapped error.
func (r Result[T]) AppError() *AppError { return r.err }
