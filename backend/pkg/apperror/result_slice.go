package apperror

// ResultSlice wraps a slice of items with an optional error.
// Use for service methods that return lists/collections.
type ResultSlice[T any] struct {
	items []T
	err   *AppError
}

// OkSlice creates a successful ResultSlice containing the given items.
func OkSlice[T any](items []T) ResultSlice[T] {
	return ResultSlice[T]{items: items}
}

// FailSlice creates a failed ResultSlice from an AppError.
func FailSlice[T any](err *AppError) ResultSlice[T] {
	return ResultSlice[T]{err: err}
}

// FailSliceWrap creates a failed ResultSlice by wrapping a raw error.
// Uses skip=3 to attribute the stack trace to the actual caller.
func FailSliceWrap[T any](cause error, code, message string) ResultSlice[T] {
	return ResultSlice[T]{err: WrapWithSkip(cause, code, message, 0)}
}

// FailSliceNew creates a failed ResultSlice from a new error (no cause).
func FailSliceNew[T any](code, message string) ResultSlice[T] {
	return ResultSlice[T]{err: NewWithSkip(code, message, 1)}
}

// HasError returns true when the operation failed.
func (r ResultSlice[T]) HasError() bool { return r.err != nil }

// IsSafe returns true when there is no error (items may be empty).
func (r ResultSlice[T]) IsSafe() bool { return r.err == nil }

// HasItems returns true when the slice contains at least one item.
func (r ResultSlice[T]) HasItems() bool { return len(r.items) > 0 }

// IsEmpty returns true when the slice has zero items.
func (r ResultSlice[T]) IsEmpty() bool { return len(r.items) == 0 }

// Count returns the number of items.
func (r ResultSlice[T]) Count() int { return len(r.items) }

// Items returns the underlying slice (nil if error).
func (r ResultSlice[T]) Items() []T { return r.items }

// First returns a Result[T] for the first item, or empty if no items.
func (r ResultSlice[T]) First() Result[T] {
	if r.err != nil {
		return Fail[T](r.err)
	}
	if len(r.items) == 0 {
		return Result[T]{}
	}

	return Ok(r.items[0])
}

// Last returns a Result[T] for the last item, or empty if no items.
func (r ResultSlice[T]) Last() Result[T] {
	if r.err != nil {
		return Fail[T](r.err)
	}
	if len(r.items) == 0 {
		return Result[T]{}
	}

	return Ok(r.items[len(r.items)-1])
}

// GetAt returns a Result[T] for the item at the given index.
// Returns empty Result if index is out of bounds.
func (r ResultSlice[T]) GetAt(index int) Result[T] {
	if r.err != nil {
		return Fail[T](r.err)
	}

	isOutOfBounds := index < 0 || index >= len(r.items)
	if isOutOfBounds {
		return Result[T]{}
	}

	return Ok(r.items[index])
}

// Error returns the underlying AppError, or nil.
func (r ResultSlice[T]) Error() *AppError { return r.err }

// Append adds items to the slice. No-op if in error state.
func (r *ResultSlice[T]) Append(items ...T) {
	if r.err != nil {
		return
	}

	r.items = append(r.items, items...)
}
