// Package database provides SQLite database operations with Split DB Architecture support
package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"sync"

	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"

	_ "modernc.org/sqlite"
)

// DB wraps the SQL database connection with Split DB support
type DB struct {
	*sql.DB
	path     string
	dataDir  string
	childDBs map[string]*sql.DB
	mu       sync.RWMutex
}

// New creates a new database connection
func New(path string) (*DB, error) {
	// Ensure directory exists
	dir := filepath.Dir(path)
	err := os.MkdirAll(dir, 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to create database directory").
			WithPath(dir)
	}

	// Resolve absolute path for database file
	absPath, err := pathutil.ToAbsolute(path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to resolve database path").
			WithPath(path)
	}

	// Open database connection with WAL mode (modernc/sqlite uses different driver name)
	sqlDB, err := sql.Open("sqlite", absPath+"?_pragma=foreign_keys(1)&_pragma=journal_mode(WAL)")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to open database").
			WithPath(absPath)
	}

	// Configure for concurrent access
	err = configureConnection(sqlDB)
	if err != nil {
		sqlDB.Close()

		return nil, err
	}

	// Test connection
	err = sqlDB.Ping()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to ping database").
			WithPath(absPath)
	}

	return &DB{
		DB:       sqlDB,
		path:     absPath,
		dataDir:  dir,
		childDBs: make(map[string]*sql.DB),
	}, nil
}

// configureConnection sets up SQLite for optimal performance
func configureConnection(db *sql.DB) error {
	pragmas := []string{
		"PRAGMA busy_timeout=5000",
		"PRAGMA synchronous=NORMAL",
		"PRAGMA cache_size=10000",
	}

	for _, pragma := range pragmas {
		_, err := db.Exec(pragma)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to execute pragma").
				WithDetails(pragma)
		}
	}

	return nil
}

// GetChildDB returns or creates a child database for a specific type/entity
func (db *DB) GetChildDB(dbType, entityID string) (*sql.DB, error) {
	key := fmt.Sprintf("%s/%s", dbType, entityID)

	db.mu.RLock()
	child, isCached := db.childDBs[key]

	if isCached {
		db.mu.RUnlock()

		return child, nil
	}
	db.mu.RUnlock()

	db.mu.Lock()
	defer db.mu.Unlock()

	// Double-check after acquiring write lock
	child, isCached = db.childDBs[key]

	if isCached {
		return child, nil
	}

	childPath, err := resolveChildPath(db.dataDir, dbType, entityID)
	if err != nil {
		return nil, err
	}

	// Open child database
	child, err := sql.Open("sqlite", childPath+"?_pragma=foreign_keys(1)&_pragma=journal_mode(WAL)")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to open child database").
			WithPath(childPath)
	}

	err = configureConnection(child)
	if err != nil {
		child.Close()

		return nil, err
	}

	db.childDBs[key] = child
	return child, nil
}

// resolveChildPath builds the filesystem path for a child database.
func resolveChildPath(dataDir, dbType, entityID string) (string, error) {
	isGlobalDB := entityID == ""

	if isGlobalDB {
		p, err := pathutil.Join(dataDir, dbType+".db")
		if err != nil {
			return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve child db path")
		}

		return p, nil
	}

	childDir, err := pathutil.Join(dataDir, dbType)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve child db directory")
	}
	err = os.MkdirAll(childDir, 0755)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to create child db directory").
			WithPath(childDir)
	}
	p, err := pathutil.Join(childDir, entityID+".db")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve child db path")
	}
	return p, nil
}

// CloseChildDBs closes all child database connections
func (db *DB) CloseChildDBs() {
	db.mu.Lock()
	defer db.mu.Unlock()

	for key, child := range db.childDBs {
		child.Close()
		delete(db.childDBs, key)
	}
}

// Close closes the main database and all child databases
func (db *DB) Close() error {
	db.CloseChildDBs()
	return db.DB.Close()
}

// DataDir returns the data directory path
func (db *DB) DataDir() string {
	return db.dataDir
}

// Path returns the main database file path
func (db *DB) Path() string {
	return db.path
}
