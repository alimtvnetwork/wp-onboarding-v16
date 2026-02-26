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
	result, err := db.Exec(`
		INSERT INTO PluginVersions (PluginId, SiteId, Version, BackupPath, FilesUpdated, GitCommitHash, PublishType, Status, Notes, CreatedAt)
		VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, datetime('now'))
	`, input.PluginId, input.SiteId, input.Version, input.BackupPath, input.FilesUpdated, input.GitCommitHash, input.PublishType, input.Notes)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to create plugin version").
			WithDetails(fmt.Sprintf("pluginId=%d, siteId=%d", input.PluginId, input.SiteId))
	}
	return result.LastInsertId()
}

// PluginVersionRow holds a single plugin version record from the database.
type PluginVersionRow struct {
	ID            int64
	PluginID      int64
	SiteID        int64
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
	query := `
		SELECT pv.Id, pv.PluginId, pv.SiteId, s.Name as SiteName, pv.Version, pv.BackupPath, 
			   pv.FilesUpdated, pv.GitCommitHash, pv.PublishType, pv.Status, pv.Notes, pv.CreatedAt
		FROM PluginVersions pv
		LEFT JOIN Sites s ON pv.SiteId = s.Id
		WHERE pv.PluginId = ?
	`
	args := []any{pluginID}

	if siteID != nil && *siteID > 0 {
		query += " AND pv.SiteId = ?"
		args = append(args, *siteID)
	}

	query += " ORDER BY pv.CreatedAt DESC LIMIT ?"
	args = append(args, limit)

	rows, err := db.Query(query, args...)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to query plugin versions").
			WithDetails(fmt.Sprintf("pluginId=%d", pluginID))
	}
	defer rows.Close()

	return scanPluginVersionRows(rows)
}

// scanPluginVersionRows scans all rows into PluginVersionRow slices.
func scanPluginVersionRows(rows *sql.Rows) ([]PluginVersionRow, error) {
	var versions []PluginVersionRow
	for rows.Next() {
		var v PluginVersionRow
		var siteName, version, backupPath, gitCommitHash, publishType, status, notes, createdAt sql.NullString

		err := rows.Scan(&v.ID, &v.PluginID, &v.SiteID, &siteName, &version, &backupPath,
			&v.FilesUpdated, &gitCommitHash, &publishType, &status, &notes, &createdAt)
		if err != nil {
			continue
		}

		v.SiteName = siteName.String
		v.Version = version.String
		v.BackupPath = backupPath.String
		v.GitCommitHash = gitCommitHash.String
		v.PublishType = publishType.String
		v.Status = status.String
		v.Notes = notes.String
		v.CreatedAt = createdAt.String
		versions = append(versions, v)
	}

	if versions == nil {
		versions = []PluginVersionRow{}
	}
	return versions, nil
}

// GetPluginVersionByID returns a specific version entry
func (db *DB) GetPluginVersionByID(versionID int64) (*PluginVersionRow, error) {
	var v PluginVersionRow
	var siteName, version, backupPath, gitCommitHash, publishType, status, notes, createdAt sql.NullString

	err := db.QueryRow(`
		SELECT pv.Id, pv.PluginId, pv.SiteId, s.Name as SiteName, pv.Version, pv.BackupPath, 
			   pv.FilesUpdated, pv.GitCommitHash, pv.PublishType, pv.Status, pv.Notes, pv.CreatedAt
		FROM PluginVersions pv
		LEFT JOIN Sites s ON pv.SiteId = s.Id
		WHERE pv.Id = ?
	`, versionID).Scan(&v.ID, &v.PluginID, &v.SiteID, &siteName, &version, &backupPath,
		&v.FilesUpdated, &gitCommitHash, &publishType, &status, &notes, &createdAt)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin version by ID").
			WithDetails(fmt.Sprintf("versionId=%d", versionID))
	}

	v.SiteName = siteName.String
	v.Version = version.String
	v.BackupPath = backupPath.String
	v.GitCommitHash = gitCommitHash.String
	v.PublishType = publishType.String
	v.Status = status.String
	v.Notes = notes.String
	v.CreatedAt = createdAt.String
	return &v, nil
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
	err := db.QueryRow(`
		SELECT COUNT(*) FROM PluginVersions WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID).Scan(&count)
	if err != nil {
		return "1.0.0", nil
	}
	return fmt.Sprintf("1.0.%d", count+1), nil
}
