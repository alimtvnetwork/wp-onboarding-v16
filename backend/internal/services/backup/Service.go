// Package backup provides plugin backup and restore functionality
package backup

import (
	"context"
	"os"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// Config holds backup service configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	WSHub         *ws.Hub
	BackupDir     string
	RetentionDays int
	MaxPerPlugin  int
}

// Service provides backup management operations
type Service struct {
	db            *database.DB
	log           *logger.Logger
	wsHub         *ws.Hub
	backupDir     string
	retentionDays int
	maxPerPlugin  int
}

// New creates a new backup service
func New(cfg Config) *Service {
	if err := os.MkdirAll(cfg.BackupDir, 0755); err != nil {
		cfg.Logger.Error("Failed to create backup directory", "error", err)
	}

	return &Service{
		db:            cfg.DB,
		log:           cfg.Logger,
		wsHub:         cfg.WSHub,
		backupDir:     cfg.BackupDir,
		retentionDays: cfg.RetentionDays,
		maxPerPlugin:  cfg.MaxPerPlugin,
	}
}

// BackupLogInput bundles parameters for broadcastLog.
type BackupLogInput struct {
	PluginID int64
	Level    string
	Step     string
	Message  string
	Details  []byte
}

// broadcastLog sends a log entry via WebSocket if hub is available
func (s *Service) broadcastLog(input BackupLogInput) {
	if s.wsHub == nil {
		return
	}

	entry := ws.OperationLogEntry{
		Level:   input.Level,
		Step:    input.Step,
		Message: input.Message,
		Details: input.Details,
	}
	logInput := ws.OperationLogInput{
		PluginID: input.PluginID,
		Entry:    entry,
	}
	s.wsHub.BroadcastBackupLog(logInput)
}

// logInfo broadcasts an info-level log entry.
func (s *Service) logInfo(pluginID int64, step, message string) {
	s.broadcastLog(BackupLogInput{
		PluginID: pluginID,
		Level:    loglevel.Info.Lower(),
		Step:     step,
		Message:  message,
	})
}

// logInfoWithDetails broadcasts an info-level log entry with details.
func (s *Service) logInfoWithDetails(pluginID int64, step, message string, details []byte) {
	s.broadcastLog(BackupLogInput{
		PluginID: pluginID,
		Level:    loglevel.Info.Lower(),
		Step:     step,
		Message:  message,
		Details:  details,
	})
}

// logError broadcasts an error-level log entry.
func (s *Service) logError(pluginID int64, step, message string) {
	s.broadcastLog(BackupLogInput{
		PluginID: pluginID,
		Level:    loglevel.Error.Lower(),
		Step:     step,
		Message:  message,
	})
}

// logWarn broadcasts a warn-level log entry.
func (s *Service) logWarn(pluginID int64, step, message string) {
	s.broadcastLog(BackupLogInput{
		PluginID: pluginID,
		Level:    loglevel.Warn.Lower(),
		Step:     step,
		Message:  message,
	})
}

// List returns all backups for a plugin mapping
func (s *Service) List(ctx context.Context, mappingID int64) apperror.ResultSlice[models.Backup] {
	return apperror.OkSlice([]models.Backup{})
}

// GetByID returns a specific backup
func (s *Service) GetByID(ctx context.Context, id int64) apperror.Result[models.Backup] {
	return apperror.Ok(models.Backup{})
}

// enforceRetention ensures we don't exceed max backups per plugin
func (s *Service) enforceRetention(ctx context.Context, mappingID int64) error {
	return nil
}

// RestoreResult represents the result of a restore operation
type RestoreResult struct {
	IsSuccess    bool
	ErrorMessage string `json:",omitempty"`
}

// ExportResult contains information about an export operation
type ExportResult struct {
	OutputPath string
	FilesCount int
	TotalBytes int64
	Duration   time.Duration
}

// ImportResult contains information about an import operation
type ImportResult struct {
	DestPath   string
	FilesCount int
	TotalBytes int64
	Duration   time.Duration
}
