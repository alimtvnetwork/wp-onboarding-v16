// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"encoding/json"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/publishtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// SitePasswordDecryptor interface for getting decrypted site passwords
type SitePasswordDecryptor interface {
	GetDecryptedPassword(ctx context.Context, siteId int64) apperror.Result[string]
}

// SessionLogger interface for session-based logging
type SessionLogger interface {
	StartSession(input session.StartSessionInput) apperror.Result[string]
	Log(input session.LogInput)
	LogStageStart(sessionId, stageName string)
	LogStageEnd(input session.StageEndInput)
	EndSession(sessionId, status, errorMsg string)
}

// PublishHistoryRecorder records publish history entries
type PublishHistoryRecorder interface {
	Record(entry models.PublishHistory) (*models.PublishHistory, error)
}

// Config holds publish service configuration
type Config struct {
	DB                    *database.DB
	Logger                *logger.Logger
	PluginService         *plugin.Service
	BackupService         *backup.Service
	SyncService           sync.Service
	SitePasswordDecryptor SitePasswordDecryptor
	WPClientFactory       func(url, user, pass string) *wordpress.Client
	TempDir               string
	WSHub                 *ws.Hub
	SessionService        SessionLogger
	HistoryService        PublishHistoryRecorder
	ManifestCacheTTL      time.Duration // TTL for remote manifest cache; default 5 minutes
}

// Service provides plugin publishing operations
type Service struct {
	db                    *database.DB
	log                   *logger.Logger
	pluginService         *plugin.Service
	backupService         *backup.Service
	syncService           sync.Service
	sitePasswordDecryptor SitePasswordDecryptor
	wpClientFactory       func(url, user, pass string) *wordpress.Client
	tempDir               string
	wsHub                 *ws.Hub
	sessionService        SessionLogger
	historyService        PublishHistoryRecorder
	manifestCache         *ManifestCache
}

// New creates a new publish service
func New(cfg Config) *Service {
	cacheTTL := cfg.ManifestCacheTTL
	if cacheTTL == 0 {
		cacheTTL = 5 * time.Minute
	}

	return &Service{
		db:                    cfg.DB,
		log:                   cfg.Logger,
		pluginService:         cfg.PluginService,
		backupService:         cfg.BackupService,
		syncService:           cfg.SyncService,
		sitePasswordDecryptor: cfg.SitePasswordDecryptor,
		wpClientFactory:       cfg.WPClientFactory,
		tempDir:               cfg.TempDir,
		wsHub:                 cfg.WSHub,
		sessionService:        cfg.SessionService,
		historyService:        cfg.HistoryService,
		manifestCache:         NewManifestCache(cacheTTL),
	}
}

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode                publishtype.Variant // Full or Selected
	Files               []string            // files to publish (for Selected mode)
	IsCreateBackup      bool                // create backup before publishing
	IsKeepZipFiles      bool                // keep ZIP files after publish (for debugging)
	IsRollbackOnFailure bool                // auto-rollback if activation fails (default: true)
}

// PublishResult represents the result of a publish operation
type PublishResult struct {
	IsSuccess        bool
	SessionId        string  `json:",omitempty"`
	FilesUpdated     int
	BackupId         *int64  `json:",omitempty"`
	ActivationStatus string  // active, inactive, error
	RollbackStatus   string  `json:",omitempty"` // "", "success", "failed", "skipped"
	RollbackMessage  string  `json:",omitempty"` // details about rollback
	Duration         int64   // milliseconds
	ErrorMessage     string  `json:",omitempty"`
	Stages           []Stage
}

// Stage represents a publish pipeline stage
type Stage struct {
	Name     string
	Status   stagestatus.Variant
	Duration int64
	Message  string `json:",omitempty"`
}

// FilePreview represents a file that will change during publish
type FilePreview struct {
	Path       string
	ChangeType string // added, modified, deleted, unchanged
	Size       int64
	LocalHash  string `json:",omitempty"`
}

// PublishPreviewResult shows what files will be published
type PublishPreviewResult struct {
	PluginId      int64
	PluginName    string
	LocalVersion  string
	RemoteVersion string
	SiteId        int64
	SiteName      string
	SiteUrl       string
	RemoteSlug    string
	TotalFiles    int
	TotalSize     int64
	Added         int
	Modified      int
	Deleted       int
	Unchanged     int
	Files         []FilePreview
}

// FileDiffResult contains local and remote content for a single file
type FileDiffResult struct {
	Path          string
	LocalContent  string
	RemoteContent string
}

// StageContext provides structured what/why/where/result context for logging.
// Details carries typed structured data serialized to json.RawMessage.
type StageContext struct {
	What    string          // What is being done
	Why     string          `json:",omitempty"` // Why it's being done
	Where   string          `json:",omitempty"` // Target URL/path
	Result  string          `json:",omitempty"` // Outcome description
	Details json.RawMessage `json:",omitempty"` // Typed structured data (use toDetails)
}
