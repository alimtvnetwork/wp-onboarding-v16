// Package splitdb — root schema initialization and migrations.
package splitdb

import "wp-plugin-publish/pkg/apperror"

// initRootSchema creates the root database schema and runs migrations
func (m *DBManager) initRootSchema() *apperror.AppError {
	schema := `
		CREATE TABLE IF NOT EXISTS Projects (
			Id TEXT PRIMARY KEY,
			Slug TEXT UNIQUE NOT NULL,
			DisplayName TEXT NOT NULL,
			Path TEXT NOT NULL,
			CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
			UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
			Status TEXT DEFAULT 'active'
		);

		CREATE INDEX IF NOT EXISTS IdxProjects_Slug ON Projects(Slug);
		CREATE INDEX IF NOT EXISTS IdxProjects_Status ON Projects(Status);

		CREATE TABLE IF NOT EXISTS Databases (
			Id TEXT PRIMARY KEY,
			ProjectId TEXT NOT NULL,
			Type TEXT NOT NULL,
			EntityId TEXT,
			Path TEXT NOT NULL,
			SizeBytes INTEGER DEFAULT 0,
			RecordCount INTEGER DEFAULT 0,
			CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
			UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
			LastAccessedAt DATETIME,
			Status TEXT DEFAULT 'active',
			FOREIGN KEY (ProjectId) REFERENCES Projects(Id)
		);

		CREATE INDEX IF NOT EXISTS IdxDatabases_ProjectId ON Databases(ProjectId);
		CREATE INDEX IF NOT EXISTS IdxDatabases_Type ON Databases(Type);
		CREATE INDEX IF NOT EXISTS IdxDatabases_EntityId ON Databases(EntityId);

		CREATE TABLE IF NOT EXISTS DatabaseStats (
			Id TEXT PRIMARY KEY,
			DatabaseId TEXT NOT NULL,
			RecordedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
			SizeBytes INTEGER,
			RecordCount INTEGER,
			QueryCount INTEGER DEFAULT 0,
			AvgQueryMs REAL,
			FOREIGN KEY (DatabaseId) REFERENCES Databases(Id)
		);

		CREATE INDEX IF NOT EXISTS IdxDatabaseStats_DatabaseId ON DatabaseStats(DatabaseId);
		CREATE INDEX IF NOT EXISTS IdxDatabaseStats_RecordedAt ON DatabaseStats(RecordedAt);
	`

	_, err := m.rootDB.Exec(schema)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseMigrate, "failed to create root schema")
	}

	return m.migrateToPascalCase()
}

// migrateToPascalCase renames legacy snake_case tables and columns to PascalCase
func (m *DBManager) migrateToPascalCase() *apperror.AppError {
	// Check if legacy tables exist
	var exists int
	err := m.rootDB.QueryRow(`SELECT 1 FROM sqlite_master WHERE type='table' AND name='projects'`).Scan(&exists)
	if err != nil {
		// No legacy tables — nothing to migrate
		return nil
	}

	m.log.Info("Migrating SplitDB to PascalCase naming")

	migrations := []string{
		// ── Table renames ──
		`ALTER TABLE projects RENAME TO Projects`,
		`ALTER TABLE databases RENAME TO Databases`,
		`ALTER TABLE database_stats RENAME TO DatabaseStats`,

		// ── Projects column renames ──
		`ALTER TABLE Projects RENAME COLUMN id TO Id`,
		`ALTER TABLE Projects RENAME COLUMN slug TO Slug`,
		`ALTER TABLE Projects RENAME COLUMN display_name TO DisplayName`,
		`ALTER TABLE Projects RENAME COLUMN path TO Path`,
		`ALTER TABLE Projects RENAME COLUMN created_at TO CreatedAt`,
		`ALTER TABLE Projects RENAME COLUMN updated_at TO UpdatedAt`,
		`ALTER TABLE Projects RENAME COLUMN status TO Status`,

		// ── Databases column renames ──
		`ALTER TABLE Databases RENAME COLUMN id TO Id`,
		`ALTER TABLE Databases RENAME COLUMN project_id TO ProjectId`,
		`ALTER TABLE Databases RENAME COLUMN type TO Type`,
		`ALTER TABLE Databases RENAME COLUMN entity_id TO EntityId`,
		`ALTER TABLE Databases RENAME COLUMN path TO Path`,
		`ALTER TABLE Databases RENAME COLUMN size_bytes TO SizeBytes`,
		`ALTER TABLE Databases RENAME COLUMN record_count TO RecordCount`,
		`ALTER TABLE Databases RENAME COLUMN created_at TO CreatedAt`,
		`ALTER TABLE Databases RENAME COLUMN updated_at TO UpdatedAt`,
		`ALTER TABLE Databases RENAME COLUMN last_accessed_at TO LastAccessedAt`,
		`ALTER TABLE Databases RENAME COLUMN status TO Status`,

		// ── DatabaseStats column renames ──
		`ALTER TABLE DatabaseStats RENAME COLUMN id TO Id`,
		`ALTER TABLE DatabaseStats RENAME COLUMN database_id TO DatabaseId`,
		`ALTER TABLE DatabaseStats RENAME COLUMN recorded_at TO RecordedAt`,
		`ALTER TABLE DatabaseStats RENAME COLUMN size_bytes TO SizeBytes`,
		`ALTER TABLE DatabaseStats RENAME COLUMN record_count TO RecordCount`,
		`ALTER TABLE DatabaseStats RENAME COLUMN query_count TO QueryCount`,
		`ALTER TABLE DatabaseStats RENAME COLUMN avg_query_ms TO AvgQueryMs`,
	}

	for _, stmt := range migrations {
		_, err := m.rootDB.Exec(stmt)
		if err != nil {
			// Column/table may already be renamed — log and continue
			m.log.Debug("Migration step skipped (may already be applied)", "stmt", stmt, "error", err)
		}
	}

	m.log.Info("SplitDB PascalCase migration complete")

	return nil
}
