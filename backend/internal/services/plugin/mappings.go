package plugin

import (
	"context"
	"database/sql"
	"strings"

	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// GetMappings returns all site mappings for a plugin
func (s *Service) GetMappings(ctx context.Context, pluginID int64) apperror.ResultSlice[models.PluginMapping] {
	rows, err := s.db.QueryContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
		       s.Name, s.Url
		FROM PluginMappings pm
		JOIN Sites s ON pm.SiteId = s.Id
		WHERE pm.PluginId = ?
		ORDER BY s.Name ASC
	`, pluginID)
	if err != nil {
		return apperror.FailSliceWrap[models.PluginMapping](err, apperror.ErrDatabaseQuery, "failed to get mappings")
	}
	defer rows.Close()

	mappings := make([]models.PluginMapping, 0)
	for rows.Next() {
		m, err := scanMappingWithSite(rows)
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
		&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAtStr, &updatedAtStr,
		&m.SiteName, &m.SiteURL,
	)
	if err != nil {
		return m, err
	}

	m.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	m.LastBackupAt = dbops.ParseNullTime(lastBackupAt)
	m.CreatedAt = dbops.ParseDateTime(createdAtStr.String)
	m.UpdatedAt = dbops.ParseDateTime(updatedAtStr.String)

	return m, nil
}

// GetMappingsBySite returns all mappings for a site
func (s *Service) GetMappingsBySite(ctx context.Context, siteID int64) apperror.ResultSlice[models.PluginMapping] {
	rows, err := s.db.QueryContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
		       p.Name as PluginName
		FROM PluginMappings pm
		JOIN Plugins p ON pm.PluginId = p.Id
		WHERE pm.SiteId = ?
		ORDER BY p.Name ASC
	`, siteID)
	if err != nil {
		return apperror.FailSliceWrap[models.PluginMapping](err, apperror.ErrDatabaseQuery, "failed to get mappings by site")
	}
	defer rows.Close()

	mappings := make([]models.PluginMapping, 0)
	for rows.Next() {
		m, err := scanMappingBySite(rows)
		if err != nil {
			continue
		}
		mappings = append(mappings, m)
	}

	return apperror.OkSlice(mappings)
}

// scanMappingBySite scans a mapping row that includes plugin name.
func scanMappingBySite(rows *sql.Rows) (models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt sql.NullString
	var pluginName string
	var createdAtStr, updatedAtStr sql.NullString

	err := rows.Scan(
		&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
		&lastSyncAt, &lastBackupAt, &createdAtStr, &updatedAtStr,
		&pluginName,
	)
	if err != nil {
		return m, err
	}

	m.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	m.LastBackupAt = dbops.ParseNullTime(lastBackupAt)
	m.CreatedAt = dbops.ParseDateTime(createdAtStr.String)
	m.UpdatedAt = dbops.ParseDateTime(updatedAtStr.String)

	return m, nil
}

// CreateMapping creates a new plugin-site mapping
func (s *Service) CreateMapping(ctx context.Context, input CreateMappingInput) apperror.Result[models.PluginMapping] {
	s.log.Info("Creating plugin mapping", "pluginId", input.PluginID, "siteId", input.SiteID)

	// Check for duplicate mapping
	var exists int
	err := s.db.QueryRowContext(ctx,
		"SELECT 1 FROM PluginMappings WHERE PluginId = ? AND SiteId = ?",
		input.PluginID, input.SiteID,
	).Scan(&exists)
	if err != sql.ErrNoRows {
		return apperror.FailNew[models.PluginMapping](apperror.ErrDuplicate, "mapping already exists")
	}

	result, err := s.db.ExecContext(ctx, `
		INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
	`, input.PluginID, input.SiteID, input.RemoteSlug)

	if err != nil {
		return apperror.FailWrap[models.PluginMapping](err, apperror.ErrDatabaseExec, "failed to create mapping")
	}

	id, _ := result.LastInsertId()

	var m models.PluginMapping
	var createdAtStr, updatedAtStr sql.NullString
	s.db.QueryRowContext(ctx, `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       pm.CreatedAt, pm.UpdatedAt, s.Name, s.Url
		FROM PluginMappings pm
		JOIN Sites s ON pm.SiteId = s.Id
		WHERE pm.Id = ?
	`, id).Scan(
		&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
		&createdAtStr, &updatedAtStr, &m.SiteName, &m.SiteURL,
	)
	m.CreatedAt = dbops.ParseDateTime(createdAtStr.String)
	m.UpdatedAt = dbops.ParseDateTime(updatedAtStr.String)

	s.log.Info("Plugin mapping created", "mappingId", id)
	return apperror.Ok(m)
}

