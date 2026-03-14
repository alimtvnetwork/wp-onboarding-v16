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
