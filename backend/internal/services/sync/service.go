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

// Service interface for sync operations
type Service interface {
	// Sync checking
	CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error)
	CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error)
	CheckAllPlugins(ctx context.Context) ([]SyncResult, error)

	// File change management
	GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error)
	RecordFileChange(ctx context.Context, change *models.FileChange) error
	MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error
	ClearChanges(ctx context.Context, pluginID int64) error
}

// Config holds sync service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   *plugin.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	WSHub           *ws.Hub
}

type serviceImpl struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   *plugin.Service
	wpClientFactory func(url, user, pass string) *wordpress.Client
	wsHub           *ws.Hub
}

// New creates a new sync service
func New(cfg Config) Service {
	return &serviceImpl{
		db:              cfg.DB,
		log:             cfg.Logger,
		pluginService:   cfg.PluginService,
		wpClientFactory: cfg.WPClientFactory,
		wsHub:           cfg.WSHub,
	}
}
