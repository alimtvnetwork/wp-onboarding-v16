// Package dbutil provides a generic database wrapper with typed Result[T]
// and ResultSet[T] envelopes, automatic stack traces via apperror, and
// convenience query helpers that eliminate boilerplate across all services.
package dbutil

import (
	"context"
	"database/sql"
)

// DB wraps *sql.DB so callers never pass the connection on every call.
type DB struct {
	conn *sql.DB
}

// New creates a DB wrapper around the given connection.
func New(conn *sql.DB) *DB {
	return &DB{conn: conn}
}

// Conn returns the underlying *sql.DB for advanced use cases.
func (d *DB) Conn() *sql.DB {
	return d.conn
}

// QueryRowContext delegates to the underlying *sql.DB.
func (d *DB) QueryRowContext(ctx context.Context, query string, args ...any) *sql.Row {
	return d.conn.QueryRowContext(ctx, query, args...)
}

// QueryContext delegates to the underlying *sql.DB.
func (d *DB) QueryContext(ctx context.Context, query string, args ...any) (*sql.Rows, error) {
	return d.conn.QueryContext(ctx, query, args...)
}

// ExecContext delegates to the underlying *sql.DB.
func (d *DB) ExecContext(ctx context.Context, query string, args ...any) (sql.Result, error) {
	return d.conn.ExecContext(ctx, query, args...)
}
