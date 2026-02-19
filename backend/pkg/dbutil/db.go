// Package dbutil provides a generic database wrapper with typed Result[T]
// and ResultSet[T] envelopes, automatic stack traces via apperror, and
// convenience query helpers that eliminate boilerplate across all services.
package dbutil

import "database/sql"

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
