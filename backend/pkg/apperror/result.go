package apperror

// Result wraps a single value with an optional error and mandatory stack trace.
// Use for service methods that return one item (or nothing).
type Result[T any] struct {
	value   T
	err     *AppError
	defined bool
}

// Ok creates a successful Result containing the given value.
func Ok[T any](value T) Result[T] {
	return Result[T]{value: value, defined: true}
}

// Fail creates a failed Result from an AppError.
func Fail[T any](err *AppError) Result[T] {
	return Result[T]{err: err}
}

// FailWrap creates a failed Result by wrapping a raw error.
// Uses skip=3 to attribute the stack trace to the actual caller.
func FailWrap[T any](cause error, code ErrorCode, message string) Result[T] {
	wrapped := WrapWithSkip(cause, code, message, 0)

	return Result[T]{err: wrapped}
}

// FailNew creates a failed Result from a new error (no cause).
// Uses skip=3 to attribute the stack trace to the actual caller.
func FailNew[T any](code ErrorCode, message string) Result[T] {
	return Result[T]{err: NewWithSkip(code, message, 1)}
}

// HasError returns true when the operation failed.
func (r Result[T]) HasError() bool { return r.err != nil }

// IsSafe returns true when a value exists and there is no error.
func (r Result[T]) IsSafe() bool { return r.defined && r.err == nil }

// IsDefined returns true when a value was set (regardless of error).
func (r Result[T]) IsDefined() bool { return r.defined }

// IsEmpty returns true when no value was set (not an error, just absent).
func (r Result[T]) IsEmpty() bool { return !r.defined }

// Value returns the contained value. Panics if HasError is true.
func (r Result[T]) Value() T {
	if r.err != nil {
		panic("Result.Value() called on error result: " + r.err.Error())
	}

	return r.value
}

// ValueOr returns the value if defined, otherwise the fallback.
func (r Result[T]) ValueOr(fallback T) T {
	if r.defined {
		return r.value
	}

	return fallback
}

// Error returns the underlying AppError, or nil.
func (r Result[T]) Error() *AppError { return r.err }

// Unwrap bridges to the standard (T, error) pattern.
func (r Result[T]) Unwrap() (T, error) {
	if r.err != nil {
		var zero T

		return zero, r.err
	}

	return r.value, nil
}
