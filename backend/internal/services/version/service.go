// Package version provides plugin version history and rollback functionality
package version

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/publish_type"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// PluginVersionRow re-exports the database type for service consumers.
type PluginVersionRow = database.PluginVersionRow

// Config holds version service configuration
type Config struct {
	DB     *database.DB
	Logger *logger.Logger
	WSHub  *ws.Hub
}

// Service provides version history and rollback operations
type Service struct {
	db    *database.DB
	log   *logger.Logger
	wsHub *ws.Hub
}

// New creates a new version service
func New(cfg Config) *Service {
	return &Service{
		db:    cfg.DB,
		log:   cfg.Logger,
		wsHub: cfg.WSHub,
	}
}

// GetVersions returns version history for a plugin
func (s *Service) GetVersions(ctx context.Context, pluginId int64, siteId *int64, limit int) ([]PluginVersionRow, *apperror.AppError) {
	if limit <= 0 {
		limit = 50
	}
	rows, err := s.db.GetPluginVersions(pluginId, siteId, limit)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin versions").
			WithPluginId(pluginId)
	}
	return rows, nil
}

// GetVersion returns a specific version entry
func (s *Service) GetVersion(ctx context.Context, versionId int64) (*PluginVersionRow, *apperror.AppError) {
	row, err := s.db.GetPluginVersionByID(versionId)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrVersionNotFound, "version not found").
			WithVersionId(versionId)
	}
	return row, nil
}

// RecordVersionInput bundles parameters for RecordVersion.
type RecordVersionInput struct {
	PluginId      int64
	SiteId        int64
	FilesUpdated  int
	GitCommitHash string
	PublishType   publishtype.Variant
	Notes         string
	BackupPath    string
}

// RecordVersion saves a new version entry after a publish operation
func (s *Service) RecordVersion(ctx context.Context, input RecordVersionInput) (int64, *apperror.AppError) {
	version, err := s.db.GetNextVersionNumber(input.PluginId, input.SiteId)
	if err != nil {
		version = fmt.Sprintf("1.0.%d", time.Now().Unix())
	}

	versionId, err := s.db.CreatePluginVersion(database.PluginVersionInput{
		PluginId:      input.PluginId,
		SiteId:        input.SiteId,
		Version:       version,
		BackupPath:    input.BackupPath,
		FilesUpdated:  input.FilesUpdated,
		GitCommitHash: input.GitCommitHash,
		PublishType:   input.PublishType.Value(),
		Notes:         input.Notes,
	})
	if err != nil {
		appErr := apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to record version").
			WithPluginId(input.PluginId).
			WithSiteId(input.SiteId)
		s.log.Error("Failed to record version", "pluginId", input.PluginId, "siteId", input.SiteId, "error", err)
		return 0, appErr
	}

	s.log.Info("Version recorded", "versionId", versionId, "version", version, "pluginId", input.PluginId, "siteId", input.SiteId)

	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventVersionCreated, ws.VersionCreatedData{
			VersionId:    versionId,
			Version:      version,
			PluginId:     input.PluginId,
			SiteId:       input.SiteId,
			FilesUpdated: input.FilesUpdated,
			PublishType:  input.PublishType,
		})
	}

	return versionId, nil
}

// Rollback restores a plugin to a previous version
func (s *Service) Rollback(ctx context.Context, versionId int64) (*ws.RollbackCompleteData, *apperror.AppError) {
	ver, err := s.db.GetPluginVersionByID(versionId)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrVersionNotFound, "version not found").
			WithVersionId(versionId)
	}

	if ver.BackupPath == "" {
		return nil, apperror.New(apperror.ErrVersionNoBackup, "no backup available for this version").
			WithVersionId(versionId)
	}

	s.log.Info("Starting rollback", "versionId", versionId, "version", ver.Version, "pluginId", ver.PluginID, "siteId", ver.SiteID)

	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventRollbackStarted, ws.RollbackStartedData{
			VersionId: versionId,
			Version:   ver.Version,
			PluginId:  ver.PluginID,
			SiteId:    ver.SiteID,
		})
	}

	// TODO: Implement actual rollback:
	// 1. Read backup zip from backupPath
	// 2. Upload to WordPress site
	// 3. Activate plugin

	result := &ws.RollbackCompleteData{
		IsSuccess:      true,
		VersionId:      versionId,
		Version:        ver.Version,
		RolledBackAt:   time.Now().Format(time.RFC3339),
		Implementation: "pending",
		Message:        "Rollback initiated - backup restoration requires WordPress API integration",
	}

	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventRollbackComplete, *result)
	}

	return result, nil
}

// DeleteVersion removes a version entry
func (s *Service) DeleteVersion(ctx context.Context, versionId int64) *apperror.AppError {
	if err := s.db.DeletePluginVersion(versionId); err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete version").
			WithVersionId(versionId)
	}
	return nil
}
