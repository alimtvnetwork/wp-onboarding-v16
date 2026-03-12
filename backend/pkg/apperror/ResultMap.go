package apperror

// ResultMap wraps a map of key-value pairs with an optional error.
// Use for service methods that return associative data.
type ResultMap[K comparable, V any] struct {
	items map[K]V
	err   *AppError
}

// OkMap creates a successful ResultMap containing the given map.
func OkMap[K comparable, V any](items map[K]V) ResultMap[K, V] {
	return ResultMap[K, V]{items: items}
}

// FailMap creates a failed ResultMap from an AppError.
func FailMap[K comparable, V any](err *AppError) ResultMap[K, V] {
	return ResultMap[K, V]{err: err}
}

// FailMapWrap creates a failed ResultMap by wrapping a raw error.
// Uses skip=3 to attribute the stack trace to the actual caller.
func FailMapWrap[K comparable, V any](cause error, code ErrorCode, message string) ResultMap[K, V] {
	return ResultMap[K, V]{err: WrapWithSkip(cause, code, message, 0)}
}

// FailMapNew creates a failed ResultMap from a new error (no cause).
func FailMapNew[K comparable, V any](code ErrorCode, message string) ResultMap[K, V] {
	return ResultMap[K, V]{err: NewWithSkip(code, message, 1)}
}

// HasError returns true when the operation failed.
func (r ResultMap[K, V]) HasError() bool { return r.err != nil }

// IsSafe returns true when there is no error (map may be empty).
func (r ResultMap[K, V]) IsSafe() bool { return r.err == nil }

// HasItems returns true when the map contains at least one entry.
func (r ResultMap[K, V]) HasItems() bool { return len(r.items) > 0 }

// IsEmpty returns true when the map has zero entries.
func (r ResultMap[K, V]) IsEmpty() bool { return len(r.items) == 0 }

// Count returns the number of entries in the map.
func (r ResultMap[K, V]) Count() int { return len(r.items) }

// Items returns the underlying map (nil if error).
func (r ResultMap[K, V]) Items() map[K]V { return r.items }

// Get returns a Result[V] for the given key. Empty if key not found.
func (r ResultMap[K, V]) Get(key K) Result[V] {
	if r.err != nil {
		return Fail[V](r.err)
	}

	val, exists := r.items[key]

	if !exists {
		return Result[V]{}
	}

	return Ok(val)
}

// Has returns true if the key exists in the map.
func (r ResultMap[K, V]) Has(key K) bool {
	if r.err != nil {
		return false
	}

	_, exists := r.items[key]

	return exists
}

// Set adds or updates an entry. No-op if in error state.
func (r *ResultMap[K, V]) Set(key K, value V) {
	if r.err != nil {
		return
	}
	if r.items == nil {
		r.items = make(map[K]V)
	}

	r.items[key] = value
}

// Remove deletes a key from the map. No-op if in error state.
func (r *ResultMap[K, V]) Remove(key K) {
	if r.err != nil {
		return
	}

	delete(r.items, key)
}

// Keys returns all map keys as a slice.
func (r ResultMap[K, V]) Keys() []K {
	if r.err != nil {
		return nil
	}

	keys := make([]K, 0, len(r.items))
	for k := range r.items {
		keys = append(keys, k)
	}

	return keys
}

// Values returns all map values as a slice.
func (r ResultMap[K, V]) Values() []V {
	if r.err != nil {
		return nil
	}

	vals := make([]V, 0, len(r.items))
	for _, v := range r.items {
		vals = append(vals, v)
	}

	return vals
}

// AppError returns the underlying AppError, or nil.
// Named AppError (not Error) to avoid confusion with Go's native error interface.
func (r ResultMap[K, V]) AppError() *AppError { return r.err }
