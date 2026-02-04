// Package database provides SQLite database operations with Split DB Architecture support
package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"sync"

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
	if err := os.MkdirAll(dir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create database directory: %w", err)
	}

	// Open database connection with WAL mode (modernc/sqlite uses different driver name)
	sqlDB, err := sql.Open("sqlite", path+"?_pragma=foreign_keys(1)&_pragma=journal_mode(WAL)")
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	// Configure for concurrent access
	if err := configureConnection(sqlDB); err != nil {
		sqlDB.Close()
		return nil, err
	}

	// Test connection
	if err := sqlDB.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	return &DB{
		DB:       sqlDB,
		path:     path,
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
		if _, err := db.Exec(pragma); err != nil {
			return fmt.Errorf("failed to execute %s: %w", pragma, err)
		}
	}

	return nil
}

// GetChildDB returns or creates a child database for a specific type/entity
func (db *DB) GetChildDB(dbType, entityID string) (*sql.DB, error) {
	key := fmt.Sprintf("%s/%s", dbType, entityID)

	db.mu.RLock()
	if child, ok := db.childDBs[key]; ok {
		db.mu.RUnlock()
		return child, nil
	}
	db.mu.RUnlock()

	db.mu.Lock()
	defer db.mu.Unlock()

	// Double-check after acquiring write lock
	if child, ok := db.childDBs[key]; ok {
		return child, nil
	}

	// Build path for child database
	var childPath string
	if entityID == "" {
		childPath = filepath.Join(db.dataDir, dbType+".db")
	} else {
		childDir := filepath.Join(db.dataDir, dbType)
		if err := os.MkdirAll(childDir, 0755); err != nil {
			return nil, fmt.Errorf("failed to create child db directory: %w", err)
		}
		childPath = filepath.Join(childDir, entityID+".db")
	}

	// Open child database
	child, err := sql.Open("sqlite", childPath+"?_pragma=foreign_keys(1)&_pragma=journal_mode(WAL)")
	if err != nil {
		return nil, fmt.Errorf("failed to open child database: %w", err)
	}

	if err := configureConnection(child); err != nil {
		child.Close()
		return nil, err
	}

	db.childDBs[key] = child
	return child, nil
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

// DataDir returns the data directory path
func (db *DB) DataDir() string {
	return db.dataDir
}

// Path returns the main database file path
func (db *DB) Path() string {
	return db.path
}

// GetSiteIDByURL returns the site ID for a given URL
func (db *DB) GetSiteIDByURL(url string) (int64, error) {
	var id int64
	err := db.QueryRow("SELECT Id FROM Sites WHERE Url = ?", url).Scan(&id)
	return id, err
}

// GetPluginIDByPath returns the plugin ID for a given path
func (db *DB) GetPluginIDByPath(path string) (int64, error) {
	var id int64
	err := db.QueryRow("SELECT Id FROM Plugins WHERE Path = ?", path).Scan(&id)
	return id, err
}

// CreateSeedSite creates a site for seeding (password must be pre-encrypted by caller)
func (db *DB) CreateSeedSite(name, url, username string, passwordEncrypted []byte, category string) (int64, error) {
	result, err := db.Exec(`
		INSERT INTO Sites (Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, 'unknown', datetime('now'), datetime('now'))
	`, name, url, username, passwordEncrypted, category)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

// CreateSeedPlugin creates a plugin for seeding
func (db *DB) CreateSeedPlugin(name, path, category string, gitEnabled, autoPublish bool) (int64, error) {
	gitEnabledInt := 0
	if gitEnabled {
		gitEnabledInt = 1
	}
	autoPublishInt := 0
	if autoPublish {
		autoPublishInt = 1
	}

	result, err := db.Exec(`
		INSERT INTO Plugins (Name, Path, Category, WatchEnabled, AutoPublish, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, 1, ?, datetime('now'), datetime('now'))
	`, name, path, category, autoPublishInt)
	if err != nil {
		return 0, err
	}

	pluginID, _ := result.LastInsertId()

	// Create git config if enabled
	if gitEnabled {
		_, _ = db.Exec(`
			INSERT INTO PluginGitConfig (PluginId, GitEnabled, UpdatedAt)
			VALUES (?, 1, datetime('now'))
		`, pluginID)
	}

	return pluginID, nil
}

// CreateSeedMapping creates a plugin-site mapping for seeding
func (db *DB) CreateSeedMapping(pluginID, siteID int64, remoteSlug string) error {
	_, err := db.Exec(`
		INSERT OR IGNORE INTO PluginMappings (PluginId, SiteId, RemoteSlug, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, datetime('now'), datetime('now'))
	`, pluginID, siteID, remoteSlug)
	return err
}

// GetDbVersion returns the stored database version for changelog comparison
func (db *DB) GetDbVersion() (string, error) {
	var version string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = 'db.version'").Scan(&version)
	if err == sql.ErrNoRows {
		return "", nil
	}
	return version, err
}

// SetDbVersion sets the database version
func (db *DB) SetDbVersion(version string) error {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES ('db.version', ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, version, version)
	return err
}
