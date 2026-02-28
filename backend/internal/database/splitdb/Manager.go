// Package splitdb provides hierarchical SQLite database management
package splitdb

import (
	"archive/zip"
	"database/sql"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"time"

	_ "github.com/mattn/go-sqlite3"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// DBManager manages a hierarchical split database structure
type DBManager struct {
	rootDB   *sql.DB
	dataDir  string
	openDBs  map[string]*sql.DB
	mu       sync.RWMutex
	log      *logger.Logger
	maxOpen  int
	maxIdle  int
	connLife time.Duration
}

// Project represents a project in the split database
type Project struct {
	ID          string
	Slug        string
	DisplayName string
	Path        string
	Status      string
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

// Database represents a child database record
type Database struct {
	ID           string
	ProjectID    string
	Type         string
	EntityID     string
	Path         string
	SizeBytes    int64
	RecordCount  int64
	Status       string
	CreatedAt    time.Time
	UpdatedAt    time.Time
	LastAccessed *time.Time
}

// DatabaseStats holds statistics for a database
type DatabaseStats struct {
	ID          string
	DatabaseID  string
	RecordedAt  time.Time
	SizeBytes   int64
	RecordCount int64
	QueryCount  int64
	AvgQueryMs  float64
}

// Config holds DBManager configuration
type Config struct {
	DataDir  string
	Logger   *logger.Logger
	MaxOpen  int
	MaxIdle  int
	ConnLife time.Duration
}

// NewDBManager creates a new split database manager
func NewDBManager(cfg Config) (*DBManager, error) {
	if cfg.MaxOpen == 0 {
		cfg.MaxOpen = 50
	}
	if cfg.MaxIdle == 0 {
		cfg.MaxIdle = 2
	}
	if cfg.ConnLife == 0 {
		cfg.ConnLife = time.Hour
	}

	err := os.MkdirAll(cfg.DataDir, 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to create data dir").
			WithPath(cfg.DataDir)
	}

	rootPath, err := pathutil.Join(cfg.DataDir, "root.db")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to resolve root db path")
	}
	rootDB, err := sql.Open("sqlite3", rootPath+"?_foreign_keys=on&_journal_mode=WAL")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to open root db").
			WithPath(rootPath)
	}

	// Configure root DB
	err = configureDB(rootDB)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to configure root db").
			WithPath(rootPath)
	}

	manager := &DBManager{
		rootDB:   rootDB,
		dataDir:  cfg.DataDir,
		openDBs:  make(map[string]*sql.DB),
		log:      cfg.Logger,
		maxOpen:  cfg.MaxOpen,
		maxIdle:  cfg.MaxIdle,
		connLife: cfg.ConnLife,
	}

	err = manager.initRootSchema()
	if err != nil {

		return nil, err
	}

	return manager, nil
}

