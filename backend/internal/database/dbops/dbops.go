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
// modernc.org/sqlite returns datetime('now') columns as strings in "YYYY-MM-DD HH:MM:SS".
// We also support RFC3339 for flexibility.
func ParseDateTime(s string) time.Time {
	if strings.TrimSpace(s) == "" {
		return time.Time{}
	}
	// Try SQLite datetime format first
	if t, err := time.Parse("2006-01-02 15:04:05", s); err == nil {
		return t
	}
	// Fallback to RFC3339
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
	Created      bool // True if a new row was inserted (for upsert operations)
	Exists       bool // True if row already existed (for INSERT OR IGNORE)
}

// ContextFields holds structured metadata fields for database operation logging.
type ContextFields = map[string]any

// Context provides metadata for logging database operations
type Context struct {
	Table     string         // Table name for logging
	Operation string         // Operation type: INSERT, UPDATE, DELETE, SELECT
	Logger    *logger.Logger // Logger instance for output
	Fields    ContextFields  // Additional fields to log
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
			// Shorten function name for readability
			if idx := strings.LastIndex(fnName, "/"); idx != -1 {
				fnName = fnName[idx+1:]
			}
		}
		// Shorten file path
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
	// Shorten file path
	if idx := strings.LastIndex(file, "/"); idx != -1 {
		file = file[idx+1:]
	}
	return file, line
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
// Returns (id, created, error) where created is true if a new record was inserted
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
	// First, try to find existing
	var id int64
	err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
	if err == nil {
		// Record exists
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, ContextFields{
				"table":     ctx.Table,
				"operation": "FIND",
				"id":        id,
				"exists":    true,
			})
			ctx.Logger.Debug("Record found (exists)", toSlice(fields)...)
		}
		return id, false, nil
	}

	if err != sql.ErrNoRows {
		// Actual error
		logError(ctx, err, selectQuery, selectArgs)
		return 0, false, err
	}

	// Not found, create new
	ctx.Operation = "INSERT"
	result, err := db.Exec(insertQuery, insertArgs...)
	if err != nil {
		logError(ctx, err, insertQuery, insertArgs)
		return 0, false, err
	}

	rows, _ := result.RowsAffected()
	id, _ = result.LastInsertId()

	// Check if insert actually created (handles INSERT OR IGNORE race conditions)
	if rows == 0 {
		// Another process may have inserted - try to find again
		err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
		if err != nil {
			logError(ctx, err, selectQuery, selectArgs)
			return 0, false, err
		}
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, ContextFields{
				"table":     ctx.Table,
				"operation": "FIND_AFTER_RACE",
				"id":        id,
				"exists":    true,
			})
			ctx.Logger.Debug("Record found after race condition", toSlice(fields)...)
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
// Returns (created bool, error) - created is true only if a new row was inserted
func CreateMapping(
	db interface{ Exec(string, ...any) (sql.Result, error) },
	ctx Context,
	query string,
	args ...any,
) (bool, error) {
	ctx.Operation = "INSERT_MAPPING"

	result, err := db.Exec(query, args...)
	if err != nil {
		// Check for constraint violations
		errStr := err.Error()
		isConstraintViolation := strings.Contains(errStr, "UNIQUE") || 
			strings.Contains(errStr, "constraint") ||
			strings.Contains(errStr, "duplicate")
		
		if isConstraintViolation {
			// Not a real error for mappings - just means it exists
			if ctx.Logger != nil {
				fields := mergeFields(ctx.Fields, ContextFields{
					"table":     ctx.Table,
					"operation": "INSERT_MAPPING",
					"exists":    true,
					"note":      "Mapping already exists (constraint)",
				})
				ctx.Logger.Debug("Mapping exists", toSlice(fields)...)
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
			fields := mergeFields(ctx.Fields, ContextFields{
				"table":        ctx.Table,
				"operation":    "INSERT_MAPPING",
				"affectedRows": rows,
				"id":           id,
				"created":      true,
			})
			ctx.Logger.Info("Mapping CREATED", toSlice(fields)...)
		}
	} else {
		if ctx.Logger != nil {
			fields := mergeFields(ctx.Fields, ContextFields{
				"table":     ctx.Table,
				"operation": "INSERT_MAPPING",
				"exists":    true,
				"note":      "Mapping already exists (INSERT OR IGNORE)",
			})
			ctx.Logger.Debug("Mapping EXISTS", toSlice(fields)...)
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
	
	fields := mergeFields(ctx.Fields, ContextFields{
		"table":        ctx.Table,
		"operation":    ctx.Operation,
		"affectedRows": res.AffectedRows,
		"caller":       fmt.Sprintf("%s:%d", file, line),
	})

	if res.LastInsertID > 0 {
		fields["lastInsertId"] = res.LastInsertID
	}
	if res.Created {
		fields["created"] = true
	}
	if res.Exists {
		fields["exists"] = true
	}

	ctx.Logger.Debug(fmt.Sprintf("DB %s on %s", ctx.Operation, ctx.Table), toSlice(fields)...)
}

// logError logs a failed database operation with stack trace
func logError(ctx Context, err error, query string, args []any) {
	if ctx.Logger == nil {
		return
	}

	file, line := getCallerInfo(3)
	stack := captureStackTrace(3)

	fields := mergeFields(ctx.Fields, ContextFields{
		"table":      ctx.Table,
		"operation":  ctx.Operation,
		"error":      err.Error(),
		"caller":     fmt.Sprintf("%s:%d", file, line),
		"stackTrace": stack,
	})

	ctx.Logger.Error(fmt.Sprintf("DB %s FAILED on %s", ctx.Operation, ctx.Table), toSlice(fields)...)
}

// mergeFields merges two field maps
func mergeFields(base, extra ContextFields) ContextFields {
	result := make(ContextFields)
	for k, v := range base {
		result[k] = v
	}
	for k, v := range extra {
		result[k] = v
	}
	return result
}

// toSlice converts a map to a slice of key-value pairs for logger
func toSlice(m ContextFields) []any {
	slice := make([]any, 0, len(m)*2)
	for k, v := range m {
		slice = append(slice, k, v)
	}
	return slice
}
