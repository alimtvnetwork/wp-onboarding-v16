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

	if err := os.MkdirAll(cfg.DataDir, 0755); err != nil {
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
	if err := configureDB(rootDB); err != nil {
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

	if err := manager.initRootSchema(); err != nil {
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
		if _, err := db.Exec(pragma); err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseConnect, "failed to execute pragma").
				WithDetails(pragma)
		}
	}

	return nil
}

// initRootSchema creates the root database schema
func (m *DBManager) initRootSchema() error {
	schema := `
		CREATE TABLE IF NOT EXISTS projects (
			id TEXT PRIMARY KEY,
			slug TEXT UNIQUE NOT NULL,
			display_name TEXT NOT NULL,
			path TEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			status TEXT DEFAULT 'active'
		);

		CREATE INDEX IF NOT EXISTS idx_projects_slug ON projects(slug);
		CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);

		CREATE TABLE IF NOT EXISTS databases (
			id TEXT PRIMARY KEY,
			project_id TEXT NOT NULL,
			type TEXT NOT NULL,
			entity_id TEXT,
			path TEXT NOT NULL,
			size_bytes INTEGER DEFAULT 0,
			record_count INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			last_accessed_at DATETIME,
			status TEXT DEFAULT 'active',
			FOREIGN KEY (project_id) REFERENCES projects(id)
		);

		CREATE INDEX IF NOT EXISTS idx_databases_project ON databases(project_id);
		CREATE INDEX IF NOT EXISTS idx_databases_type ON databases(type);
		CREATE INDEX IF NOT EXISTS idx_databases_entity ON databases(entity_id);

		CREATE TABLE IF NOT EXISTS database_stats (
			id TEXT PRIMARY KEY,
			database_id TEXT NOT NULL,
			recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			size_bytes INTEGER,
			record_count INTEGER,
			query_count INTEGER DEFAULT 0,
			avg_query_ms REAL,
			FOREIGN KEY (database_id) REFERENCES databases(id)
		);

		CREATE INDEX IF NOT EXISTS idx_stats_database ON database_stats(database_id);
		CREATE INDEX IF NOT EXISTS idx_stats_recorded ON database_stats(recorded_at);
	`

	_, err := m.rootDB.Exec(schema)
	return err
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
	if db, ok := m.openDBs[key]; ok {
		m.log.Debug("Database cached", "key", key, "duration_ms", time.Since(startTime).Milliseconds())
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
	dbRecord, err := m.getOrCreateDatabase(project.ID, dbType, entityID, dbPath)
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
	if err := os.MkdirAll(dir, 0755); err != nil {
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

	if err := configureDB(db); err != nil {
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
		"duration_ms", time.Since(startTime).Milliseconds(),
	)

	return db, nil
}

// getOrCreateProject ensures a project exists and returns it
func (m *DBManager) getOrCreateProject(slug string) (*Project, error) {
	var project Project

	err := m.rootDB.QueryRow(`
		SELECT id, slug, display_name, path, status, created_at, updated_at
		FROM projects WHERE slug = ?
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
			INSERT INTO projects (id, slug, display_name, path, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)
		`, project.ID, project.Slug, project.DisplayName, project.Path,
			project.Status, project.CreatedAt, project.UpdatedAt)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create project").
				WithSlug(slug)
		}

		m.log.Info("Created project", "slug", slug, "id", project.ID)
	} else if err != nil {
		return nil, err
	}

	return &project, nil
}

// getOrCreateDatabase ensures a database record exists
func (m *DBManager) getOrCreateDatabase(projectID, dbType, entityID, path string) (*Database, error) {
	var db Database

	query := `SELECT id, project_id, type, entity_id, path, size_bytes, record_count, 
	          status, created_at, updated_at FROM databases 
	          WHERE project_id = ? AND type = ? AND entity_id = ?`

	err := m.rootDB.QueryRow(query, projectID, dbType, entityID).Scan(
		&db.ID, &db.ProjectID, &db.Type, &db.EntityID, &db.Path,
		&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		db = Database{
			ID:          generateID(),
			ProjectID:   projectID,
			Type:        dbType,
			EntityID:    entityID,
			Path:        path,
			Status:      "active",
			CreatedAt:   time.Now(),
			UpdatedAt:   time.Now(),
		}

		_, err = m.rootDB.Exec(`
			INSERT INTO databases (id, project_id, type, entity_id, path, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		`, db.ID, db.ProjectID, db.Type, db.EntityID, db.Path, db.Status, db.CreatedAt, db.UpdatedAt)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create database record").
				WithDetails(fmt.Sprintf("type=%s, entityId=%s", dbType, entityID))
		}
	} else if err != nil {
		return nil, err
	}

	return &db, nil
}

