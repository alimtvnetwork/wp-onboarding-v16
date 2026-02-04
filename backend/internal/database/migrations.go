// Package database - Schema migrations
package database

import (
	"fmt"
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
}

// Migrate runs all pending migrations
func Migrate(db *DB) error {
	// Create migrations table if not exists
	_, err := db.Exec(`
		CREATE TABLE IF NOT EXISTS _migrations (
			Version INTEGER PRIMARY KEY,
			Description TEXT NOT NULL,
			AppliedAt TEXT DEFAULT (datetime('now'))
		)
	`)
	if err != nil {
		return fmt.Errorf("failed to create migrations table: %w", err)
	}

	// Get current version
	var currentVersion int
	err = db.QueryRow("SELECT COALESCE(MAX(Version), 0) FROM _migrations").Scan(&currentVersion)
	if err != nil {
		return fmt.Errorf("failed to get current migration version: %w", err)
	}

	// Apply pending migrations
	for _, m := range migrations {
		if m.Version <= currentVersion {
			continue
		}

		// Run migration in transaction
		tx, err := db.Begin()
		if err != nil {
			return fmt.Errorf("failed to begin transaction for migration %d: %w", m.Version, err)
		}

		if _, err := tx.Exec(m.SQL); err != nil {
			tx.Rollback()
			return fmt.Errorf("failed to apply migration %d (%s): %w", m.Version, m.Description, err)
		}

		if _, err := tx.Exec(
			"INSERT INTO _migrations (Version, Description) VALUES (?, ?)",
			m.Version, m.Description,
		); err != nil {
			tx.Rollback()
			return fmt.Errorf("failed to record migration %d: %w", m.Version, err)
		}

		if err := tx.Commit(); err != nil {
			return fmt.Errorf("failed to commit migration %d: %w", m.Version, err)
		}
	}

	return nil
}
