// Package database provides SQLite persistence for findings.
package database

import (
	"database/sql"

	"consistency-checker/pkg/apperror"
)

// DB wraps a SQLite connection.
type DB struct {
	conn *sql.DB
}

// Open creates or opens a SQLite database and applies migrations.
func Open(path string) apperror.Result[*DB] {
	conn, err := sql.Open("sqlite3", path)
	if err != nil {
		return apperror.Fail[*DB](apperror.Wrap(err, apperror.ErrDatabase, "failed to open database").WithPath(path))
	}

	db := &DB{conn: conn}
	if migErr := db.migrate(); migErr != nil {
		conn.Close()
		return apperror.Fail[*DB](migErr)
	}

	return apperror.Ok(db)
}

// Close closes the database connection.
func (db *DB) Close() error {
	return db.conn.Close()
}

// Conn returns the underlying sql.DB for direct queries.
func (db *DB) Conn() *sql.DB {
	return db.conn
}
