// Package sync provides local-remote file synchronization
package sync

import (
	"context"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// PushSyncResult represents the result of a sync push operation
type PushSyncResult struct {
	PluginId     int64
	SiteId       int64
	FilesUpdated int
	FilesDeleted int
	FilesIgnored int
	TotalChanges int
	IsSuccess    bool
	ErrorMessage string `json:",omitempty"`
}

// Service interface for sync operations
type Service interface {
	CheckSync(ctx context.Context, pluginId, siteId int64) apperror.Result[SyncResult]
	CheckAllSites(ctx context.Context, pluginId int64) apperror.Result[BatchSyncResult]
	CheckAllPlugins(ctx context.Context) apperror.ResultSlice[SyncResult]
	PushSync(ctx context.Context, pluginId, siteId int64) apperror.Result[PushSyncResult]
	GetFileChanges(ctx context.Context, pluginId, siteId int64) apperror.ResultSlice[models.FileChange]
	RecordFileChange(ctx context.Context, change *models.FileChange) *apperror.AppError
	MarkSynced(ctx context.Context, pluginId, siteId int64, files []string) *apperror.AppError
	ClearChanges(ctx context.Context, pluginId int64) *apperror.AppError
}

// FileEntry represents a file with its hash, modification time, and size
type FileEntry struct {
	Path       string
	Hash       string
	ModifiedAt time.Time
	Size       int64
}

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginId     int64
	SiteId       int64
	SiteName     string              `json:",omitempty"`
	IsInSync     bool
	LocalFiles   int
	RemoteFiles  int
	Added        int
	Modified     int
	Deleted      int
	Changes      []models.FileChange `json:",omitempty"`
	CheckedAt    time.Time
	ErrorMessage string              `json:",omitempty"`
}

// BatchSyncResult represents sync results for multiple sites
type BatchSyncResult struct {
	PluginId   int64
	PluginName string
	Results    []SyncResult
	TotalSites int
	IsInSync   int
	OutOfSync  int
	Errors     int
}

// SitePasswordDecryptor interface for getting decrypted site passwords
type SitePasswordDecryptor interface {
	GetDecryptedPassword(ctx context.Context, siteId int64) apperror.Result[string]
}

// Config holds sync service configuration
type Config struct {
	DB                    *database.DB
	Logger                *logger.Logger
	PluginService         *plugin.Service
	SitePasswordDecryptor SitePasswordDecryptor
	WPClientFactory       func(url, user, pass string) *wordpress.Client
	WSHub                 *ws.Hub
}

type serviceImpl struct {
	db                    *database.DB
	dbu                   *dbutil.DB
	log                   *logger.Logger
	pluginService         *plugin.Service
	sitePasswordDecryptor SitePasswordDecryptor
	wpClientFactory       func(url, user, pass string) *wordpress.Client
	wsHub                 *ws.Hub
}

// New creates a new sync service
func New(cfg Config) Service {
	return &serviceImpl{
		db:                    cfg.DB,
		dbu:                   dbutil.New(cfg.DB.DB),
		log:                   cfg.Logger,
		pluginService:         cfg.PluginService,
		sitePasswordDecryptor: cfg.SitePasswordDecryptor,
		wpClientFactory:       cfg.WPClientFactory,
		wsHub:                 cfg.WSHub,
	}
}
