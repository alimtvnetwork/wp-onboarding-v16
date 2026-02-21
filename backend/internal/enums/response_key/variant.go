package responsekey

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents standardized response array keys.
type Variant byte

const (
	Invalid         Variant = iota
	Success
	Error
	Message
	Data
	Code
	Valid
	Errors
	Cached
	Phase
	Reason
	Total
	Agents
	Actions
	Logs
	Snapshots
	Sql
	Params
	Sets
	Plugins
	Tables
	Rows
	Bytes
	Size
	FileSize
	Path
	Filename
	Checksum
	Duration
	Count
	Files
	Directory
	Scope
	Exported
	Entry
	Computed
	Removed
	SnapshotId
	Sequence
	FolderName
	TablesChanged
	TotalRows
	TotalNewRows
	ZipSize
	BackupId
	ZipFailed
	SkipAudit
	TablesRestored
)

var variantStrings = [...]string{
	Invalid:        "invalid",
	Success:        "success",
	Error:          "error",
	Message:        "message",
	Data:           "data",
	Code:           "code",
	Valid:          "valid",
	Errors:         "errors",
	Cached:         "cached",
	Phase:          "phase",
	Reason:         "reason",
	Total:          "total",
	Agents:         "agents",
	Actions:        "actions",
	Logs:           "logs",
	Snapshots:      "snapshots",
	Sql:            "sql",
	Params:         "params",
	Sets:           "sets",
	Plugins:        "plugins",
	Tables:         "tables",
	Rows:           "rows",
	Bytes:          "bytes",
	Size:           "size",
	FileSize:       "file_size",
	Path:           "path",
	Filename:       "filename",
	Checksum:       "checksum",
	Duration:       "duration",
	Count:          "count",
	Files:          "files",
	Directory:      "directory",
	Scope:          "scope",
	Exported:       "exported",
	Entry:          "entry",
	Computed:       "computed",
	Removed:        "removed",
	SnapshotId:     "snapshot_id",
	Sequence:       "sequence",
	FolderName:     "folder_name",
	TablesChanged:  "tables_changed",
	TotalRows:      "total_rows",
	TotalNewRows:   "total_new_rows",
	ZipSize:        "zip_size",
	BackupId:       "backup_id",
	ZipFailed:      "zip_failed",
	SkipAudit:      "skip_audit",
	TablesRestored: "tables_restored",
}

var variantLabels = [...]string{
	Invalid:        "Invalid Key",
	Success:        "Success",
	Error:          "Error",
	Message:        "Message",
	Data:           "Data",
	Code:           "Code",
	Valid:          "Valid",
	Errors:         "Errors",
	Cached:         "Cached",
	Phase:          "Phase",
	Reason:         "Reason",
	Total:          "Total",
	Agents:         "Agents",
	Actions:        "Actions",
	Logs:           "Logs",
	Snapshots:      "Snapshots",
	Sql:            "SQL",
	Params:         "Params",
	Sets:           "Sets",
	Plugins:        "Plugins",
	Tables:         "Tables",
	Rows:           "Rows",
	Bytes:          "Bytes",
	Size:           "Size",
	FileSize:       "File Size",
	Path:           "Path",
	Filename:       "Filename",
	Checksum:       "Checksum",
	Duration:       "Duration",
	Count:          "Count",
	Files:          "Files",
	Directory:      "Directory",
	Scope:          "Scope",
	Exported:       "Exported",
	Entry:          "Entry",
	Computed:       "Computed",
	Removed:        "Removed",
	SnapshotId:     "Snapshot ID",
	Sequence:       "Sequence",
	FolderName:     "Folder Name",
	TablesChanged:  "Tables Changed",
	TotalRows:      "Total Rows",
	TotalNewRows:   "Total New Rows",
	ZipSize:        "ZIP Size",
	BackupId:       "Backup ID",
	ZipFailed:      "ZIP Failed",
	SkipAudit:      "Skip Audit",
	TablesRestored: "Tables Restored",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsInvalid() bool { return v == Invalid }

// IsOtherThan returns true if this key differs from the given key.
func (v Variant) IsOtherThan(other Variant) bool {
	return v != other
}

func All() []Variant {
	all := make([]Variant, 0, len(variantStrings)-1)
	for i := 1; i < len(variantStrings); i++ {
		all = append(all, Variant(i))
	}
	return all
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	lower := strings.ToLower(strings.TrimSpace(s))
	for i, str := range variantStrings {
		if str == lower {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid response key: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.String())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	if err := json.Unmarshal(data, &s); err != nil {
		return err
	}
	parsed, err := Parse(s)
	if err != nil {
		return err
	}
	*v = parsed
	return nil
}
