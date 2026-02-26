package dbutil

import "wp-plugin-publish/pkg/apperror"

// ResultSet wraps a multi-row query outcome with error and stack trace.
type ResultSet[T any] struct {
	items      []T
	err        *apperror.AppError
	stackTrace string
}

// NewResultSet constructs a successful multi-row result.
func NewResultSet[T any](items []T) ResultSet[T] {
	return ResultSet[T]{items: items}
}

// NewResultSetError constructs an error result set with a captured stack trace.
func NewResultSetError[T any](err *apperror.AppError, stack string) ResultSet[T] {
	return ResultSet[T]{err: err, stackTrace: stack}
}

// IsEmpty returns true when the result contains zero items (regardless of error).
func (r ResultSet[T]) IsEmpty() bool { return len(r.items) == 0 }

// HasAny returns true when the result contains at least one item.
func (r ResultSet[T]) HasAny() bool { return len(r.items) > 0 }

// Count returns the number of items in the result.
func (r ResultSet[T]) Count() int { return len(r.items) }

// HasError returns true when the query failed.
func (r ResultSet[T]) HasError() bool { return r.err != nil }

// IsSafe returns true when there is no error (items may still be empty).
func (r ResultSet[T]) IsSafe() bool { return r.err == nil }

// Items returns the scanned items slice (nil if error).
func (r ResultSet[T]) Items() []T { return r.items }

// AppError returns the underlying *AppError, or nil.
// Named AppError (not Error) to avoid confusion with Go's native error interface.
func (r ResultSet[T]) AppError() *apperror.AppError { return r.err }

// StackTrace returns the captured stack trace if an error occurred.
func (r ResultSet[T]) StackTrace() string { return r.stackTrace }

// First returns a Result[T] for the first item, or an empty Result if no items exist.
// Propagates any error from the original query.
func (r ResultSet[T]) First() Result[T] {
	if r.err != nil {
		return NewResultError[T](r.err, r.stackTrace)
	}
	if len(r.items) == 0 {
		return Result[T]{}
	}
	return NewResult(r.items[0])
}

// ToAppResultSlice converts a dbutil.ResultSet[T] directly to an apperror.ResultSlice[T].
// Normalizes nil items to empty slice. Eliminates redundant unwrap+rewrap.
func (r ResultSet[T]) ToAppResultSlice() apperror.ResultSlice[T] {
	if r.err != nil {
		return apperror.FailSlice[T](r.err)
	}
	items := r.items
	if items == nil {
		items = []T{}
	}
	return apperror.OkSlice(items)
}
