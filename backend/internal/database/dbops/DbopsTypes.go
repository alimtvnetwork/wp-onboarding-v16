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
	isBlank := strings.TrimSpace(s) == ""

	if isBlank {

		return time.Time{}
	}

	t, err := time.Parse("2006-01-02 15:04:05", s)
	isParsed := err == nil

	if isParsed {

		return t
	}

	t, err = time.Parse(time.RFC3339, s)
	isParsed = err == nil

	if isParsed {

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
	hasSiteID := f.SiteID != 0
	if hasSiteID {
		kv = append(kv, "siteId", f.SiteID)
	}

	hasPluginID := f.PluginID != 0
	if hasPluginID {
		kv = append(kv, "pluginId", f.PluginID)
	}

	hasMappingID := f.MappingID != 0
	if hasMappingID {
		kv = append(kv, "mappingId", f.MappingID)
	}

	hasURL := f.URL != ""
	if hasURL {
		kv = append(kv, "url", f.URL)
	}

	hasPath := f.Path != ""
	if hasPath {
		kv = append(kv, "path", f.Path)
	}

	hasRemoteSlug := f.RemoteSlug != ""
	if hasRemoteSlug {
		kv = append(kv, "remoteSlug", f.RemoteSlug)
	}

	hasPluginName := f.PluginName != ""
	if hasPluginName {
		kv = append(kv, "pluginName", f.PluginName)
	}

	hasSiteName := f.SiteName != ""
	if hasSiteName {
		kv = append(kv, "siteName", f.SiteName)
	}

	hasVersion := f.Version != ""
	if hasVersion {
		kv = append(kv, "version", f.Version)
	}

	hasCategory := f.Category != ""
	if hasCategory {
		kv = append(kv, "category", f.Category)
	}

	// Operation fields
	hasTable := f.Table != ""
	if hasTable {
		kv = append(kv, "table", f.Table)
	}

	hasOperation := f.Operation != ""
	if hasOperation {
		kv = append(kv, "operation", f.Operation)
	}

	hasAffectedRows := f.AffectedRows != 0
	if hasAffectedRows {
		kv = append(kv, "affectedRows", f.AffectedRows)
	}

	hasCaller := f.Caller != ""
	if hasCaller {
		kv = append(kv, "caller", f.Caller)
	}

	hasError := f.Error != ""
	if hasError {
		kv = append(kv, "error", f.Error)
	}

	hasStackTrace := f.StackTrace != ""
	if hasStackTrace {
		kv = append(kv, "stackTrace", f.StackTrace)
	}

	hasID := f.ID != 0
	if hasID {
		kv = append(kv, "id", f.ID)
	}

	if f.Created {
		kv = append(kv, "created", f.Created)
	}

	if f.Exists {
		kv = append(kv, "exists", f.Exists)
	}

	hasLastInsertID := f.LastInsertID != 0
	if hasLastInsertID {
		kv = append(kv, "lastInsertId", f.LastInsertID)
	}

	hasNote := f.Note != ""
	if hasNote {
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
		pc, file, line, isValid := runtime.Caller(i)
		isInvalid := !isValid

		if isInvalid {
			break
		}

		fn := runtime.FuncForPC(pc)
		fnName := "unknown"

		hasFn := fn != nil
		if hasFn {
			fnName = fn.Name()
			idx := strings.LastIndex(fnName, "/")
			hasSlash := idx != -1

			if hasSlash {
				fnName = fnName[idx+1:]
			}
		}

		fileIdx := strings.LastIndex(file, "/")
		hasFileSlash := fileIdx != -1

		if hasFileSlash {
			file = file[fileIdx+1:]
		}

		stack.WriteString(fmt.Sprintf("  at %s (%s:%d)\n", fnName, file, line))
	}

	return stack.String()
}

// getCallerInfo returns the caller's file and line for logging
func getCallerInfo(skip int) (file string, line int) {
	_, file, line, isValid := runtime.Caller(skip)
	isInvalid := !isValid

	if isInvalid {

		return "unknown", 0
	}

	idx := strings.LastIndex(file, "/")
	hasSlash := idx != -1

	if hasSlash {
		file = file[idx+1:]
	}

	return file, line
}

// mergeFields overlays non-zero extra fields onto base.
func mergeFields(base, extra OperationFields) OperationFields {
	result := base

	hasSiteID := extra.SiteID != 0
	if hasSiteID {
		result.SiteID = extra.SiteID
	}

	hasPluginID := extra.PluginID != 0
	if hasPluginID {
		result.PluginID = extra.PluginID
	}

	hasMappingID := extra.MappingID != 0
	if hasMappingID {
		result.MappingID = extra.MappingID
	}

	hasURL := extra.URL != ""
	if hasURL {
		result.URL = extra.URL
	}

	hasPath := extra.Path != ""
	if hasPath {
		result.Path = extra.Path
	}

	hasRemoteSlug := extra.RemoteSlug != ""
	if hasRemoteSlug {
		result.RemoteSlug = extra.RemoteSlug
	}

	hasPluginName := extra.PluginName != ""
	if hasPluginName {
		result.PluginName = extra.PluginName
	}

	hasSiteName := extra.SiteName != ""
	if hasSiteName {
		result.SiteName = extra.SiteName
	}

	hasVersion := extra.Version != ""
	if hasVersion {
		result.Version = extra.Version
	}

	hasCategory := extra.Category != ""
	if hasCategory {
		result.Category = extra.Category
	}

	hasTable := extra.Table != ""
	if hasTable {
		result.Table = extra.Table
	}

	hasOperation := extra.Operation != ""
	if hasOperation {
		result.Operation = extra.Operation
	}

	hasAffectedRows := extra.AffectedRows != 0
	if hasAffectedRows {
		result.AffectedRows = extra.AffectedRows
	}

	hasCaller := extra.Caller != ""
	if hasCaller {
		result.Caller = extra.Caller
	}

	hasError := extra.Error != ""
	if hasError {
		result.Error = extra.Error
	}

	hasStackTrace := extra.StackTrace != ""
	if hasStackTrace {
		result.StackTrace = extra.StackTrace
	}

	hasID := extra.ID != 0
	if hasID {
		result.ID = extra.ID
	}

	if extra.IsCreated {
		result.IsCreated = extra.IsCreated
	}

	if extra.IsExists {
		result.IsExists = extra.IsExists
	}

	hasLastInsertID := extra.LastInsertID != 0
	if hasLastInsertID {
		result.LastInsertID = extra.LastInsertID
	}

	hasNote := extra.Note != ""
	if hasNote {
		result.Note = extra.Note
	}

	return result
}
