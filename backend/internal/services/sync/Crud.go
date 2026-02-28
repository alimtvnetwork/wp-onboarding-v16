package sync

import (
	"context"
	"database/sql"
	"strings"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// siteInfo holds minimal site connection details for creating a WP client.
type siteInfo struct {
	Url      string
	Username string
}

// SQL query constants (centralized per coding standard).
const fileChangesSelectQuery = `
	SELECT Id, PluginId, FilePath, ChangeType, LocalHash, RemoteHash,
	       LocalModifiedAt, DetectedAt, SyncedAt
	FROM FileChanges
	WHERE PluginId = ? AND SyncedAt IS NULL
	ORDER BY DetectedAt DESC`

const fileChangesInsertQuery = `
	INSERT OR REPLACE INTO FileChanges
	(PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
	VALUES (?, ?, ?, ?, datetime('now'))`

const fileChangesClearQuery = `
	UPDATE FileChanges
	SET SyncedAt = datetime('now')
	WHERE PluginId = ? AND SyncedAt IS NULL`

const mappingSelectQuery = `
	SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
	       s.Name as SiteName, s.Url as SiteUrl
	FROM PluginMappings pm
	JOIN Sites s ON s.Id = pm.SiteId
	WHERE pm.PluginId = ? AND pm.SiteId = ?`

const mappingsListQuery = `
	SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
	       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
	       s.Name as SiteName, s.Url as SiteUrl
	FROM PluginMappings pm
	JOIN Sites s ON s.Id = pm.SiteId
	WHERE pm.PluginId = ?`

const siteInfoSelectQuery = `SELECT Url, Username FROM Sites WHERE Id = ?`

const mappingSyncStatusUpdateQuery = `
	UPDATE PluginMappings
	SET SyncStatus = ?, LastSyncAt = datetime('now'), UpdatedAt = datetime('now')
	WHERE PluginId = ? AND SiteId = ?`

// scanFileChangeRows is a dbutil.RowsScanner[models.FileChange] for QueryMany.
func scanFileChangeRows(rows *sql.Rows) (models.FileChange, error) {
	var change models.FileChange
	var localModifiedAt, detectedAt, syncedAt string

	err := rows.Scan(
		&change.Id,
		&change.PluginId,
		&change.FilePath,
		&change.ChangeType,
		&change.LocalHash,
		&change.RemoteHash,
		&localModifiedAt,
		&detectedAt,
		&syncedAt,
	)
	return change, err
}

// scanMappingRow is a dbutil.RowScanner[models.PluginMapping] for QueryOne (getMapping).
func scanMappingRow(row *sql.Row) (models.PluginMapping, error) {
	var m models.PluginMapping
	var syncStatus string
	err := row.Scan(
		&m.Id,
		&m.PluginId,
		&m.SiteId,
		&m.RemoteSlug,
		&syncStatus,
		&m.SiteName,
		&m.SiteUrl,
	)
	m.SyncStatus = syncStatus
	return m, err
}

// scanMappingRows is a dbutil.RowsScanner[models.PluginMapping] for QueryMany (getMappings).
func scanMappingRows(rows *sql.Rows) (models.PluginMapping, error) {
	var m models.PluginMapping
	var lastSyncAt, lastBackupAt, createdAt, updatedAt string

	err := rows.Scan(
		&m.Id,
		&m.PluginId,
		&m.SiteId,
		&m.RemoteSlug,
		&m.SyncStatus,
		&lastSyncAt,
		&lastBackupAt,
		&createdAt,
		&updatedAt,
		&m.SiteName,
		&m.SiteUrl,
	)
	return m, err
}

// scanSiteInfoRow is a dbutil.RowScanner[siteInfo] for QueryOne.
func scanSiteInfoRow(row *sql.Row) (siteInfo, error) {
	var info siteInfo
	err := row.Scan(&info.Url, &info.Username)
	return info, err
}

// GetFileChanges returns pending file changes for a plugin.
func (s *serviceImpl) GetFileChanges(ctx context.Context, pluginId, siteId int64) apperror.ResultSlice[models.FileChange] {
	set := dbutil.QueryMany[models.FileChange](
		ctx,
		s.dbu,
		fileChangesSelectQuery,
		scanFileChangeRows,
		pluginId,
	)
	if set.HasError() {
		return set.ToAppResultSlice()
	}

	changes := set.Items()
	if changes == nil {
		changes = []models.FileChange{}
	}
	return apperror.OkSlice(changes)
}

// RecordFileChange records a file change in the database.
func (s *serviceImpl) RecordFileChange(ctx context.Context, change *models.FileChange) *apperror.AppError {
	res := dbutil.Exec(
		ctx,
		s.dbu,
		fileChangesInsertQuery,
		change.PluginId,
		change.FilePath,
		change.ChangeType,
		change.LocalHash,
	)
	if res.HasError() {
		return res.AppError()
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastFileChange(change.PluginId, change.FilePath, change.ChangeType)
	}

	return nil
}

// MarkSynced marks specific files as synced.
func (s *serviceImpl) MarkSynced(ctx context.Context, pluginId, siteId int64, files []string) *apperror.AppError {
	if len(files) == 0 {

		return nil
	}

	placeholders := make([]string, len(files))
	args := make([]any, len(files)+1)
	args[0] = pluginId
	for i, f := range files {
		placeholders[i] = "?"
		args[i+1] = f
	}

	query := `UPDATE FileChanges SET SyncedAt = datetime('now') WHERE PluginId = ? AND FilePath IN (` +
		strings.Join(placeholders, ",") + `)`

	res := dbutil.Exec(ctx, s.dbu, query, args...)
	if res.HasError() {
		return res.AppError()
	}

	return nil
}

// ClearChanges removes all pending changes for a plugin.
func (s *serviceImpl) ClearChanges(ctx context.Context, pluginId int64) *apperror.AppError {
	res := dbutil.Exec(
		ctx,
		s.dbu,
		fileChangesClearQuery,
		pluginId,
	)
	if res.HasError() {
		return res.AppError()
	}

	return nil
}

// getMappings retrieves all mappings for a plugin.
func (s *serviceImpl) getMappings(ctx context.Context, pluginId int64) ([]models.PluginMapping, *apperror.AppError) {
	set := dbutil.QueryMany[models.PluginMapping](
		ctx,
		s.dbu,
		mappingsListQuery,
		scanMappingRows,
		pluginId,
	)
	if set.HasError() {
		return nil, set.AppError()
	}

	return set.Items(), nil
}

// getMapping retrieves a specific plugin-site mapping.
func (s *serviceImpl) getMapping(ctx context.Context, pluginId, siteId int64) (*models.PluginMapping, *apperror.AppError) {
	result := dbutil.QueryOne[models.PluginMapping](
		ctx,
		s.dbu,
		mappingSelectQuery,
		scanMappingRow,
		pluginId,
		siteId,
	)
	if result.HasError() {
		return nil, result.AppError()
	}

	if result.IsEmpty() {
		return nil, apperror.New(apperror.ErrNotFound, "plugin-site mapping not found").
			WithPluginId(pluginId).WithSiteId(siteId)
	}

	m := result.Value()

	return &m, nil
}

// getSiteInfo retrieves minimal site info for creating a WP client.
func (s *serviceImpl) getSiteInfo(ctx context.Context, siteId int64) (*siteInfo, *apperror.AppError) {
	result := dbutil.QueryOne[siteInfo](
		ctx,
		s.dbu,
		siteInfoSelectQuery,
		scanSiteInfoRow,
		siteId,
	)
	if result.HasError() {
		return nil, result.AppError()
	}

	if result.IsEmpty() {
		return nil, apperror.New(apperror.ErrNotFound, "site not found").WithSiteId(siteId)
	}

	info := result.Value()

	return &info, nil
}

// updateMappingSyncStatus updates the sync status of a mapping.
func (s *serviceImpl) updateMappingSyncStatus(ctx context.Context, pluginId, siteId int64, isInSync bool) {
	status := "out_of_sync"
	if isInSync {
		status = "synced"
	}
	dbutil.Exec(
		ctx,
		s.dbu,
		mappingSyncStatusUpdateQuery,
		status,
		pluginId,
		siteId,
	)
}
