package dbops

import (
	"database/sql"
	"fmt"
	"runtime"
	"strings"
	"time"

	"wp-plugin-publish/internal/logger"
)

// ParseDateTime parses SQLite datetime strings into time.Time.
func ParseDateTime(s string) time.Time {
	if strings.TrimSpace(s) == "" {
		return time.Time{}
	}
	if t, err := time.Parse("2006-01-02 15:04:05", s); err == nil {
		return t
	}
	if t, err := time.Parse(time.RFC3339, s); err == nil {
		return t
	}
	return time.Time{}
}

// ParseNullTime converts sql.NullString to *time.Time using ParseDateTime.
func ParseNullTime(ns sql.NullString) *time.Time {
	isInvalid := !ns.Valid
	isBlank := strings.TrimSpace(ns.String) == ""
	if isInvalid || isBlank {
		return nil
	}
	t := ParseDateTime(ns.String)
	if t.IsZero() {
		return nil
	}
	return &t
}

// Result represents the outcome of a database operation
type Result struct {
	AffectedRows int64
	LastInsertID int64
	Created      bool
	Exists       bool
}

// OperationFields holds typed metadata for database operation logging (GE pattern).
type OperationFields struct {
	// Domain context (set by callers)
	SiteID     int64  `json:",omitempty"`
	PluginID   int64  `json:",omitempty"`
	MappingID  int64  `json:",omitempty"`
	URL        string `json:",omitempty"`
	Path       string `json:",omitempty"`
	RemoteSlug string `json:",omitempty"`
	PluginName string `json:",omitempty"`
	SiteName   string `json:",omitempty"`
	Version    string `json:",omitempty"`
	Category   string `json:",omitempty"`

	// Operation context (set internally by dbops)
	Table        string `json:",omitempty"`
	Operation    string `json:",omitempty"`
	AffectedRows int64  `json:",omitempty"`
	Caller       string `json:",omitempty"`
	Error        string `json:",omitempty"`
	StackTrace   string `json:",omitempty"`
	ID           int64  `json:",omitempty"`
	IsCreated    bool   `json:",omitempty"`
	IsExists     bool   `json:",omitempty"`
	LastInsertID int64  `json:",omitempty"`
	Note         string `json:",omitempty"`
}

// toKeyvals converts the struct to a flat key-value slice for the logger.
func (f OperationFields) toKeyvals() []any {
	var kv []any
	// Domain fields
	if f.SiteID != 0 {
		kv = append(kv, "siteId", f.SiteID)
	}
	if f.PluginID != 0 {
		kv = append(kv, "pluginId", f.PluginID)
	}
	if f.MappingID != 0 {
		kv = append(kv, "mappingId", f.MappingID)
	}
	if f.URL != "" {
		kv = append(kv, "url", f.URL)
	}
	if f.Path != "" {
		kv = append(kv, "path", f.Path)
	}
	if f.RemoteSlug != "" {
		kv = append(kv, "remoteSlug", f.RemoteSlug)
	}
	if f.PluginName != "" {
		kv = append(kv, "pluginName", f.PluginName)
	}
	if f.SiteName != "" {
		kv = append(kv, "siteName", f.SiteName)
	}
	if f.Version != "" {
		kv = append(kv, "version", f.Version)
	}
	if f.Category != "" {
		kv = append(kv, "category", f.Category)
	}
	// Operation fields
	if f.Table != "" {
		kv = append(kv, "table", f.Table)
	}
	if f.Operation != "" {
		kv = append(kv, "operation", f.Operation)
	}
	if f.AffectedRows != 0 {
		kv = append(kv, "affectedRows", f.AffectedRows)
	}
	if f.Caller != "" {
		kv = append(kv, "caller", f.Caller)
	}
	if f.Error != "" {
		kv = append(kv, "error", f.Error)
	}
	if f.StackTrace != "" {
		kv = append(kv, "stackTrace", f.StackTrace)
	}
	if f.ID != 0 {
		kv = append(kv, "id", f.ID)
	}
	if f.Created {
		kv = append(kv, "created", f.Created)
	}
	if f.Exists {
		kv = append(kv, "exists", f.Exists)
	}
	if f.LastInsertID != 0 {
		kv = append(kv, "lastInsertId", f.LastInsertID)
	}
	if f.Note != "" {
		kv = append(kv, "note", f.Note)
	}
	return kv
}

// Context provides metadata for logging database operations
type Context struct {
	Table     string          // Table name for logging
	Operation string          // Operation type: INSERT, UPDATE, DELETE, SELECT
	Logger    *logger.Logger  // Logger instance for output
	Fields    OperationFields // Additional fields to log
}

// captureStackTrace returns a formatted stack trace string from the call site
func captureStackTrace(skip int) string {
	var stack strings.Builder
	for i := skip; i < skip+10; i++ {
		pc, file, line, ok := runtime.Caller(i)
		if !ok {
			break
		}
		fn := runtime.FuncForPC(pc)
		fnName := "unknown"
		if fn != nil {
			fnName = fn.Name()
			idx := strings.LastIndex(fnName, "/")
			if idx != -1 {
				fnName = fnName[idx+1:]
			}
		}
		fileIdx := strings.LastIndex(file, "/")
		if fileIdx != -1 {
			file = file[fileIdx+1:]
		}
		stack.WriteString(fmt.Sprintf("  at %s (%s:%d)\n", fnName, file, line))
	}
	return stack.String()
}

// getCallerInfo returns the caller's file and line for logging
func getCallerInfo(skip int) (file string, line int) {
	_, file, line, ok := runtime.Caller(skip)
	if !ok {
		return "unknown", 0
	}
	idx := strings.LastIndex(file, "/")
	if idx != -1 {
		file = file[idx+1:]
	}
	return file, line
}

// mergeFields overlays non-zero extra fields onto base.
func mergeFields(base, extra OperationFields) OperationFields {
	result := base
	if extra.SiteID != 0 {
		result.SiteID = extra.SiteID
	}
	if extra.PluginID != 0 {
		result.PluginID = extra.PluginID
	}
	if extra.MappingID != 0 {
		result.MappingID = extra.MappingID
	}
	if extra.URL != "" {
		result.URL = extra.URL
	}
	if extra.Path != "" {
		result.Path = extra.Path
	}
	if extra.RemoteSlug != "" {
		result.RemoteSlug = extra.RemoteSlug
	}
	if extra.PluginName != "" {
		result.PluginName = extra.PluginName
	}
	if extra.SiteName != "" {
		result.SiteName = extra.SiteName
	}
	if extra.Version != "" {
		result.Version = extra.Version
	}
	if extra.Category != "" {
		result.Category = extra.Category
	}
	if extra.Table != "" {
		result.Table = extra.Table
	}
	if extra.Operation != "" {
		result.Operation = extra.Operation
	}
	if extra.AffectedRows != 0 {
		result.AffectedRows = extra.AffectedRows
	}
	if extra.Caller != "" {
		result.Caller = extra.Caller
	}
	if extra.Error != "" {
		result.Error = extra.Error
	}
	if extra.StackTrace != "" {
		result.StackTrace = extra.StackTrace
	}
	if extra.ID != 0 {
		result.ID = extra.ID
	}
	if extra.IsCreated {
		result.IsCreated = extra.IsCreated
	}
	if extra.IsExists {
		result.IsExists = extra.IsExists
	}
	if extra.LastInsertID != 0 {
		result.LastInsertID = extra.LastInsertID
	}
	if extra.Note != "" {
		result.Note = extra.Note
	}
	return result
}
