package database

import (
	"database/sql"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

// PluginVersionInput bundles parameters for CreatePluginVersion.
type PluginVersionInput struct {
	PluginId      int64
	SiteId        int64
	Version       string
	BackupPath    string
	FilesUpdated  int
	GitCommitHash string
	PublishType   string
	Notes         string
}

// CreatePluginVersion records a new version entry after a publish operation
func (db *DB) CreatePluginVersion(input PluginVersionInput) (int64, error) {
	result, err := db.Exec(insertVersionSQL,
		input.PluginId,
		input.SiteId,
		input.Version,
		input.BackupPath,
		input.FilesUpdated,
		input.GitCommitHash,
		input.PublishType,
		input.Notes,
	)

	if err != nil {

		return 0, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create plugin version").
			WithDetails(fmt.Sprintf("pluginId=%d, siteId=%d", input.PluginId, input.SiteId))
	}

	return result.LastInsertId()
}

// PluginVersionRow holds a single plugin version record from the database.
type PluginVersionRow struct {
	Id            int64
	PluginId      int64
	SiteId        int64
	SiteName      string
	Version       string
	BackupPath    string
	FilesUpdated  int64
	GitCommitHash string
	PublishType   string
	Status        string
	Notes         string
	CreatedAt     string
}

// GetPluginVersions returns version history for a plugin, optionally filtered by site
func (db *DB) GetPluginVersions(pluginID int64, siteID *int64, limit int) ([]PluginVersionRow, error) {
	query, args := buildVersionQuery(pluginID, siteID, limit)

	rows, err := db.Query(query, args...)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query plugin versions").
			WithDetails(fmt.Sprintf("pluginId=%d", pluginID))
	}
	defer rows.Close()

	return scanPluginVersionRows(rows)
}

// buildVersionQuery constructs the version query with optional site filter.
func buildVersionQuery(pluginID int64, siteID *int64, limit int) (string, []any) {
	query := selectVersionSQL + " WHERE pv.PluginId = ?"
	args := []any{pluginID}

	hasSiteFilter := siteID != nil && *siteID > 0

	if hasSiteFilter {
		query += " AND pv.SiteId = ?"
		args = append(args, *siteID)
	}

	query += " ORDER BY pv.CreatedAt DESC LIMIT ?"
	args = append(args, limit)

	return query, args
}

// scanPluginVersionRows scans all rows into PluginVersionRow slices.
func scanPluginVersionRows(rows *sql.Rows) ([]PluginVersionRow, error) {
	var versions []PluginVersionRow

	for rows.Next() {
		m, err := scanVersionRow(rows)
		if err != nil {

			continue
		}

		versions = append(versions, m)
	}

	isVersionsEmpty := versions == nil

	if isVersionsEmpty {
		versions = []PluginVersionRow{}
	}

	return versions, nil
}

// versionNullFields holds nullable fields for version row scanning.
type versionNullFields struct {
	SiteName      sql.NullString
	Version       sql.NullString
	BackupPath    sql.NullString
	GitCommitHash sql.NullString
	PublishType   sql.NullString
	Status        sql.NullString
	Notes         sql.NullString
	CreatedAt     sql.NullString
}

// scanVersionRow scans a single version row from *sql.Rows.
func scanVersionRow(rows *sql.Rows) (PluginVersionRow, error) {
	var m PluginVersionRow
	var nf versionNullFields

	err := rows.Scan(
		&m.Id,
		&m.PluginId,
		&m.SiteId,
		&nf.SiteName,
		&nf.Version,
		&nf.BackupPath,
		&m.FilesUpdated,
		&nf.GitCommitHash,
		&nf.PublishType,
		&nf.Status,
		&nf.Notes,
		&nf.CreatedAt,
	)

	if err != nil {

		return m, err
	}

	applyVersionNullFields(&m, &nf)

	return m, nil
}

// applyVersionNullFields applies nullable fields to the version row.
func applyVersionNullFields(m *PluginVersionRow, nf *versionNullFields) {
	m.SiteName = nf.SiteName.String
	m.Version = nf.Version.String
	m.BackupPath = nf.BackupPath.String
	m.GitCommitHash = nf.GitCommitHash.String
	m.PublishType = nf.PublishType.String
	m.Status = nf.Status.String
	m.Notes = nf.Notes.String
	m.CreatedAt = nf.CreatedAt.String
}

// GetPluginVersionById returns a specific version entry
func (db *DB) GetPluginVersionById(versionID int64) (*PluginVersionRow, error) {
	var m PluginVersionRow
	var nf versionNullFields

	err := db.QueryRow(selectVersionSQL+" WHERE pv.Id = ?", versionID).Scan(
		&m.Id,
		&m.PluginId,
		&m.SiteId,
		&nf.SiteName,
		&nf.Version,
		&nf.BackupPath,
		&m.FilesUpdated,
		&nf.GitCommitHash,
		&nf.PublishType,
		&nf.Status,
		&nf.Notes,
		&nf.CreatedAt,
	)

	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin version by ID").
			WithDetails(fmt.Sprintf("versionId=%d", versionID))
	}

	applyVersionNullFields(&m, &nf)

	return &m, nil
}

// DeletePluginVersion removes a version entry
func (db *DB) DeletePluginVersion(versionID int64) error {
	_, err := db.Exec("DELETE FROM PluginVersions WHERE Id = ?", versionID)

	if err != nil {

		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete plugin version").
			WithDetails(fmt.Sprintf("versionId=%d", versionID))
	}

	return nil
}

// GetNextVersionNumber generates the next version number for a plugin-site combination
func (db *DB) GetNextVersionNumber(pluginID, siteID int64) (string, error) {
	var count int

	err := db.QueryRow(
		"SELECT COUNT(*) FROM PluginVersions WHERE PluginId = ? AND SiteId = ?",
		pluginID,
		siteID,
	).Scan(&count)

	if err != nil {

		return "1.0.0", nil
	}

	return fmt.Sprintf("1.0.%d", count+1), nil
}

// SQL constants

const selectVersionSQL = `SELECT
	pv.Id,
	pv.PluginId,
	pv.SiteId,
	s.Name as SiteName,
	pv.Version,
	pv.BackupPath,
	pv.FilesUpdated,
	pv.GitCommitHash,
	pv.PublishType,
	pv.Status,
	pv.Notes,
	pv.CreatedAt
FROM PluginVersions pv
LEFT JOIN Sites s ON pv.SiteId = s.Id`

const insertVersionSQL = `INSERT INTO PluginVersions (
	PluginId,
	SiteId,
	Version,
	BackupPath,
	FilesUpdated,
	GitCommitHash,
	PublishType,
	Status,
	Notes,
	CreatedAt
) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, datetime('now'))`
