// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"encoding/json"

	"wp-plugin-publish/internal/database"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
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
	GetDecryptedPassword(ctx context.Context, siteID int64) (string, error)
}

// SessionLogger interface for session-based logging
type SessionLogger interface {
	StartSession(sessionType session.SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)
	Log(sessionID, level, step, message string, details json.RawMessage)
	LogStageStart(sessionID, stageName string)
	LogStageEnd(sessionID, stageName, status string, durationMs int64)
	EndSession(sessionID, status, errorMsg string)
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
}

// New creates a new publish service
func New(cfg Config) *Service {
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
	}
}

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode              string   `json:"mode"`              // "selected" or "full"
	Files             []string `json:"files"`             // files to publish (for "selected" mode)
	CreateBackup      bool     `json:"createBackup"`      // create backup before publishing
	KeepZipFiles      bool     `json:"keepZipFiles"`      // keep ZIP files after publish (for debugging)
	RollbackOnFailure bool     `json:"rollbackOnFailure"` // auto-rollback if activation fails (default: true)
}

// PublishResult represents the result of a publish operation
type PublishResult struct {
	Success          bool     `json:"success"`
	SessionID        string   `json:"sessionId,omitempty"`
	FilesUpdated     int      `json:"filesUpdated"`
	BackupID         *int64   `json:"backupId,omitempty"`
	ActivationStatus string   `json:"activationStatus"`          // active, inactive, error
	RollbackStatus   string   `json:"rollbackStatus,omitempty"`  // "", "success", "failed", "skipped"
	RollbackMessage  string   `json:"rollbackMessage,omitempty"` // details about rollback
	Duration         int64    `json:"duration"`                  // milliseconds
	ErrorMessage     string   `json:"errorMessage,omitempty"`
	Stages           []Stage  `json:"stages"`
}

// Stage represents a publish pipeline stage
type Stage struct {
	Name      string                `json:"name"`
	Status    stagestatus.Variant   `json:"status"`
	Duration  int64                 `json:"duration"`
	Message   string                `json:"message,omitempty"`
}

// FilePreview represents a file that will change during publish
type FilePreview struct {
	Path       string `json:"path"`
	ChangeType string `json:"changeType"` // added, modified, deleted
	Size       int64  `json:"size"`
	LocalHash  string `json:"localHash,omitempty"`
}

// PublishPreviewResult shows what files will be published
type PublishPreviewResult struct {
	PluginID      int64         `json:"pluginId"`
	PluginName    string        `json:"pluginName"`
	LocalVersion  string        `json:"localVersion"`
	RemoteVersion string        `json:"remoteVersion"`
	SiteID        int64         `json:"siteId"`
	SiteName      string        `json:"siteName"`
	SiteURL       string        `json:"siteUrl"`
	RemoteSlug    string        `json:"remoteSlug"`
	TotalFiles    int           `json:"totalFiles"`
	TotalSize     int64         `json:"totalSize"`
	Added         int           `json:"added"`
	Modified      int           `json:"modified"`
	Deleted       int           `json:"deleted"`
	Files         []FilePreview `json:"files"`
}

// FileDiffResult contains local and remote content for a single file
type FileDiffResult struct {
	Path          string `json:"path"`
	LocalContent  string `json:"localContent"`
	RemoteContent string `json:"remoteContent"`
}

// StageContext provides structured what/why/where/result context for logging.
// InnerData uses map[string]any internally for dynamic construction, but is always
// marshaled to json.RawMessage before crossing the WSHub boundary.
type StageContext struct {
	What      string         `json:"what"`                // What is being done
	Why       string         `json:"why,omitempty"`       // Why it's being done
	Where     string         `json:"where,omitempty"`     // Target URL/path
	Result    string         `json:"result,omitempty"`    // Outcome description
	InnerData map[string]any `json:"innerData,omitempty"` // HTTP status, response snippets, etc.
}
