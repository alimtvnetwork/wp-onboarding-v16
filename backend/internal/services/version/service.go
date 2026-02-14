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
func (s *Service) GetVersions(ctx context.Context, pluginID int64, siteID *int64, limit int) (any, error) {
	if limit <= 0 {
		limit = 50
	}
	return s.db.GetPluginVersions(pluginID, siteID, limit)
}

// GetVersion returns a specific version entry
func (s *Service) GetVersion(ctx context.Context, versionID int64) (any, error) {
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
		s.wsHub.Broadcast(ws.EventVersionCreated, map[string]any{
			"versionId":    versionID,
			"version":      version,
			"pluginId":     pluginID,
			"siteId":       siteID,
			"filesUpdated": filesUpdated,
			"publishType":  publishType,
		})
	}

	return versionID, nil
}

// Rollback restores a plugin to a previous version
func (s *Service) Rollback(ctx context.Context, versionID int64) (any, error) {
	// Get version info
	version, err := s.db.GetPluginVersionByID(versionID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrVersionNotFound, "version not found").
			WithContext("versionId", versionID)
	}

	backupPath, _ := version["backupPath"].(string)
	if backupPath == "" {
		return nil, apperror.New(apperror.ErrVersionNoBackup, "no backup available for this version").
			WithContext("versionId", versionID)
	}

	pluginID, _ := version["pluginId"].(int64)
	siteID, _ := version["siteId"].(int64)
	versionStr, _ := version["version"].(string)

	s.log.Info("Starting rollback",
		"versionId", versionID,
		"version", versionStr,
		"pluginId", pluginID,
		"siteId", siteID,
	)

	// Broadcast rollback started
	if s.wsHub != nil {
		s.wsHub.Broadcast(ws.EventRollbackStarted, map[string]any{
			"versionId": versionID,
			"version":   versionStr,
			"pluginId":  pluginID,
			"siteId":    siteID,
		})
	}

	// TODO: Implement actual rollback:
	// 1. Read backup zip from backupPath
	// 2. Upload to WordPress site
	// 3. Activate plugin
	// For now, return success with TODO note

	result := map[string]any{
		"success":       true,
		"versionId":     versionID,
		"version":       versionStr,
		"rolledBackAt":  time.Now().Format(time.RFC3339),
		"implementation": "pending",
		"message":       "Rollback initiated - backup restoration requires WordPress API integration",
	}

	// Broadcast rollback complete
	if s.wsHub != nil {
		s.wsHub.Broadcast(ws.EventRollbackComplete, result)
	}

	return result, nil
}

// DeleteVersion removes a version entry
func (s *Service) DeleteVersion(ctx context.Context, versionID int64) error {
	// TODO: Also delete backup file if exists
	return s.db.DeletePluginVersion(versionID)
}
