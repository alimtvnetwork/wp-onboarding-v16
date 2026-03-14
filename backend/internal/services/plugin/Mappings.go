package plugin

import (
	"context"
	"database/sql"

	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// GetMappings returns all site mappings for a plugin.
func (s *Service) GetMappings(ctx context.Context, pluginId int64) apperror.ResultSlice[models.PluginMapping] {
	rows, err := s.db.QueryContext(ctx, mappingsByPluginQuery, pluginId)
	if err != nil {
		return apperror.FailSliceWrap[models.PluginMapping](err, apperror.ErrDatabaseQuery, "failed to get mappings")
	}
	defer rows.Close()

	return scanMappingRows(rows, scanMappingWithSite)
}

const mappingsByPluginQuery = `
	SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
	       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
	       s.Name, s.Url
	FROM PluginMappings pm
	JOIN Sites s ON pm.SiteId = s.Id
	WHERE pm.PluginId = ?
	ORDER BY s.Name ASC`

const mappingsBySiteQuery = `
	SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
	       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
	       p.Name as PluginName
	FROM PluginMappings pm
	JOIN Plugins p ON pm.PluginId = p.Id
	WHERE pm.SiteId = ?
	ORDER BY p.Name ASC`

const mappingByIdQuery = `
	SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
	       pm.CreatedAt, pm.UpdatedAt, s.Name, s.Url
	FROM PluginMappings pm
	JOIN Sites s ON pm.SiteId = s.Id
	WHERE pm.Id = ?`

// scanMappingRows collects mappings using the provided scanner function.
func scanMappingRows(rows *sql.Rows, scanner func(*sql.Rows) (models.PluginMapping, error)) apperror.ResultSlice[models.PluginMapping] {
	mappings := make([]models.PluginMapping, 0)
	for rows.Next() {
		m, err := scanner(rows)
		if err != nil {
			continue
		}
		mappings = append(mappings, m)
	}
	return apperror.OkSlice(mappings)
}

// scanMappingWithSite scans a mapping row that includes site name and URL.
func scanMappingWithSite(rows *sql.Rows) (models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt sql.NullString
	var createdAtStr, updatedAtStr sql.NullString

	err := rows.Scan(
		&m.Id, &m.PluginId, &m.SiteId, &m.RemoteSlug, &m.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAtStr, &updatedAtStr,
		&m.SiteName, &m.SiteUrl,
	)
	if err != nil {
		return m, err
	}

	applyMappingTimestamps(&m, mappingTimestamps{LastSync: lastSyncAt, LastBackup: lastBackupAt, Created: createdAtStr, Updated: updatedAtStr})
	return m, nil
}

// scanMappingBySite scans a mapping row that includes plugin name.
func scanMappingBySite(rows *sql.Rows) (models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt sql.NullString
	var pluginName string
	var createdAtStr, updatedAtStr sql.NullString

	err := rows.Scan(
		&m.Id, &m.PluginId, &m.SiteId, &m.RemoteSlug, &m.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAtStr, &updatedAtStr,
		&pluginName,
	)
	if err != nil {
		return m, err
	}

	applyMappingTimestamps(&m, mappingTimestamps{LastSync: lastSyncAt, LastBackup: lastBackupAt, Created: createdAtStr, Updated: updatedAtStr})
	return m, nil
}

// mappingTimestamps bundles timestamp fields for applyMappingTimestamps.
type mappingTimestamps struct {
	LastSync   sql.NullString
	LastBackup sql.NullString
	Created    sql.NullString
	Updated    sql.NullString
}

// applyMappingTimestamps parses and sets timestamp fields on a mapping.
func applyMappingTimestamps(m *models.PluginMapping, ts mappingTimestamps) {
	m.LastSyncAt = dbops.ParseNullTime(ts.LastSync)
	m.LastBackupAt = dbops.ParseNullTime(ts.LastBackup)
	m.CreatedAt = dbops.ParseDateTime(ts.Created.String)
	m.UpdatedAt = dbops.ParseDateTime(ts.Updated.String)
}

// GetMappingsBySite returns all mappings for a site.
func (s *Service) GetMappingsBySite(ctx context.Context, siteId int64) apperror.ResultSlice[models.PluginMapping] {
	rows, err := s.db.QueryContext(ctx, mappingsBySiteQuery, siteId)
	if err != nil {
		return apperror.FailSliceWrap[models.PluginMapping](err, apperror.ErrDatabaseQuery, "failed to get mappings by site")
	}
	defer rows.Close()

	return scanMappingRows(rows, scanMappingBySite)
}

// CreateMapping creates a new plugin-site mapping.
func (s *Service) CreateMapping(ctx context.Context, input CreateMappingInput) apperror.Result[models.PluginMapping] {
	s.log.Info("Creating plugin mapping", "pluginId", input.PluginId, "siteId", input.SiteId)

	err := s.checkDuplicateMapping(ctx, input)

	if err != nil {
		return apperror.Fail[models.PluginMapping](err)
	}

	id, insertErr := s.insertMapping(ctx, input)
	if insertErr != nil {
		return apperror.Fail[models.PluginMapping](insertErr)
	}

	m := s.loadMappingById(ctx, id)
	s.log.Info("Plugin mapping created", "mappingId", id)
	return apperror.Ok(m)
}

// checkDuplicateMapping returns an error if the mapping already exists.
func (s *Service) checkDuplicateMapping(ctx context.Context, input CreateMappingInput) *apperror.AppError {
	var exists int
	err := s.db.QueryRowContext(ctx,
		"SELECT 1 FROM PluginMappings WHERE PluginId = ? AND SiteId = ?",
		input.PluginId, input.SiteId,
	).Scan(&exists)
	isDuplicate := err != sql.ErrNoRows

	if isDuplicate {

		return apperror.New(apperror.ErrDuplicate, "mapping already exists")
	}
	return nil
}

// insertMapping inserts a mapping row and returns the new ID.
func (s *Service) insertMapping(ctx context.Context, input CreateMappingInput) (int64, *apperror.AppError) {
	result, err := s.db.ExecContext(ctx, `
		INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
	`, input.PluginId, input.SiteId, input.RemoteSlug)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create mapping")
	}
	id, _ := result.LastInsertId()
	return id, nil
}

// loadMappingById fetches a mapping by its ID.
func (s *Service) loadMappingById(ctx context.Context, id int64) models.PluginMapping {
	var m models.PluginMapping
	var createdAtStr, updatedAtStr sql.NullString

	s.db.QueryRowContext(ctx, mappingByIdQuery, id).Scan(
		&m.Id, &m.PluginId, &m.SiteId, &m.RemoteSlug, &m.SyncStatus,
		&createdAtStr, &updatedAtStr, &m.SiteName, &m.SiteUrl,
	)
	m.CreatedAt = dbops.ParseDateTime(createdAtStr.String)
	m.UpdatedAt = dbops.ParseDateTime(updatedAtStr.String)
	return m
}

// DeleteMapping removes a plugin-site mapping.
func (s *Service) DeleteMapping(ctx context.Context, mappingId int64) *apperror.AppError {
	s.log.Info("Deleting plugin mapping", "mappingId", mappingId)

	result, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE Id = ?", mappingId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete mapping")
	}

	rows, _ := result.RowsAffected()
	isMissing := rows == 0

	if isMissing {
		return apperror.New(apperror.ErrNotFound, "mapping not found")
	}

	s.log.Info("Plugin mapping deleted", "mappingId", mappingId)
	return nil
}
