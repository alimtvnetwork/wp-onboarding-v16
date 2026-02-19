package dbutil

import (
	"context"
	"database/sql"

	"wp-plugin-publish/pkg/apperror"
)

// RowScanner scans a single *sql.Row into T.
type RowScanner[T any] func(*sql.Row) (T, error)

// RowsScanner scans the current row of *sql.Rows into T.
type RowsScanner[T any] func(*sql.Rows) (T, error)

// QueryOne executes a query expected to return a single row.
// Returns Result[T] with IsDefined()=false for sql.ErrNoRows (not an error).
func QueryOne[T any](ctx context.Context, db *DB, query string, scan RowScanner[T], args ...any) Result[T] {
	row := db.conn.QueryRowContext(ctx, query, args...)
	value, err := scan(row)

	if err == sql.ErrNoRows {
		return Result[T]{}
	}
	if err != nil {
		wrapped := apperror.Wrap(err, "E5010", "QueryOne failed")
		return NewResultError[T](wrapped, wrapped.StackTrace)
	}
	return NewResult(value)
}

// QueryMany executes a query expected to return multiple rows.
func QueryMany[T any](ctx context.Context, db *DB, query string, scan RowsScanner[T], args ...any) ResultSet[T] {
	rows, err := db.conn.QueryContext(ctx, query, args...)
	if err != nil {
		wrapped := apperror.Wrap(err, "E5011", "QueryMany failed")
		return NewResultSetError[T](wrapped, wrapped.StackTrace)
	}
	defer rows.Close()

	return collectRows(rows, scan)
}

// collectRows iterates rows and collects into a ResultSet.
func collectRows[T any](rows *sql.Rows, scan RowsScanner[T]) ResultSet[T] {
	var items []T
	for rows.Next() {
		item, err := scan(rows)
		if err != nil {
			wrapped := apperror.Wrap(err, "E5012", "row scan failed")
			return NewResultSetError[T](wrapped, wrapped.StackTrace)
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		wrapped := apperror.Wrap(err, "E5013", "rows iteration failed")
		return NewResultSetError[T](wrapped, wrapped.StackTrace)
	}
	return NewResultSet(items)
}