// configureDB sets up SQLite for optimal concurrent access
func configureDB(db *sql.DB) error {
	pragmas := []string{
		"PRAGMA journal_mode=WAL",
		"PRAGMA busy_timeout=5000",
		"PRAGMA foreign_keys=ON",
		"PRAGMA synchronous=NORMAL",
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

// initRootSchema creates the root database schema and runs migrations
func (m *DBManager) initRootSchema() error {
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
func (m *DBManager) migrateToPascalCase() error {
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

// GetOrCreateDB returns a database, creating it if it doesn't exist
func (m *DBManager) GetOrCreateDB(projectSlug, dbType, entityID string) (*sql.DB, error) {
	startTime := time.Now()

	m.log.Debug("GetOrCreateDB called",
		"project", projectSlug,
		"type", dbType,
		"entity", entityID,
	)

	m.mu.Lock()
	defer m.mu.Unlock()

	// Check if already open
	key := fmt.Sprintf("%s/%s/%s", projectSlug, dbType, entityID)
	db, isCached := m.openDBs[key]

	if isCached {
		m.log.Debug("Database cached", "key", key, "durationMs", time.Since(startTime).Milliseconds())

		return db, nil
	}

	// Ensure project exists
	project, err := m.getOrCreateProject(projectSlug)
	if err != nil {
		m.log.Error("Failed to get/create project", "error", err, "project", projectSlug)
		return nil, err
	}

	// Get or create database record
	dbPath := m.buildDBPath(projectSlug, dbType, entityID)
	dbRecord, err := m.getOrCreateDatabase(GetOrCreateDBInput{ProjectID: project.ID, DBType: dbType, EntityID: entityID, Path: dbPath})
	if err != nil {
		m.log.Error("Failed to get/create database record", "error", err)
		return nil, err
	}

	// Ensure directory exists
	fullPath, err := pathutil.Join(m.dataDir, dbRecord.Path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve db path").
			WithPath(dbRecord.Path)
	}
	dir := filepath.Dir(fullPath)
	err = os.MkdirAll(dir, 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to create db dir").
			WithPath(dir)
	}

	// Open the database
	db, err := sql.Open("sqlite3", fullPath+"?_foreign_keys=on&_journal_mode=WAL")
	if err != nil {
		m.log.Error("Failed to open database", "error", err, "path", fullPath)
		return nil, apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to open db").
			WithPath(fullPath)
	}

	err = configureDB(db)
	if err != nil {
		db.Close()

		return nil, err
	}

	m.openDBs[key] = db

	// Update last accessed
	m.updateLastAccessed(dbRecord.ID)

	m.log.Info("Database ready",
		"project", projectSlug,
		"type", dbType,
		"entity", entityID,
		"durationMs", time.Since(startTime).Milliseconds(),
	)

	return db, nil
}

// getOrCreateProject ensures a project exists and returns it
func (m *DBManager) getOrCreateProject(slug string) (*Project, error) {
	var project Project

	err := m.rootDB.QueryRow(`
		SELECT Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt
		FROM Projects WHERE Slug = ?
	`, slug).Scan(
		&project.ID, &project.Slug, &project.DisplayName,
		&project.Path, &project.Status, &project.CreatedAt, &project.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		// Create new project
		project = Project{
			ID:          generateID(),
			Slug:        slug,
			DisplayName: slug,
			Path:        slug + "/",
			Status:      "active",
			CreatedAt:   time.Now(),
			UpdatedAt:   time.Now(),
		}

		_, err = m.rootDB.Exec(`
			INSERT INTO Projects (Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, ?, ?, ?, ?)
		`, project.ID, project.Slug, project.DisplayName, project.Path,
			project.Status, project.CreatedAt, project.UpdatedAt)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create project").
				WithSlug(slug)
		}

		m.log.Info("Created project", "slug", slug, "id", project.ID)
	} else if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query project").
			WithSlug(slug)
	}

	return &project, nil
}

// GetOrCreateDBInput bundles parameters for getOrCreateDatabase.
type GetOrCreateDBInput struct {
	ProjectID string
	DBType    string
	EntityID  string
	Path      string
}

// getOrCreateDatabase ensures a database record exists
func (m *DBManager) getOrCreateDatabase(input GetOrCreateDBInput) (*Database, error) {
	var db Database

	query := `SELECT Id, ProjectId, Type, EntityId, Path, SizeBytes, RecordCount, 
	          Status, CreatedAt, UpdatedAt FROM Databases 
	          WHERE ProjectId = ? AND Type = ? AND EntityId = ?`

	err := m.rootDB.QueryRow(query, input.ProjectID, input.DBType, input.EntityID).Scan(
		&db.ID, &db.ProjectID, &db.Type, &db.EntityID, &db.Path,
		&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		db = Database{
			ID:          generateID(),
			ProjectID:   input.ProjectID,
			Type:        input.DBType,
			EntityID:    input.EntityID,
			Path:        input.Path,
			Status:      "active",
			CreatedAt:   time.Now(),
			UpdatedAt:   time.Now(),
		}

		_, err = m.rootDB.Exec(`
			INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, Status, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		`, db.ID, db.ProjectID, db.Type, db.EntityID, db.Path, db.Status, db.CreatedAt, db.UpdatedAt)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create database record").
				WithDetails(fmt.Sprintf("type=%s, entityId=%s", input.DBType, input.EntityID))
		}
	} else if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query database record").
			WithDetails(fmt.Sprintf("type=%s, entityId=%s", dbType, entityID))
	}

	return &db, nil
}

// buildDBPath constructs the path for a database file
func (m *DBManager) buildDBPath(projectSlug, dbType, entityID string) string {
	isGlobalDB := entityID == ""

	if isGlobalDB {
		return filepath.Join(projectSlug, dbType+".db")
	}
	return filepath.Join(projectSlug, dbType, GenerateSlug(entityID)+".db")
}

// updateLastAccessed updates the last accessed timestamp
func (m *DBManager) updateLastAccessed(dbID string) {
	_, err := m.rootDB.Exec(`
		UPDATE Databases SET LastAccessedAt = CURRENT_TIMESTAMP, UpdatedAt = CURRENT_TIMESTAMP
		WHERE Id = ?
	`, dbID)
	if err != nil {
		m.log.Warn("Failed to update LastAccessedAt", "error", err, "dbId", dbID)
	}
}

