package wordpress

// StatusType represents operation status values.
type StatusType string

const (
	// StatusSuccess indicates the operation succeeded.
	StatusSuccess StatusType = "success"

	// StatusFailed indicates the operation failed.
	StatusFailed StatusType = "failed"
)

// IsEqual checks type-safe equality against another StatusType.
func (s StatusType) IsEqual(other StatusType) bool {
	return s == other
}

// String returns the raw string value.
func (s StatusType) String() string {
	return string(s)
}

// IsSuccess returns true if the status is Success.
func (s StatusType) IsSuccess() bool {
	return s.IsEqual(StatusSuccess)
}

// IsFailed returns true if the status is Failed.
func (s StatusType) IsFailed() bool {
	return s.IsEqual(StatusFailed)
}
