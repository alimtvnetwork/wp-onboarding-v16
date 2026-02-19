package dbutil

import (
	"context"

	"wp-plugin-publish/pkg/apperror"
)

// ExecResult holds the outcome of a non-query statement.
type ExecResult struct {
	AffectedRows int64
	LastInsertID int64
	err          error
	stackTrace   string
}

// HasError returns true when the exec failed.
func (r ExecResult) HasError() bool { return r.err != nil }

// IsSafe returns true when there is no error.
func (r ExecResult) IsSafe() bool { return r.err == nil }

// Error returns the underlying error, or nil.
func (r ExecResult) Error() error { return r.err }

// StackTrace returns the captured stack trace if an error occurred.
func (r ExecResult) StackTrace() string { return r.stackTrace }

// IsEmpty returns true when zero rows were affected.
func (r ExecResult) IsEmpty() bool { return r.AffectedRows == 0 }

// Exec runs a non-query statement (INSERT, UPDATE, DELETE) and returns ExecResult.
func Exec(ctx context.Context, db *DB, query string, args ...any) ExecResult {
	result, err := db.conn.ExecContext(ctx, query, args...)
	if err != nil {
		wrapped := apperror.Wrap(err, "E5014", "Exec failed")
		return ExecResult{err: wrapped, stackTrace: wrapped.StackTrace}
	}

	rows, _ := result.RowsAffected()
	id, _ := result.LastInsertId()

	return ExecResult{AffectedRows: rows, LastInsertID: id}
}
