// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Config holds publish service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   *plugin.Service
	BackupService   *backup.Service
	SyncService     *sync.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	TempDir         string
	WSHub           *ws.Hub
}

// Service provides plugin publishing operations
type Service struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   *plugin.Service
	backupService   *backup.Service
	syncService     *sync.Service
	wpClientFactory func(url, user, pass string) *wordpress.Client
	tempDir         string
	wsHub           *ws.Hub
}

// New creates a new publish service
func New(cfg Config) *Service {
	return &Service{
		db:              cfg.DB,
		log:             cfg.Logger,
		pluginService:   cfg.PluginService,
		backupService:   cfg.BackupService,
		syncService:     cfg.SyncService,
		wpClientFactory: cfg.WPClientFactory,
		tempDir:         cfg.TempDir,
		wsHub:           cfg.WSHub,
	}
}

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode         string   // "selected" or "full"
	Files        []string // files to publish (for "selected" mode)
	CreateBackup bool     // create backup before publishing
}

// PublishResult represents the result of a publish operation
type PublishResult struct {
	Success          bool   `json:"success"`
	FilesUpdated     int    `json:"filesUpdated"`
	BackupID         *int64 `json:"backupId,omitempty"`
	ActivationStatus string `json:"activationStatus"` // active, inactive, error
	Duration         int64  `json:"duration"`         // milliseconds
	ErrorMessage     string `json:"errorMessage,omitempty"`
}

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) (*PublishResult, error) {
	s.wsHub.Broadcast(ws.EventPublishStarted, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"mode":     opts.Mode,
	})

	// TODO: Implement publish pipeline:
	// 1. Create backup if requested
	// 2. Build zip file (for full mode) or prepare files (for selected mode)
	// 3. Upload to WordPress
	// 4. Activate plugin
	// 5. Mark files as synced
	// 6. Clean up temp files

	result := &PublishResult{
		Success:          true,
		FilesUpdated:     0,
		ActivationStatus: "active",
		Duration:         0,
	}

	s.wsHub.Broadcast(ws.EventPublishComplete, map[string]interface{}{
		"pluginId":     pluginID,
		"siteId":       siteID,
		"filesUpdated": result.FilesUpdated,
		"success":      result.Success,
	})

	return result, nil
}

// createZip creates a zip file of the plugin
func (s *Service) createZip(pluginPath, outputPath string, files []string) error {
	// TODO: Implement zip creation
	return nil
}

// upload uploads the plugin to WordPress
func (s *Service) upload(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) error {
	// TODO: Implement upload via WP REST API
	return nil
}

// activate activates the plugin on WordPress
func (s *Service) activate(ctx context.Context, wpClient *wordpress.Client, slug string) error {
	// TODO: Implement plugin activation via WP REST API
	return nil
}
