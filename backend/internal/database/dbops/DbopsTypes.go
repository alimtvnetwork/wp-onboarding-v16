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
// Go's time.Parse uses a reference time (Mon Jan 2 15:04:05 MST 2006) as the format template.
// The literal "2006-01-02 15:04:05" is NOT an arbitrary date — it is Go's canonical reference
// timestamp that defines the layout. See: https://pkg.go.dev/time#pkg-constants
func ParseDateTime(s string) time.Time {
	isBlank := strings.TrimSpace(s) == ""

	if isBlank {
		return time.Time{}
	}

	// sqliteDateTimeLayout is Go's reference time in SQLite's default DATETIME format.
	sqliteDateTimeLayout := "2006-01-02 15:04:05"

	t, err := time.Parse(sqliteDateTimeLayout, s)
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
	LastInsertId int64
	Created      bool
	Exists       bool
}

// OperationFields holds typed metadata for database operation logging (GE pattern).
type OperationFields struct {
	// Domain context (set by callers)
	SiteId     int64  `json:",omitempty"`
	PluginId   int64  `json:",omitempty"`
	MappingId  int64  `json:",omitempty"`
	Url        string `json:",omitempty"`
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
	Id           int64  `json:",omitempty"`
	IsCreated    bool   `json:",omitempty"`
	IsExists     bool   `json:",omitempty"`
	LastInsertId int64  `json:",omitempty"`
	Note         string `json:",omitempty"`
}

// toKeyvals converts the struct to a flat key-value slice for the logger.
func (f OperationFields) toKeyvals() []any {
	var kv []any

	// Domain fields
	hasSiteId := f.SiteId != 0
	if hasSiteId {
		kv = append(kv, "siteId", f.SiteId)
	}

	hasPluginId := f.PluginId != 0
	if hasPluginId {
		kv = append(kv, "pluginId", f.PluginId)
	}

	hasMappingId := f.MappingId != 0
	if hasMappingId {
		kv = append(kv, "mappingId", f.MappingId)
	}

	hasUrl := f.Url != ""
	if hasUrl {
		kv = append(kv, "url", f.Url)
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

	hasId := f.Id != 0
	if hasId {
		kv = append(kv, "id", f.Id)
	}

	if f.Created {
		kv = append(kv, "created", f.Created)
	}

	if f.Exists {
		kv = append(kv, "exists", f.Exists)
	}

	hasLastInsertId := f.LastInsertId != 0
	if hasLastInsertId {
		kv = append(kv, "lastInsertId", f.LastInsertId)
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

	hasSiteId := extra.SiteId != 0
	if hasSiteId {
		result.SiteId = extra.SiteId
	}

	hasPluginId := extra.PluginId != 0
	if hasPluginId {
		result.PluginId = extra.PluginId
	}

	hasMappingId := extra.MappingId != 0
	if hasMappingId {
		result.MappingId = extra.MappingId
	}

	hasUrl := extra.Url != ""
	if hasUrl {
		result.Url = extra.Url
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

	hasId := extra.Id != 0
	if hasId {
		result.Id = extra.Id
	}

	if extra.IsCreated {
		result.IsCreated = extra.IsCreated
	}

	if extra.IsExists {
		result.IsExists = extra.IsExists
	}

	hasLastInsertId := extra.LastInsertId != 0
	if hasLastInsertId {
		result.LastInsertId = extra.LastInsertId
	}

	hasNote := extra.Note != ""
	if hasNote {
		result.Note = extra.Note
	}

	return result
}
