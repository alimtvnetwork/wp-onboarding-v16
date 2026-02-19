package dbutil

// ResultSet wraps a multi-row query outcome with error and stack trace.
type ResultSet[T any] struct {
	items      []T
	err        error
	stackTrace string
}

// NewResultSet constructs a successful multi-row result.
func NewResultSet[T any](items []T) ResultSet[T] {
	return ResultSet[T]{items: items}
}

// NewResultSetError constructs an error result set with a captured stack trace.
func NewResultSetError[T any](err error, stack string) ResultSet[T] {
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

// Error returns the underlying error, or nil.
func (r ResultSet[T]) Error() error { return r.err }

// StackTrace returns the captured stack trace if an error occurred.
func (r ResultSet[T]) StackTrace() string { return r.stackTrace }
