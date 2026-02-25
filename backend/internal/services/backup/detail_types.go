// Package backup — typed detail structs for broadcast log calls.
// These replace inline map[string]any literals at call sites,
// ensuring type safety per the Generic Enforce Pattern (GE-1).
package backup

import "encoding/json"

// toDetails marshals a typed struct into json.RawMessage for WS broadcast boundaries.
func toDetails[T any](v T) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// InitDetails carries backup/restore init context.
type InitDetails struct {
	MappingID int64 `json:",omitempty"`
	BackupID  int64 `json:",omitempty"`
}

// PathDetails carries a file path context.
type PathDetails struct {
	Path string `json:",omitempty"`
}

// RetentionDetails carries retention policy context.
type RetentionDetails struct {
	MaxPerPlugin  int `json:",omitempty"`
	RetentionDays int `json:",omitempty"`
}

// BackupCompleteDetails carries backup completion context.
type BackupCompleteDetails struct {
	Path     string `json:",omitempty"`
	FileSize int64  `json:",omitempty"`
}

// CleanupInitDetails carries cleanup init context.
type CleanupInitDetails struct {
	RetentionDays int `json:",omitempty"`
}

// ExpiredBackupDetails carries expired backup removal context.
type ExpiredBackupDetails struct {
	ModifiedAt string `json:",omitempty"`
}

// CleanupCompleteDetails carries cleanup completion context.
type CleanupCompleteDetails struct {
	RemovedCount int `json:",omitempty"`
}

// ExportInitDetails carries export init context.
type ExportInitDetails struct {
	SourceCount int `json:",omitempty"`
}

// ExportErrorDetails carries export error context.
type ExportErrorDetails struct {
	Error string `json:",omitempty"`
}

// ExportCompleteDetails carries export completion context.
type ExportCompleteDetails struct {
	FilesCount int   `json:",omitempty"`
	TotalBytes int64 `json:",omitempty"`
	DurationMs int64 `json:",omitempty"`
}

// ImportInitDetails carries import init context.
type ImportInitDetails struct {
	Destination string `json:",omitempty"`
	Overwrite   bool   `json:",omitempty"`
}

// ImportCompleteDetails carries import completion context.
type ImportCompleteDetails struct {
	FilesCount int   `json:",omitempty"`
	TotalBytes int64 `json:",omitempty"`
	DurationMs int64 `json:",omitempty"`
}