// buildDBPath constructs the path for a database file
func (m *DBManager) buildDBPath(projectSlug, dbType, entityID string) string {
	if entityID == "" {
		return filepath.Join(projectSlug, dbType+".db")
	}
	return filepath.Join(projectSlug, dbType, GenerateSlug(entityID)+".db")
}

// updateLastAccessed updates the last accessed timestamp
func (m *DBManager) updateLastAccessed(dbID string) {
	_, err := m.rootDB.Exec(`
		UPDATE databases SET last_accessed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, dbID)
	if err != nil {
		m.log.Warn("Failed to update last_accessed_at", "error", err, "db_id", dbID)
	}
}

// ListProjects returns all active projects
func (m *DBManager) ListProjects() ([]Project, error) {
	rows, err := m.rootDB.Query(`
		SELECT id, slug, display_name, path, status, created_at, updated_at
		FROM projects WHERE status = 'active'
		ORDER BY display_name
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var projects []Project
	for rows.Next() {
		var p Project
		if err := rows.Scan(&p.ID, &p.Slug, &p.DisplayName, &p.Path, &p.Status, &p.CreatedAt, &p.UpdatedAt); err != nil {
			return nil, err
		}
		projects = append(projects, p)
	}

	return projects, nil
}

// ListDatabases returns all databases for a project
func (m *DBManager) ListDatabases(projectSlug string) ([]Database, error) {
	query := `
		SELECT d.id, d.project_id, d.type, d.entity_id, d.path, 
		       d.size_bytes, d.record_count, d.status, d.created_at, d.updated_at
		FROM databases d
		JOIN projects p ON d.project_id = p.id
		WHERE p.slug = ? AND d.status = 'active'
	`

	rows, err := m.rootDB.Query(query, projectSlug)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var dbs []Database
	for rows.Next() {
		var db Database
		if err := rows.Scan(
			&db.ID, &db.ProjectID, &db.Type, &db.EntityID, &db.Path,
			&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
		); err != nil {
			return nil, err
		}
		dbs = append(dbs, db)
	}

	return dbs, nil
}

// ArchiveStale archives databases not accessed within maxAge
func (m *DBManager) ArchiveStale(maxAge time.Duration) error {
	cutoff := time.Now().Add(-maxAge)

	result, err := m.rootDB.Exec(`
		UPDATE databases 
		SET status = 'archived', updated_at = CURRENT_TIMESTAMP
		WHERE last_accessed_at < ? AND status = 'active'
	`, cutoff)
	if err != nil {
		return err
	}

	affected, _ := result.RowsAffected()
	if affected > 0 {
		m.log.Info("Archived stale databases", "count", affected, "max_age", maxAge.String())
	}

	return nil
}

// PurgeArchived deletes archived databases older than retention period
func (m *DBManager) PurgeArchived(retention time.Duration) error {
	cutoff := time.Now().Add(-retention)

	// Get databases to delete
	rows, err := m.rootDB.Query(`
		SELECT path FROM databases 
		WHERE status = 'archived' AND updated_at < ?
	`, cutoff)
	if err != nil {
		return err
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
		if err := os.Remove(fullPath); err != nil && !os.IsNotExist(err) {
			m.log.Warn("Failed to delete archived database file", "path", pathutil.ForDisplay(fullPath), "error", err)
		} else {
			deleted++
		}
	}

	// Remove records
	_, err = m.rootDB.Exec(`
		DELETE FROM databases 
		WHERE status = 'archived' AND updated_at < ?
	`, cutoff)

	if deleted > 0 {
		m.log.Info("Purged archived databases", "count", deleted)
	}

	return err
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
