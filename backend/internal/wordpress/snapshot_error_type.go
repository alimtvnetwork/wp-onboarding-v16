package wordpress

import "strings"

// SnapshotErrorType represents snapshot operation error codes.
type SnapshotErrorType string

const (
	// SnapshotErrorLockExists indicates a snapshot lock already exists.
	SnapshotErrorLockExists SnapshotErrorType = "SNAPSHOT_LOCK_EXISTS"

	// SnapshotErrorNotFound indicates the snapshot was not found.
	SnapshotErrorNotFound SnapshotErrorType = "SNAPSHOT_NOT_FOUND"

	// SnapshotErrorCorrupt indicates the snapshot is corrupt.
	SnapshotErrorCorrupt SnapshotErrorType = "SNAPSHOT_CORRUPT"

	// SnapshotErrorTooLarge indicates the snapshot exceeds size limits.
	SnapshotErrorTooLarge SnapshotErrorType = "SNAPSHOT_TOO_LARGE"

	// SnapshotErrorRestoreFailed indicates a restore operation failed.
	SnapshotErrorRestoreFailed SnapshotErrorType = "RESTORE_FAILED"

	// SnapshotErrorRestoreNoConfirm indicates restore was not confirmed.
	SnapshotErrorRestoreNoConfirm SnapshotErrorType = "RESTORE_NO_CONFIRM"

	// SnapshotErrorProviderNotAvail indicates the provider is not available.
	SnapshotErrorProviderNotAvail SnapshotErrorType = "PROVIDER_NOT_AVAILABLE"

	// SnapshotErrorIncrementalNoParent indicates no parent snapshot for incremental.
	SnapshotErrorIncrementalNoParent SnapshotErrorType = "INCREMENTAL_NO_PARENT"

	// SnapshotErrorExportNotFound indicates the export was not found.
	SnapshotErrorExportNotFound SnapshotErrorType = "EXPORT_NOT_FOUND"

	// SnapshotErrorExportBuildFailed indicates the export build failed.
	SnapshotErrorExportBuildFailed SnapshotErrorType = "EXPORT_BUILD_FAILED"

	// SnapshotErrorExportTokenInvalid indicates the export token is invalid.
	SnapshotErrorExportTokenInvalid SnapshotErrorType = "EXPORT_TOKEN_INVALID"
)

// IsEqual checks type-safe equality against another SnapshotErrorType.
func (s SnapshotErrorType) IsEqual(other SnapshotErrorType) bool {
	return s == other
}

// String returns the raw string value.
func (s SnapshotErrorType) String() string {
	return string(s)
}

// IsExport returns true if this is an export-related error.
func (s SnapshotErrorType) IsExport() bool {
	return strings.HasPrefix(string(s), "EXPORT_")
}

// IsRestore returns true if this is a restore-related error.
func (s SnapshotErrorType) IsRestore() bool {
	return strings.HasPrefix(string(s), "RESTORE_")
}
