// Package sync provides local-remote file synchronization
package sync

import (
	"context"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Config holds sync service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   *plugin.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	WSHub           *ws.Hub
}

// Service provides sync comparison and tracking
type Service struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   *plugin.Service
	wpClientFactory func(url, user, pass string) *wordpress.Client
	wsHub           *ws.Hub
}

// New creates a new sync service
func New(cfg Config) *Service {
	return &Service{
		db:              cfg.DB,
		log:             cfg.Logger,
		pluginService:   cfg.PluginService,
		wpClientFactory: cfg.WPClientFactory,
		wsHub:           cfg.WSHub,
	}
}

// CheckSync compares local and remote plugin files
func (s *Service) CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error) {
	// TODO: Get plugin and site details
	// TODO: Fetch remote file list via WP REST
	// TODO: Compare with local files
	// TODO: Return differences

	s.wsHub.Broadcast(ws.EventSyncStarted, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
	})

	result := &SyncResult{
		PluginID:     pluginID,
		SiteID:       siteID,
		TotalFiles:   0,
		ChangedFiles: 0,
		Changes:      []models.FileChange{},
	}

	s.wsHub.Broadcast(ws.EventSyncComplete, map[string]interface{}{
		"pluginId":   pluginID,
		"siteId":     siteID,
		"changedFiles": result.ChangedFiles,
	})

	return result, nil
}

// CheckAllSites checks sync status for all sites mapped to a plugin
func (s *Service) CheckAllSites(ctx context.Context, pluginID int64) ([]SyncResult, error) {
	// TODO: Get all mappings for plugin
	// TODO: Check each site
	return []SyncResult{}, nil
}

// GetFileChanges returns pending file changes for a plugin-site mapping
func (s *Service) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	// TODO: Query database for file changes
	return []models.FileChange{}, nil
}

// MarkSynced marks files as synced after successful publish
func (s *Service) MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error {
	// TODO: Update file change records
	return nil
}

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID      int64               `json:"pluginId"`
	SiteID        int64               `json:"siteId"`
	TotalFiles    int                 `json:"totalFiles"`
	ChangedFiles  int                 `json:"changedFiles"`
	AddedFiles    int                 `json:"addedFiles"`
	ModifiedFiles int                 `json:"modifiedFiles"`
	DeletedFiles  int                 `json:"deletedFiles"`
	Changes       []models.FileChange `json:"changes"`
	CheckedAt     string              `json:"checkedAt"`
}
