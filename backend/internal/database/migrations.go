// Package database - Schema migrations
package database

import (
	"fmt"

	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// Migration represents a database migration
type Migration struct {
	Version     int
	Description string
	SQL         string
}

// migrations is the list of all database migrations
var migrations = []Migration{
	{
		Version:     1,
		Description: "Initial schema",
		SQL: `
			-- Sites table: WordPress site connections
			CREATE TABLE IF NOT EXISTS Sites (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				Name TEXT NOT NULL,
				Url TEXT NOT NULL UNIQUE,
				Username TEXT NOT NULL,
				PasswordEncrypted BLOB NOT NULL,
				ConnectionStatus TEXT DEFAULT 'unknown',
				LastTestedAt TEXT,
				LastSyncAt TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				UpdatedAt TEXT DEFAULT (datetime('now'))
			);

			-- Plugins table: Local plugin directories
			CREATE TABLE IF NOT EXISTS Plugins (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				Name TEXT NOT NULL,
				Path TEXT NOT NULL UNIQUE,
				WatchEnabled INTEGER DEFAULT 1,
				ExcludePatterns TEXT DEFAULT '[]',
				FileCount INTEGER DEFAULT 0,
				LastScannedAt TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				UpdatedAt TEXT DEFAULT (datetime('now'))
			);

			-- PluginMappings table: Plugin to site relationships
			CREATE TABLE IF NOT EXISTS PluginMappings (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginId INTEGER NOT NULL,
				SiteId INTEGER NOT NULL,
				RemoteSlug TEXT NOT NULL,
				SyncStatus TEXT DEFAULT 'unknown',
				LastSyncAt TEXT,
				LastBackupAt TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				UpdatedAt TEXT DEFAULT (datetime('now')),
				FOREIGN KEY (PluginId) REFERENCES Plugins(Id) ON DELETE CASCADE,
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE,
				UNIQUE(PluginId, SiteId)
			);

			-- FileChanges table: Detected file modifications
			CREATE TABLE IF NOT EXISTS FileChanges (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginId INTEGER NOT NULL,
				FilePath TEXT NOT NULL,
				ChangeType TEXT NOT NULL,
				LocalHash TEXT,
				RemoteHash TEXT,
				LocalModifiedAt TEXT,
				DetectedAt TEXT DEFAULT (datetime('now')),
				SyncedAt TEXT,
				FOREIGN KEY (PluginId) REFERENCES Plugins(Id) ON DELETE CASCADE
			);

			-- SyncRecords table: Sync operation history
			CREATE TABLE IF NOT EXISTS SyncRecords (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginMappingId INTEGER NOT NULL,
				SyncType TEXT NOT NULL,
				Status TEXT NOT NULL,
				FilesChecked INTEGER DEFAULT 0,
				FilesChanged INTEGER DEFAULT 0,
				FilesUploaded INTEGER DEFAULT 0,
				ErrorMessage TEXT,
				StartedAt TEXT DEFAULT (datetime('now')),
				CompletedAt TEXT,
				FOREIGN KEY (PluginMappingId) REFERENCES PluginMappings(Id) ON DELETE CASCADE
			);

			-- Backups table: Plugin backup records
			CREATE TABLE IF NOT EXISTS Backups (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginMappingId INTEGER NOT NULL,
				FilePath TEXT NOT NULL,
				FileSize INTEGER NOT NULL,
				PluginVersion TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				ExpiresAt TEXT,
				FOREIGN KEY (PluginMappingId) REFERENCES PluginMappings(Id) ON DELETE CASCADE
			);

			-- ErrorLogs table: Application error history
			CREATE TABLE IF NOT EXISTS ErrorLogs (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				Code TEXT NOT NULL,
				Level TEXT NOT NULL,
				Message TEXT NOT NULL,
				Details TEXT,
				Context TEXT,
				File TEXT,
				Line INTEGER,
				Function TEXT,
				StackTrace TEXT,
				CreatedAt TEXT DEFAULT (datetime('now'))
			);

			-- AppConfig table: Application settings
			CREATE TABLE IF NOT EXISTS AppConfig (
				Key TEXT PRIMARY KEY,
				Value TEXT NOT NULL,
				UpdatedAt TEXT DEFAULT (datetime('now'))
			);

			-- Create indexes
			CREATE INDEX IF NOT EXISTS idx_plugins_path ON Plugins(Path);
			CREATE INDEX IF NOT EXISTS idx_filechanges_plugin ON FileChanges(PluginId);
			CREATE INDEX IF NOT EXISTS idx_syncrecords_mapping ON SyncRecords(PluginMappingId);
			CREATE INDEX IF NOT EXISTS idx_backups_mapping ON Backups(PluginMappingId);
			CREATE INDEX IF NOT EXISTS idx_errorlogs_code ON ErrorLogs(Code);
			CREATE INDEX IF NOT EXISTS idx_errorlogs_created ON ErrorLogs(CreatedAt);
		`,
	},
	{
		Version:     2,
		Description: "Add PluginGitConfig table",
		SQL: `
			-- PluginGitConfig table: Git and build settings per plugin
			CREATE TABLE IF NOT EXISTS PluginGitConfig (
				PluginId INTEGER PRIMARY KEY,
				GitEnabled INTEGER DEFAULT 1,
				GitBranch TEXT DEFAULT 'main',
				GitRemoteUrl TEXT,
				BuildEnabled INTEGER DEFAULT 0,
				BuildCommand TEXT,
				UpdatedAt TEXT DEFAULT (datetime('now')),
				FOREIGN KEY (PluginId) REFERENCES Plugins(Id) ON DELETE CASCADE
			);
		`,
	},
	{
		Version:     3,
		Description: "Add Category field to Sites and Plugins",
		SQL: `
			-- Add Category column to Sites table
			ALTER TABLE Sites ADD COLUMN Category TEXT DEFAULT '';

			-- Add Category column to Plugins table
			ALTER TABLE Plugins ADD COLUMN Category TEXT DEFAULT '';

			-- Create indexes for category filtering
			CREATE INDEX IF NOT EXISTS idx_sites_category ON Sites(Category);
			CREATE INDEX IF NOT EXISTS idx_plugins_category ON Plugins(Category);
		`,
	},
	{
		Version:     4,
		Description: "Add AutoPublish and SeedVersion support",
		SQL: `
			-- Add AutoPublish column to Plugins table for auto-deploy on file changes
			ALTER TABLE Plugins ADD COLUMN AutoPublish INTEGER DEFAULT 0;

			-- Add SeedVersion to AppConfig for tracking seeded data version
			INSERT OR IGNORE INTO AppConfig (Key, Value) VALUES ('seed.version', '');

			-- Add DbVersion to track schema version for changelog display
			INSERT OR IGNORE INTO AppConfig (Key, Value) VALUES ('db.version', '1.8.0');
		`,
	},
	{
		Version:     5,
		Description: "Add PluginVersions table for version history and rollback",
		SQL: `
			-- PluginVersions table: Track each publish operation for rollback support
			CREATE TABLE IF NOT EXISTS PluginVersions (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginId INTEGER NOT NULL,
				SiteId INTEGER NOT NULL,
				Version TEXT NOT NULL,
				BackupPath TEXT,
				FilesUpdated INTEGER DEFAULT 0,
				GitCommitHash TEXT,
				PublishType TEXT DEFAULT 'full',
				Status TEXT DEFAULT 'completed',
				Notes TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				FOREIGN KEY (PluginId) REFERENCES Plugins(Id) ON DELETE CASCADE,
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
			);

			-- Create indexes for efficient queries
			CREATE INDEX IF NOT EXISTS idx_pluginversions_plugin ON PluginVersions(PluginId);
			CREATE INDEX IF NOT EXISTS idx_pluginversions_site ON PluginVersions(SiteId);
			CREATE INDEX IF NOT EXISTS idx_pluginversions_created ON PluginVersions(CreatedAt DESC);
		`,
	},
	{
		Version:     6,
		Description: "Add RemotePluginsCache table for caching site plugin lists",
		SQL: `
			-- RemotePluginsCache table: Cache plugin list data fetched from remote sites
			CREATE TABLE IF NOT EXISTS RemotePluginsCache (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				SiteId INTEGER NOT NULL UNIQUE,
				PluginsJSON TEXT NOT NULL,
				CachedAt TEXT DEFAULT (datetime('now')),
				ExpiresAt TEXT NOT NULL,
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
			);

			-- Create index for efficient cache lookups
			CREATE INDEX IF NOT EXISTS idx_remotepluginscache_site ON RemotePluginsCache(SiteId);
			CREATE INDEX IF NOT EXISTS idx_remotepluginscache_expires ON RemotePluginsCache(ExpiresAt);
		`,
	},
	{
		Version:     7,
		Description: "Add ErrorHistory table for persistent error/notification storage",
		SQL: `
			-- ErrorHistory table: Persistent storage for all captured errors and notifications
			CREATE TABLE IF NOT EXISTS ErrorHistory (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				ErrorId TEXT NOT NULL UNIQUE,
				Code TEXT NOT NULL,
				Level TEXT NOT NULL DEFAULT 'error',
				Message TEXT NOT NULL,
				Details TEXT,
				ContextJson TEXT,
				StackTrace TEXT,
				Endpoint TEXT,
				Method TEXT,
				RequestBodyJson TEXT,
				ResponseStatus INTEGER,
				SessionId TEXT,
				SessionType TEXT,
				PhpStackFramesJson TEXT,
				BackendLogsJson TEXT,
				BackendStackTrace TEXT,
				SiteUrl TEXT,
				TriggerComponent TEXT,
				TriggerAction TEXT,
				InvocationChainJson TEXT,
				UiClickPath TEXT,
				MarkdownReport TEXT,
				CreatedAt TEXT DEFAULT (datetime('now'))
			);

			-- Create indexes for efficient queries
			CREATE INDEX IF NOT EXISTS idx_errorhistory_errorid ON ErrorHistory(ErrorId);
			CREATE INDEX IF NOT EXISTS idx_errorhistory_code ON ErrorHistory(Code);
			CREATE INDEX IF NOT EXISTS idx_errorhistory_level ON ErrorHistory(Level);
			CREATE INDEX IF NOT EXISTS idx_errorhistory_created ON ErrorHistory(CreatedAt DESC);
		`,
	},
	{
		Version:     8,
		Description: "PublishHistory table for publish operation audit trail",
		SQL: `
			CREATE TABLE IF NOT EXISTS PublishHistory (
				ID INTEGER PRIMARY KEY AUTOINCREMENT,
				PluginID INTEGER NOT NULL,
				PluginName TEXT NOT NULL DEFAULT '',
				SiteID INTEGER NOT NULL,
				SiteName TEXT NOT NULL DEFAULT '',
				SiteURL TEXT NOT NULL DEFAULT '',
				SessionID TEXT DEFAULT '',
				Status TEXT NOT NULL DEFAULT 'unknown',
				Mode TEXT NOT NULL DEFAULT 'full',
				FilesUpdated INTEGER DEFAULT 0,
				ActivationStatus TEXT DEFAULT 'unknown',
				RollbackStatus TEXT DEFAULT '',
				RollbackMessage TEXT DEFAULT '',
				ErrorMessage TEXT DEFAULT '',
				DurationMs INTEGER DEFAULT 0,
				CreatedAt TEXT DEFAULT (datetime('now'))
			);

			CREATE INDEX IF NOT EXISTS idx_publishhistory_plugin ON PublishHistory(PluginID);
			CREATE INDEX IF NOT EXISTS idx_publishhistory_site ON PublishHistory(SiteID);
			CREATE INDEX IF NOT EXISTS idx_publishhistory_status ON PublishHistory(Status);
			CREATE INDEX IF NOT EXISTS idx_publishhistory_created ON PublishHistory(CreatedAt DESC);
		`,
	},
	{
		Version:     9,
		Description: "SiteHealthChecks table for health monitoring",
		SQL: `
			CREATE TABLE IF NOT EXISTS SiteHealthChecks (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				SiteId INTEGER NOT NULL,
				Status TEXT NOT NULL DEFAULT 'unknown',
				ResponseMs INTEGER DEFAULT 0,
				StatusCode INTEGER DEFAULT 0,
				ErrorMessage TEXT DEFAULT '',
				UploaderOk INTEGER DEFAULT 0,
				CreatedAt TEXT DEFAULT (datetime('now')),
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
			);

			CREATE INDEX IF NOT EXISTS idx_sitehealthchecks_site ON SiteHealthChecks(SiteId);
			CREATE INDEX IF NOT EXISTS idx_sitehealthchecks_created ON SiteHealthChecks(CreatedAt DESC);
			CREATE INDEX IF NOT EXISTS idx_sitehealthchecks_status ON SiteHealthChecks(Status);
		`,
	},
}

// Migrate runs all pending migrations
func Migrate(db *DB, log *logger.Logger) error {
	log.Info("Starting database migrations")

	if err := ensureMigrationsTable(db, log); err != nil {
		return err
	}

	currentVersion, err := getCurrentMigrationVersion(db, log)
	if err != nil {
		return err
	}

	log.Debug("Current migration version", "version", currentVersion)

	appliedCount, err := applyPendingMigrations(db, log, currentVersion)
	if err != nil {
		return err
	}

	logMigrationSummary(log, appliedCount, currentVersion)
	return nil
}

// ensureMigrationsTable creates the migrations tracking table if it doesn't exist.
func ensureMigrationsTable(db *DB, log *logger.Logger) error {
	_, err := db.Exec(`
		CREATE TABLE IF NOT EXISTS _migrations (
			Version INTEGER PRIMARY KEY,
			Description TEXT NOT NULL,
			AppliedAt TEXT DEFAULT (datetime('now'))
		)
	`)
	if err != nil {
		log.Error("Failed to create migrations table", "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to create migrations table")
	}
	return nil
}

// getCurrentMigrationVersion returns the highest applied migration version.
func getCurrentMigrationVersion(db *DB, log *logger.Logger) (int, error) {
	var version int
	err := db.QueryRow("SELECT COALESCE(MAX(Version), 0) FROM _migrations").Scan(&version)
	if err != nil {
		log.Error("Failed to get current migration version", "error", err)
		return 0, apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to get current migration version")
	}
	return version, nil
}

// applyPendingMigrations runs all migrations above currentVersion, returns count applied.
func applyPendingMigrations(db *DB, log *logger.Logger, currentVersion int) (int, error) {
	applied := 0
	for _, m := range migrations {
		if m.Version <= currentVersion {
			continue
		}
		if err := applySingleMigration(db, log, m); err != nil {
			return applied, err
		}
		applied++
	}
	return applied, nil
}

// applySingleMigration runs one migration inside a transaction.
func applySingleMigration(db *DB, log *logger.Logger, m Migration) error {
	log.Info("Applying migration", "version", m.Version, "description", m.Description)

	tx, err := db.Begin()
	if err != nil {
		log.Error("Failed to begin transaction", "version", m.Version, "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to begin transaction").
			WithDetails(fmt.Sprintf("version=%d", m.Version))
	}

	if _, err := tx.Exec(m.SQL); err != nil {
		tx.Rollback()
		log.Error("Migration SQL failed", "version", m.Version, "description", m.Description, "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to apply migration SQL").
			WithDetails(fmt.Sprintf("version=%d, description=%s", m.Version, m.Description))
	}

	if _, err := tx.Exec("INSERT INTO _migrations (Version, Description) VALUES (?, ?)", m.Version, m.Description); err != nil {
		tx.Rollback()
		log.Error("Failed to record migration", "version", m.Version, "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to record migration").
			WithDetails(fmt.Sprintf("version=%d", m.Version))
	}

	if err := tx.Commit(); err != nil {
		log.Error("Failed to commit migration", "version", m.Version, "error", err)
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to commit migration").
			WithDetails(fmt.Sprintf("version=%d", m.Version))
	}

	log.Info("Migration completed", "version", m.Version)
	return nil
}

// logMigrationSummary logs the final migration result.
func logMigrationSummary(log *logger.Logger, appliedCount, currentVersion int) {
	if appliedCount > 0 {
		log.Info("Migrations applied", "count", appliedCount, "currentVersion", len(migrations))
	} else {
		log.Debug("No new migrations to apply", "currentVersion", currentVersion)
	}
}
