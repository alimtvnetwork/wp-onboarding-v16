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
func (m *DBManager) GetOrCreateDB(projectSlug, dbType, entityId string) (*sql.DB, error) {
	startTime := time.Now()

	m.log.Debug("GetOrCreateDB called",
		"project", projectSlug,
		"type", dbType,
		"entity", entityId,
	)

	m.mu.Lock()
	defer m.mu.Unlock()

	// Check if already open
	key := fmt.Sprintf("%s/%s/%s", projectSlug, dbType, entityId)
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
	dbPath := m.buildDBPath(projectSlug, dbType, entityId)
	dbInput := GetOrCreateDBInput{
		ProjectId: project.Id,
		DBType:    dbType,
		EntityId:  entityId,
		Path:      dbPath,
	}
	dbRecord, err := m.getOrCreateDatabase(dbInput)
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
		"entity", entityId,
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
	m.updateLastAccessed(dbRecord.Id)

	return db, nil
}

// getOrCreateProject ensures a project exists and returns it
func (m *DBManager) getOrCreateProject(slug string) (*Project, error) {
	var project Project

	err := m.rootDB.QueryRow(`
		SELECT Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt
		FROM Projects WHERE Slug = ?
	`, slug).Scan(
		&project.Id, &project.Slug, &project.DisplayName,
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
		Id:          generateId(),
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
	`, project.Id, project.Slug, project.DisplayName, project.Path,
		project.Status, project.CreatedAt, project.UpdatedAt)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create project").
			WithSlug(slug)
	}

	m.log.Info("Created project", "slug", slug, "id", project.Id)

	return &project, nil
}

// GetOrCreateDBInput bundles parameters for getOrCreateDatabase.
type GetOrCreateDBInput struct {
	ProjectId string
	DBType    string
	EntityId  string
	Path      string
}

// getOrCreateDatabase ensures a database record exists
func (m *DBManager) getOrCreateDatabase(input GetOrCreateDBInput) (*Database, error) {
	var db Database

	query := `SELECT Id, ProjectId, Type, EntityId, Path, SizeBytes, RecordCount, 
	          Status, CreatedAt, UpdatedAt FROM Databases 
	          WHERE ProjectId = ? AND Type = ? AND EntityId = ?`

	err := m.rootDB.QueryRow(query, input.ProjectId, input.DBType, input.EntityId).Scan(
		&db.Id, &db.ProjectId, &db.Type, &db.EntityId, &db.Path,
		&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
	)

	if err == sql.ErrNoRows {
		return m.insertDatabase(input)
	}

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query database record").
			WithDetails(fmt.Sprintf("type=%s, entityId=%s", input.DBType, input.EntityId))
	}

	return &db, nil
}

// insertDatabase creates a new database record.
func (m *DBManager) insertDatabase(input GetOrCreateDBInput) (*Database, error) {
	db := Database{
		Id:        generateId(),
		ProjectId: input.ProjectId,
		Type:      input.DBType,
		EntityId:  input.EntityId,
		Path:      input.Path,
		Status:    "active",
		CreatedAt: time.Now(),
		UpdatedAt: time.Now(),
	}

	_, err := m.rootDB.Exec(`
		INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, Status, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)
	`, db.Id, db.ProjectId, db.Type, db.EntityId, db.Path, db.Status, db.CreatedAt, db.UpdatedAt)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create database record").
			WithDetails(fmt.Sprintf("type=%s, entityId=%s", input.DBType, input.EntityId))
	}

	return &db, nil
}

// buildDBPath constructs the path for a database file
func (m *DBManager) buildDBPath(projectSlug, dbType, entityId string) string {
	isGlobalDB := entityId == ""

	if isGlobalDB {
		return filepath.Join(projectSlug, dbType+".db")
	}
	return filepath.Join(projectSlug, dbType, GenerateSlug(entityId)+".db")
}

// updateLastAccessed updates the last accessed timestamp
func (m *DBManager) updateLastAccessed(dbId string) {
	_, err := m.rootDB.Exec(`
		UPDATE Databases SET LastAccessedAt = CURRENT_TIMESTAMP, UpdatedAt = CURRENT_TIMESTAMP
		WHERE Id = ?
	`, dbId)
	if err != nil {
		m.log.Warn("Failed to update LastAccessedAt", "error", err, "dbId", dbId)
	}
}
