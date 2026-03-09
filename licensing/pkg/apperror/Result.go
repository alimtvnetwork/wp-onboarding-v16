package apperror

// Result wraps a single value with an optional AppError.
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
func FailWrap[T any](cause error, code ErrorCode, message string) Result[T] {
	return Result[T]{err: Wrap(cause, code, message)}
}

// HasError returns true when the operation failed.
func (r Result[T]) HasError() bool { return r.err != nil }

// Value returns the contained value. Panics if HasError is true.
func (r Result[T]) Value() T {
	if r.err != nil {
		panic("Result.Value() called on error result: " + r.err.Error())
	}

	return r.value
}

// AppError returns the underlying AppError, or nil.
func (r Result[T]) AppError() *AppError { return r.err }
