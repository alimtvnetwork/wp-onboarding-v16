// Package database - Migration definitions (SQL schema)
package database

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
	{
		Version:     6,
		Description: "Add RemotePluginsCache table for caching site plugin lists",
		SQL: `
			CREATE TABLE IF NOT EXISTS RemotePluginsCache (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				SiteId INTEGER NOT NULL UNIQUE,
				PluginsJSON TEXT NOT NULL,
				CachedAt TEXT DEFAULT (datetime('now')),
				ExpiresAt TEXT NOT NULL,
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
			);
			CREATE INDEX IF NOT EXISTS idx_remotepluginscache_site ON RemotePluginsCache(SiteId);
			CREATE INDEX IF NOT EXISTS idx_remotepluginscache_expires ON RemotePluginsCache(ExpiresAt);
		`,
	},
	{
		Version:     7,
		Description: "Add ErrorHistory table for persistent error/notification storage",
		SQL: `
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
	{
		Version:     10,
		Description: "SiteCredentials table for multi-user per site",
		SQL: `
			CREATE TABLE IF NOT EXISTS SiteCredentials (
				Id INTEGER PRIMARY KEY AUTOINCREMENT,
				SiteId INTEGER NOT NULL,
				AppName TEXT NOT NULL,
				Username TEXT NOT NULL,
				PasswordEncrypted BLOB NOT NULL,
				IsDefault INTEGER DEFAULT 0,
				ConnectionStatus TEXT DEFAULT 'unknown',
				LastTestedAt TEXT,
				CreatedAt TEXT DEFAULT (datetime('now')),
				UpdatedAt TEXT DEFAULT (datetime('now')),
				FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE,
				UNIQUE(SiteId, Username, AppName)
			);
			CREATE INDEX IF NOT EXISTS idx_sitecredentials_site ON SiteCredentials(SiteId);
			CREATE INDEX IF NOT EXISTS idx_sitecredentials_default ON SiteCredentials(SiteId, IsDefault);

			-- Migrate existing credentials from Sites table into SiteCredentials
			INSERT OR IGNORE INTO SiteCredentials (SiteId, AppName, Username, PasswordEncrypted, IsDefault, ConnectionStatus, CreatedAt, UpdatedAt)
			SELECT Id, 'default', Username, PasswordEncrypted, 1, ConnectionStatus, CreatedAt, UpdatedAt
			FROM Sites
			WHERE Username != '' AND PasswordEncrypted IS NOT NULL;
		`,
	},
}
