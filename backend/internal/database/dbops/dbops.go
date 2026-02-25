// Package dbops provides shared database operation utilities with comprehensive logging,
// stack trace capture, and affected rows tracking. This is the single source of truth
// for all database operations requiring detailed traceability.
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
	if !ns.Valid || strings.TrimSpace(ns.String) == "" {
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
			if idx := strings.LastIndex(fnName, "/"); idx != -1 {
				fnName = fnName[idx+1:]
			}
		}
		if idx := strings.LastIndex(file, "/"); idx != -1 {
			file = file[idx+1:]
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
	if idx := strings.LastIndex(file, "/"); idx != -1 {
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

// ExecInsert executes an INSERT operation and returns detailed result with logging
func ExecInsert(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "INSERT"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err, query, args)
		return nil, err
	}

	rows, _ := result.RowsAffected()
	id, _ := result.LastInsertId()

	res := &Result{
		AffectedRows: rows,
		LastInsertID: id,
		Created:      rows > 0,
		Exists:       rows == 0,
	}

	logSuccess(ctx, res, query, args)
	return res, nil
}

// ExecUpdate executes an UPDATE operation and returns detailed result with logging
func ExecUpdate(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "UPDATE"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err, query, args)
		return nil, err
	}

	rows, _ := result.RowsAffected()

	res := &Result{
		AffectedRows: rows,
	}

	logSuccess(ctx, res, query, args)
	return res, nil
}

// ExecDelete executes a DELETE operation and returns detailed result with logging
func ExecDelete(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "DELETE"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err, query, args)
		return nil, err
	}

	rows, _ := result.RowsAffected()

	res := &Result{
		AffectedRows: rows,
	}

	logSuccess(ctx, res, query, args)
	return res, nil
}

// FindOrCreate attempts to find an existing record by key, or creates it if not found
func FindOrCreate(
	db interface {
		QueryRow(string, ...any) *sql.Row
		Exec(string, ...any) (sql.Result, error)
	},
	ctx Context,
	selectQuery string,
	selectArgs []any,
	insertQuery string,
	insertArgs []any,
) (int64, bool, error) {
	var id int64
	err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
	if err == nil {
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "FIND",
				ID:        id,
				Exists:    true,
			})
			ctx.Logger.Debug("Record found (exists)", fields.toKeyvals()...)
		}
		return id, false, nil
	}

	if err != sql.ErrNoRows {
		logError(ctx, err, selectQuery, selectArgs)
		return 0, false, err
	}

	ctx.Operation = "INSERT"
	result, err := db.Exec(insertQuery, insertArgs...)
	if err != nil {
		logError(ctx, err, insertQuery, insertArgs)
		return 0, false, err
	}

	rows, _ := result.RowsAffected()
	id, _ = result.LastInsertId()

	if rows == 0 {
		err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
		if err != nil {
			logError(ctx, err, selectQuery, selectArgs)
			return 0, false, err
		}
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "FIND_AFTER_RACE",
				ID:        id,
				Exists:    true,
			})
			ctx.Logger.Debug("Record found after race condition", fields.toKeyvals()...)
		}
		return id, false, nil
	}

	res := &Result{
		AffectedRows: rows,
		LastInsertID: id,
		Created:      true,
	}
	logSuccess(ctx, res, insertQuery, insertArgs)
	return id, true, nil
}

// CreateMapping creates a many-to-many relationship record with proper logging
func CreateMapping(
	db interface{ Exec(string, ...any) (sql.Result, error) },
	ctx Context,
	query string,
	args ...any,
) (bool, error) {
	ctx.Operation = "INSERT_MAPPING"

	result, err := db.Exec(query, args...)
	if err != nil {
		errStr := err.Error()
		isConstraintViolation := strings.Contains(errStr, "UNIQUE") ||
			strings.Contains(errStr, "constraint") ||
			strings.Contains(errStr, "duplicate")

		if isConstraintViolation {
			if ctx.Logger != nil {
				fields := mergeFields(ctx.Fields, OperationFields{
					Table:     ctx.Table,
					Operation: "INSERT_MAPPING",
					Exists:    true,
					Note:      "Mapping already exists (constraint)",
				})
				ctx.Logger.Debug("Mapping exists", fields.toKeyvals()...)
			}
			return false, nil
		}

		logError(ctx, err, query, args)
		return false, err
	}

	rows, _ := result.RowsAffected()
	created := rows > 0

	if created {
		if ctx.Logger != nil {
			id, _ := result.LastInsertId()
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:        ctx.Table,
				Operation:    "INSERT_MAPPING",
				AffectedRows: rows,
				ID:           id,
				Created:      true,
			})
			ctx.Logger.Info("Mapping CREATED", fields.toKeyvals()...)
		}
	} else {
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "INSERT_MAPPING",
				Exists:    true,
				Note:      "Mapping already exists (INSERT OR IGNORE)",
			})
			ctx.Logger.Debug("Mapping EXISTS", fields.toKeyvals()...)
		}
	}

	return created, nil
}

// logSuccess logs a successful database operation
func logSuccess(ctx Context, res *Result, query string, args []any) {
	if ctx.Logger == nil {
		return
	}

	file, line := getCallerInfo(3)

	fields := mergeFields(ctx.Fields, OperationFields{
		Table:        ctx.Table,
		Operation:    ctx.Operation,
		AffectedRows: res.AffectedRows,
		Caller:       fmt.Sprintf("%s:%d", file, line),
	})

	if res.LastInsertID > 0 {
		fields.LastInsertID = res.LastInsertID
	}
	if res.IsCreated {
		fields.IsCreated = true
	}
	if res.IsExists {
		fields.IsExists = true
	}

	ctx.Logger.Debug(fmt.Sprintf("DB %s on %s", ctx.Operation, ctx.Table), fields.toKeyvals()...)
}

// logError logs a failed database operation with stack trace
func logError(ctx Context, err error, query string, args []any) {
	if ctx.Logger == nil {
		return
	}

	file, line := getCallerInfo(3)
	stack := captureStackTrace(3)

	fields := mergeFields(ctx.Fields, OperationFields{
		Table:      ctx.Table,
		Operation:  ctx.Operation,
		Error:      err.Error(),
		Caller:     fmt.Sprintf("%s:%d", file, line),
		StackTrace: stack,
	})

	ctx.Logger.Error(fmt.Sprintf("DB %s FAILED on %s", ctx.Operation, ctx.Table), fields.toKeyvals()...)
}
