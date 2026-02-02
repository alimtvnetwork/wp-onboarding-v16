package sync

import (
	"context"
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, PluginId, FilePath, ChangeType, LocalHash, RemoteHash,
		       LocalModifiedAt, DetectedAt, SyncedAt
		FROM FileChanges
		WHERE PluginId = ? AND SyncedAt IS NULL
		ORDER BY DetectedAt DESC
	`, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get file changes")
	}
	defer rows.Close()

	var changes []models.FileChange
	for rows.Next() {
		var c models.FileChange
		var localModAt, syncedAt sql.NullString

		err := rows.Scan(
			&c.ID, &c.PluginID, &c.FilePath, &c.ChangeType,
			&c.LocalHash, &c.RemoteHash, &localModAt, &c.DetectedAt, &syncedAt,
		)
		if err != nil {
			continue
		}

		if localModAt.Valid {
			t, _ := time.Parse(time.RFC3339, localModAt.String)
			c.LocalModifiedAt = &t
		}
		if syncedAt.Valid {
			t, _ := time.Parse(time.RFC3339, syncedAt.String)
			c.SyncedAt = &t
		}

		changes = append(changes, c)
	}

	if changes == nil {
		changes = []models.FileChange{}
	}

	return changes, nil
}

func (s *serviceImpl) RecordFileChange(ctx context.Context, change *models.FileChange) error {
	// Check if change already exists for this file
	var existingID int64
	err := s.db.QueryRowContext(ctx, `
		SELECT Id FROM FileChanges
		WHERE PluginId = ? AND FilePath = ? AND SyncedAt IS NULL
	`, change.PluginID, change.FilePath).Scan(&existingID)

	if err == sql.ErrNoRows {
		// Insert new change
		_, err = s.db.ExecContext(ctx, `
			INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, LocalModifiedAt, DetectedAt)
			VALUES (?, ?, ?, ?, ?, datetime('now'))
		`, change.PluginID, change.FilePath, change.ChangeType, change.LocalHash, change.LocalModifiedAt)
	} else if err == nil {
		// Update existing change
		_, err = s.db.ExecContext(ctx, `
			UPDATE FileChanges
			SET ChangeType = ?, LocalHash = ?, LocalModifiedAt = ?, DetectedAt = datetime('now')
			WHERE Id = ?
		`, change.ChangeType, change.LocalHash, change.LocalModifiedAt, existingID)
	}

	return err
}

func (s *serviceImpl) MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error {
	for _, filePath := range files {
		_, err := s.db.ExecContext(ctx, `
			UPDATE FileChanges
			SET SyncedAt = datetime('now')
			WHERE PluginId = ? AND FilePath = ? AND SyncedAt IS NULL
		`, pluginID, filePath)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to mark file as synced")
		}
	}
	return nil
}

func (s *serviceImpl) ClearChanges(ctx context.Context, pluginID int64) error {
	_, err := s.db.ExecContext(ctx, `
		DELETE FROM FileChanges WHERE PluginId = ?
	`, pluginID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear file changes")
	}
	return nil
}
