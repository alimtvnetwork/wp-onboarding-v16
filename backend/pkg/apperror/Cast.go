// Package apperror — safe type assertion utilities.
package apperror

import "fmt"

// Cast performs a safe type assertion from any to T.
// Returns Result[T] with a typed error if the assertion fails.
// Uses skip=1 so the stack trace points to the caller of Cast, not Cast itself.
func Cast[T any](value any) Result[T] {
	result, ok := value.(T)
	if !ok {
		return FailNew[T](ErrTypeCast, fmt.Sprintf("type assertion failed: expected %T, got %T", *new(T), value))
	}

	return Ok(result)
}

// CastSlice performs a safe type assertion from any to []T.
// Returns ResultSlice[T] with a typed error if the assertion fails.
func CastSlice[T any](value any) ResultSlice[T] {
	result, ok := value.([]T)
	if !ok {
		return FailSliceNew[T](ErrTypeCast, fmt.Sprintf("slice type assertion failed: expected []%T, got %T", *new(T), value))
	}

	return OkSlice(result)
}