// DeleteMapping removes a plugin-site mapping
func (s *Service) DeleteMapping(ctx context.Context, mappingID int64) error {
	s.log.Info("Deleting plugin mapping", "mappingId", mappingID)

	result, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE Id = ?", mappingID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete mapping")
	}

	rows, _ := result.RowsAffected()
	if rows == 0 {
		return apperror.New(apperror.ErrNotFound, "mapping not found")
	}

	s.log.Info("Plugin mapping deleted", "mappingId", mappingID)
	return nil
}

// UpdateMappingsForPlugin replaces all site mappings for a plugin
func (s *Service) UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error {
	s.log.Info("Updating plugin mappings", "pluginId", pluginID, "sites", len(siteIDs))

	// Delete existing mappings
	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE PluginId = ?", pluginID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear existing mappings")
	}

	// Create new mappings
	for _, siteID := range siteIDs {
		_, err := s.db.ExecContext(ctx, `
			INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
		`, pluginID, siteID, remoteSlug)
		if err != nil {
			s.log.Warn("Failed to create mapping", "pluginId", pluginID, "siteId", siteID, "error", err)
		}
	}

	return nil
}

// UpdateMappingsForSite replaces all plugin mappings for a site
// This is the robust endpoint for updating site→plugin relationships from the Edit Site dialog
func (s *Service) UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error {
	s.log.Info("Updating site mappings", "siteId", siteID, "plugins", len(pluginIDs))

	// Get existing mappings for this site to preserve remoteSlug values
	existingResult := s.GetMappingsBySite(ctx, siteID)
	var existingMappings []models.PluginMapping
	if existingResult.HasError() {
		s.log.Warn("Failed to fetch existing site mappings", "siteId", siteID, "error", existingResult.Error())
		existingMappings = []models.PluginMapping{}
	} else {
		existingMappings = existingResult.Items()
		if existingMappings == nil {
			existingMappings = []models.PluginMapping{}
		}
	}

	// Build a map of pluginId → remoteSlug for existing mappings
	slugByPluginID := make(map[int64]string)
	for _, m := range existingMappings {
		slugByPluginID[m.PluginID] = m.RemoteSlug
	}

	// Delete all existing mappings for this site
	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE SiteId = ?", siteID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear existing site mappings")
	}

	// Create new mappings for each selected plugin
	for _, pluginID := range pluginIDs {
		remoteSlug := s.resolveRemoteSlug(ctx, pluginID, slugByPluginID)

		_, err := s.db.ExecContext(ctx, `
			INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
		`, pluginID, siteID, remoteSlug)
		if err != nil {
			s.log.Warn("Failed to create site mapping", "siteId", siteID, "pluginId", pluginID, "error", err)
		}
	}

	s.log.Info("Site mappings updated", "siteId", siteID, "pluginsLinked", len(pluginIDs))
	return nil
}

// resolveRemoteSlug returns the existing slug or generates one from plugin name.
func (s *Service) resolveRemoteSlug(ctx context.Context, pluginID int64, slugByPluginID map[int64]string) string {
	if slug, ok := slugByPluginID[pluginID]; ok && slug != "" {
		return slug
	}

	var pluginName string
	err := s.db.QueryRowContext(ctx, "SELECT Name FROM Plugins WHERE Id = ?", pluginID).Scan(&pluginName)
	if err == nil {
		return strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))
	}

	return "plugin"
}