// ListProjects returns all active projects
func (m *DBManager) ListProjects() ([]Project, error) {
	rows, err := m.rootDB.Query(`
		SELECT Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt
		FROM Projects WHERE Status = 'active'
		ORDER BY DisplayName
	`)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list projects")
	}
	defer rows.Close()

	var projects []Project
	for rows.Next() {
		var p Project
		err := rows.Scan(&p.ID, &p.Slug, &p.DisplayName, &p.Path, &p.Status, &p.CreatedAt, &p.UpdatedAt)
		if err != nil {

			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan project row")
		}
		projects = append(projects, p)
	}

	return projects, nil
}

// ListDatabases returns all databases for a project
func (m *DBManager) ListDatabases(projectSlug string) ([]Database, error) {
	query := `
		SELECT d.Id, d.ProjectId, d.Type, d.EntityId, d.Path, 
		       d.SizeBytes, d.RecordCount, d.Status, d.CreatedAt, d.UpdatedAt
		FROM Databases d
		JOIN Projects p ON d.ProjectId = p.Id
		WHERE p.Slug = ? AND d.Status = 'active'
	`

	rows, err := m.rootDB.Query(query, projectSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list databases").
			WithSlug(projectSlug)
	}
	defer rows.Close()

	var dbs []Database
	for rows.Next() {
		var db Database
		err := rows.Scan(
			&db.ID, &db.ProjectID, &db.Type, &db.EntityID, &db.Path,
			&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
		)
		if err != nil {

			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan database row")
		}
		dbs = append(dbs, db)
	}

	return dbs, nil
}

// ArchiveStale archives databases not accessed within maxAge
func (m *DBManager) ArchiveStale(maxAge time.Duration) error {
	cutoff := time.Now().Add(-maxAge)

	result, err := m.rootDB.Exec(`
		UPDATE Databases 
		SET Status = 'archived', UpdatedAt = CURRENT_TIMESTAMP
		WHERE LastAccessedAt < ? AND Status = 'active'
	`, cutoff)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to archive stale databases")
	}

	affected, _ := result.RowsAffected()
	hasArchivedDatabases := affected > 0

	if hasArchivedDatabases {
		m.log.Info("Archived stale databases", "count", affected, "maxAge", maxAge.String())
	}

	return nil
}

// PurgeArchived deletes archived databases older than retention period
func (m *DBManager) PurgeArchived(retention time.Duration) error {
	cutoff := time.Now().Add(-retention)

	// Get databases to delete
	rows, err := m.rootDB.Query(`
		SELECT Path FROM Databases 
		WHERE Status = 'archived' AND UpdatedAt < ?
	`, cutoff)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query archived databases for purge")
	}
	defer rows.Close()

	var deleted int
	for rows.Next() {
		var path string
		rows.Scan(&path)
		fullPath, err := pathutil.Join(m.dataDir, path)
		if err != nil {
			m.log.Warn("Failed to resolve archived database path", "path", path, "error", err)
			continue
		}
		appErr := pathutil.RemoveFile(fullPath, "fullPath")
		if appErr != nil {
			m.log.Warn("Failed to delete archived database file", "path", pathutil.ForDisplay(fullPath), "error", appErr)
		} else {
			deleted++
		}
	}

	// Remove records
	_, err = m.rootDB.Exec(`
		DELETE FROM Databases 
		WHERE Status = 'archived' AND UpdatedAt < ?
	`, cutoff)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to purge archived database records")
	}

	hasPurgedDatabases := deleted > 0

	if hasPurgedDatabases {
		m.log.Info("Purged archived databases", "count", deleted)
	}

	return nil
}

// closeProjectDBs closes all open databases for a project
func (m *DBManager) closeProjectDBs(projectSlug string) {
	prefix := projectSlug + "/"
	for key, db := range m.openDBs {
		if strings.HasPrefix(key, prefix) {
			db.Close()
			delete(m.openDBs, key)
		}
	}
}

// Close closes all open databases
func (m *DBManager) Close() error {
	m.mu.Lock()
	defer m.mu.Unlock()

	for _, db := range m.openDBs {
		db.Close()
	}
	m.openDBs = make(map[string]*sql.DB)

	return m.rootDB.Close()
}

// GenerateSlug converts a name to a URL-safe slug
func GenerateSlug(name string) string {
	slug := strings.ToLower(name)
	slug = regexp.MustCompile(`[^a-z0-9]+`).ReplaceAllString(slug, "-")
	slug = strings.Trim(slug, "-")
	return slug
}

// generateID generates a unique ID
func generateID() string {
	return fmt.Sprintf("%d", time.Now().UnixNano())
}
