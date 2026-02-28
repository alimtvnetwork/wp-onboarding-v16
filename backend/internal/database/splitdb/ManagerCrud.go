// Package splitdb — CRUD operations for projects and databases.
package splitdb

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

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

	db, err = m.openAndConfigure(dbRecord, key)
	if err != nil {

		return nil, err
	}

	m.log.Info("Database ready",
		"project", projectSlug,
		"type", dbType,
		"entity", entityID,
		"durationMs", time.Since(startTime).Milliseconds(),
	)

	return db, nil
}

// openAndConfigure opens and configures a SQLite database file.
func (m *DBManager) openAndConfigure(dbRecord *Database, key string) (*sql.DB, error) {
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
	m.updateLastAccessed(dbRecord.ID)

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
		return m.insertProject(slug)
	}

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query project").
			WithSlug(slug)
	}

	return &project, nil
}

// insertProject creates a new project record.
func (m *DBManager) insertProject(slug string) (*Project, error) {
	project := Project{
		ID:          generateID(),
		Slug:        slug,
		DisplayName: slug,
		Path:        slug + "/",
		Status:      "active",
		CreatedAt:   time.Now(),
		UpdatedAt:   time.Now(),
	}

	_, err := m.rootDB.Exec(`
		INSERT INTO Projects (Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, ?)
	`, project.ID, project.Slug, project.DisplayName, project.Path,
		project.Status, project.CreatedAt, project.UpdatedAt)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create project").
			WithSlug(slug)
	}

	m.log.Info("Created project", "slug", slug, "id", project.ID)

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
		return m.insertDatabase(input)
	}

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query database record").
			WithDetails(fmt.Sprintf("type=%s, entityId=%s", input.DBType, input.EntityID))
	}

	return &db, nil
}

// insertDatabase creates a new database record.
func (m *DBManager) insertDatabase(input GetOrCreateDBInput) (*Database, error) {
	db := Database{
		ID:        generateID(),
		ProjectID: input.ProjectID,
		Type:      input.DBType,
		EntityID:  input.EntityID,
		Path:      input.Path,
		Status:    "active",
		CreatedAt: time.Now(),
		UpdatedAt: time.Now(),
	}

	_, err := m.rootDB.Exec(`
		INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, Status, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)
	`, db.ID, db.ProjectID, db.Type, db.EntityID, db.Path, db.Status, db.CreatedAt, db.UpdatedAt)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create database record").
			WithDetails(fmt.Sprintf("type=%s, entityId=%s", input.DBType, input.EntityID))
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
