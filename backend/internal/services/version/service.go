// Package version provides plugin version history and rollback functionality
package version

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/database"
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
func (s *Service) GetVersions(ctx context.Context, pluginID int64, siteID *int64, limit int) ([]PluginVersionRow, error) {
	if limit <= 0 {
		limit = 50
	}
	return s.db.GetPluginVersions(pluginID, siteID, limit)
}

// GetVersion returns a specific version entry
func (s *Service) GetVersion(ctx context.Context, versionID int64) (*PluginVersionRow, error) {
	return s.db.GetPluginVersionByID(versionID)
}

// RecordVersion saves a new version entry after a publish operation
func (s *Service) RecordVersion(ctx context.Context, pluginID, siteID int64, filesUpdated int, gitCommitHash, publishType, notes string, backupPath string) (int64, error) {
	// Generate version number
	version, err := s.db.GetNextVersionNumber(pluginID, siteID)
	if err != nil {
		version = fmt.Sprintf("1.0.%d", time.Now().Unix())
	}

	versionID, err := s.db.CreatePluginVersion(pluginID, siteID, version, backupPath, filesUpdated, gitCommitHash, publishType, notes)
	if err != nil {
		s.log.Error("Failed to record version",
			"pluginId", pluginID,
			"siteId", siteID,
			"error", err,
		)
		return 0, err
	}

	s.log.Info("Version recorded",
		"versionId", versionID,
		"version", version,
		"pluginId", pluginID,
		"siteId", siteID,
	)

	// Broadcast version created event
	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventVersionCreated, ws.VersionCreatedData{
			VersionID:    versionID,
			Version:      version,
			PluginID:     pluginID,
			SiteID:       siteID,
			FilesUpdated: filesUpdated,
			PublishType:  publishType,
		})
	}

	return versionID, nil
}

// Rollback restores a plugin to a previous version
func (s *Service) Rollback(ctx context.Context, versionID int64) (*ws.RollbackCompleteData, error) {
	// Get version info
	ver, err := s.db.GetPluginVersionByID(versionID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrVersionNotFound, "version not found").
			WithVersionID(versionID)
	}

	if ver.BackupPath == "" {
		return nil, apperror.New(apperror.ErrVersionNoBackup, "no backup available for this version").
			WithVersionID(versionID)
	}

	pluginID := ver.PluginID
	siteID := ver.SiteID
	versionStr := ver.Version

	s.log.Info("Starting rollback",
		"versionId", versionID,
		"version", versionStr,
		"pluginId", pluginID,
		"siteId", siteID,
	)

	// Broadcast rollback started
	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventRollbackStarted, ws.RollbackStartedData{
			VersionID: versionID,
			Version:   versionStr,
			PluginID:  pluginID,
			SiteID:    siteID,
		})
	}

	// TODO: Implement actual rollback:
	// 1. Read backup zip from backupPath
	// 2. Upload to WordPress site
	// 3. Activate plugin
	// For now, return success with TODO note

	result := &ws.RollbackCompleteData{
		IsSuccess:      true,
		VersionID:      versionID,
		Version:        versionStr,
		RolledBackAt:   time.Now().Format(time.RFC3339),
		Implementation: "pending",
		Message:        "Rollback initiated - backup restoration requires WordPress API integration",
	}

	// Broadcast rollback complete
	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventRollbackComplete, *result)
	}

	return result, nil
}

// DeleteVersion removes a version entry
func (s *Service) DeleteVersion(ctx context.Context, versionID int64) error {
	// TODO: Also delete backup file if exists
	return s.db.DeletePluginVersion(versionID)
}
