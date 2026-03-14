// Package database - Migration definitions (SQL schema)
package database

// Migration represents a database migration
type Migration struct {
	Version     int
	Description string
	SQL         string
}

// migrations is the list of all database migrations (core schema, migrations 1-5).
// Extended migrations (6+) are appended in MigrationsDataExt.go via init().
var migrations = []Migration{
	{
		Version:     1,
		Description: "Initial schema",
		SQL: `
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
			CREATE TABLE IF NOT EXISTS AppConfig (
				Key TEXT PRIMARY KEY,
				Value TEXT NOT NULL,
				UpdatedAt TEXT DEFAULT (datetime('now'))
			);
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
			ALTER TABLE Sites ADD COLUMN Category TEXT DEFAULT '';
			ALTER TABLE Plugins ADD COLUMN Category TEXT DEFAULT '';
			CREATE INDEX IF NOT EXISTS idx_sites_category ON Sites(Category);
			CREATE INDEX IF NOT EXISTS idx_plugins_category ON Plugins(Category);
		`,
	},
	{
		Version:     4,
		Description: "Add AutoPublish and SeedVersion support",
		SQL: `
			ALTER TABLE Plugins ADD COLUMN AutoPublish INTEGER DEFAULT 0;
			INSERT OR IGNORE INTO AppConfig (Key, Value) VALUES ('seed.version', '');
			INSERT OR IGNORE INTO AppConfig (Key, Value) VALUES ('db.version', '1.8.0');
		`,
	},
	{
		Version:     5,
		Description: "Add PluginVersions table for version history and rollback",
		SQL: `
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
			CREATE INDEX IF NOT EXISTS idx_pluginversions_plugin ON PluginVersions(PluginId);
			CREATE INDEX IF NOT EXISTS idx_pluginversions_site ON PluginVersions(SiteId);
			CREATE INDEX IF NOT EXISTS idx_pluginversions_created ON PluginVersions(CreatedAt DESC);
		`,
	},
}
