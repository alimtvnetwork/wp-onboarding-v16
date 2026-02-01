// Package database provides SQLite database operations
package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	_ "github.com/mattn/go-sqlite3"
)

// DB wraps the SQL database connection
type DB struct {
	*sql.DB
}

// New creates a new database connection
func New(path string) (*DB, error) {
	// Ensure directory exists
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create database directory: %w", err)
	}

	// Open database connection
	sqlDB, err := sql.Open("sqlite3", path+"?_foreign_keys=on&_journal_mode=WAL")
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	// Test connection
	if err := sqlDB.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	return &DB{sqlDB}, nil
}

// GetSeedVersion returns the current seed version from the database
func (db *DB) GetSeedVersion() (string, error) {
	var version string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = 'seed_version'").Scan(&version)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return version, err
}

// SetSeedVersion sets the seed version in the database
func (db *DB) SetSeedVersion(version string) error {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES ('seed_version', ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, version, version)
	return err
}

// SetSettingIfNotExists creates a setting only if it doesn't already exist
func (db *DB) SetSettingIfNotExists(key string, value interface{}) error {
	_, err := db.Exec(`
		INSERT OR IGNORE INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES (?, ?, datetime('now'))
	`, key, fmt.Sprintf("%v", value))
	return err
}

// GetSetting retrieves a setting value by key
func (db *DB) GetSetting(key string) (string, error) {
	var value string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = ?", key).Scan(&value)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return value, err
}

// SetSetting updates or creates a setting
func (db *DB) SetSetting(key, value string) error {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES (?, ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, key, value, value)
	return err
}
