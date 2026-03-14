package database

func init() {
	migrations = append(migrations,
		Migration{
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
		Migration{
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
		Migration{
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
		Migration{
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
		Migration{
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

				INSERT OR IGNORE INTO SiteCredentials (SiteId, AppName, Username, PasswordEncrypted, IsDefault, ConnectionStatus, CreatedAt, UpdatedAt)
				SELECT Id, 'default', Username, PasswordEncrypted, 1, ConnectionStatus, CreatedAt, UpdatedAt
				FROM Sites
				WHERE Username != '' AND PasswordEncrypted IS NOT NULL;
			`,
		},
	)
}
