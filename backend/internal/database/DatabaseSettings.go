package database

import (
	"database/sql"
	"fmt"

	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// ─── Settings ────────────────────────────────────────────────────────────────

// GetSeedVersion returns the current seed version from the database
func (db *DB) GetSeedVersion() (string, error) {
	var version string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = 'seed_version'").Scan(&version)
	if err == sql.ErrNoRows {
		return "", nil
	}
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get seed version")
	}
	return version, nil
}

// SetSeedVersion sets the seed version in the database
func (db *DB) SetSeedVersion(version string) *apperror.AppError {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES ('seed_version', ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, version, version)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set seed version")
	}
	return nil
}

// SetSettingIfNotExists creates a setting only if it doesn't already exist
func (db *DB) SetSettingIfNotExists(key string, value any) *apperror.AppError {
	_, err := db.Exec(`
		INSERT OR IGNORE INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES (?, ?, datetime('now'))
	`, key, fmt.Sprintf("%v", value))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set setting if not exists").
			WithDetails(fmt.Sprintf("key=%s", key))
	}
	return nil
}

// GetSetting retrieves a setting value by key
func (db *DB) GetSetting(key string) (string, error) {
	var value string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = ?", key).Scan(&value)
	if err == sql.ErrNoRows {
		return "", nil
	}
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get setting").
			WithDetails(fmt.Sprintf("key=%s", key))
	}
	return value, nil
}

// SetSetting updates or creates a setting
func (db *DB) SetSetting(key, value string) *apperror.AppError {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES (?, ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, key, value, value)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set setting").
			WithDetails(fmt.Sprintf("key=%s", key))
	}
	return nil
}

// GetDbVersion returns the stored database version for changelog comparison
func (db *DB) GetDbVersion() (string, error) {
	var version string
	err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = 'db.version'").Scan(&version)
	if err == sql.ErrNoRows {
		return "", nil
	}
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get db version")
	}
	return version, nil
}

// SetDbVersion sets the database version
func (db *DB) SetDbVersion(version string) *apperror.AppError {
	_, err := db.Exec(`
		INSERT INTO AppConfig (Key, Value, UpdatedAt) 
		VALUES ('db.version', ?, datetime('now'))
		ON CONFLICT(Key) DO UPDATE SET Value = ?, UpdatedAt = datetime('now')
	`, version, version)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set db version")
	}
	return nil
}

// ─── Lookup ──────────────────────────────────────────────────────────────────

// GetSiteIdByUrl returns the site ID for a given URL
func (db *DB) GetSiteIdByUrl(url string) (int64, error) {
	var id int64
	err := db.QueryRow("SELECT Id FROM Sites WHERE Url = ?", url).Scan(&id)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site by URL").
			WithUrl(url)
	}
	return id, nil
}

// GetPluginIdByPath returns the plugin ID for a given path
func (db *DB) GetPluginIdByPath(path string) (int64, error) {
	var id int64
	err := db.QueryRow("SELECT Id FROM Plugins WHERE Path = ?", path).Scan(&id)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin by path").
			WithPath(path)
	}
	return id, nil
}

// ─── Seeding ─────────────────────────────────────────────────────────────────

// SeedSiteInput bundles parameters for CreateSeedSite.
type SeedSiteInput struct {
	Name              string
	Url               string
	Username          string
	PasswordEncrypted []byte
	Category          string
}

// CreateSeedSite creates a site for seeding (password must be pre-encrypted by caller)
// Seeded sites default to ConnectionStatus = 'connected' for quick testing
func (db *DB) CreateSeedSite(input SeedSiteInput) (int64, error) {
	result, err := db.Exec(`
		INSERT INTO Sites (Name, Url, Username, PasswordEncrypted, Category, ConnectionStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, 'connected', datetime('now'), datetime('now'))
	`, input.Name, input.Url, input.Username, input.PasswordEncrypted, input.Category)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create seed site").
			WithUrl(input.Url)
	}
	return result.LastInsertId()
}

// SeedPluginInput bundles parameters for CreateSeedPlugin.
type SeedPluginInput struct {
	Name        string
	Path        string
	Category    string
	GitEnabled  bool
	AutoPublish bool
}

// CreateSeedPlugin creates a plugin for seeding
func (db *DB) CreateSeedPlugin(input SeedPluginInput) (int64, error) {
	autoPublishInt := 0
	if input.AutoPublish {
		autoPublishInt = 1
	}

	result, err := db.Exec(`
		INSERT INTO Plugins (Name, Path, Category, WatchEnabled, AutoPublish, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, 1, ?, datetime('now'), datetime('now'))
	`, input.Name, input.Path, input.Category, autoPublishInt)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create seed plugin").
			WithPath(input.Path)
	}

	pluginId, lastIdErr := result.LastInsertId()

	if lastIdErr != nil {
		return 0, apperror.Wrap(lastIdErr, apperror.ErrDatabaseQuery, "failed to get last insert ID for seed plugin")
	}

	// Create git config if enabled
	if input.GitEnabled {
		_, gitErr := db.Exec(`
			INSERT INTO PluginGitConfig (PluginId, GitEnabled, UpdatedAt)
			VALUES (?, 1, datetime('now'))
		`, pluginId)

		if gitErr != nil {
			return pluginId, apperror.Wrap(gitErr, apperror.ErrDatabaseInsert, "failed to create git config for seed plugin")
		}
	}

	return pluginId, nil
}

// SeedMappingInput bundles parameters for CreateSeedMapping.
type SeedMappingInput struct {
	PluginId   int64
	SiteId     int64
	RemoteSlug string
	Logger     *logger.Logger
}

// CreateSeedMapping creates a plugin-site mapping for seeding
// Returns (created bool, error) - created is true only if a new row was inserted
func (db *DB) CreateSeedMapping(input SeedMappingInput) (bool, error) {
	ctx := dbops.Context{
		Table:  "PluginMappings",
		Logger: input.Logger,
		Fields: dbops.OperationFields{
			PluginId:   input.PluginId,
			SiteId:     input.SiteId,
			RemoteSlug: input.RemoteSlug,
		},
	}

	return dbops.CreateMapping(db.DB, ctx, `
		INSERT OR IGNORE INTO PluginMappings (PluginId, SiteId, RemoteSlug, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, datetime('now'), datetime('now'))
	`, input.PluginId, input.SiteId, input.RemoteSlug)
}
