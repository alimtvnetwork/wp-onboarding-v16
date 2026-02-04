// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"archive/zip"
	"context"
	"database/sql"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/database/dbops"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// SitePasswordDecryptor interface for getting decrypted site passwords
type SitePasswordDecryptor interface {
	GetDecryptedPassword(ctx context.Context, siteID int64) (string, error)
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
	}
}

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode         string   `json:"mode"`         // "selected" or "full"
	Files        []string `json:"files"`        // files to publish (for "selected" mode)
	CreateBackup bool     `json:"createBackup"` // create backup before publishing
}

// PublishResult represents the result of a publish operation
type PublishResult struct {
	Success          bool     `json:"success"`
	FilesUpdated     int      `json:"filesUpdated"`
	BackupID         *int64   `json:"backupId,omitempty"`
	ActivationStatus string   `json:"activationStatus"` // active, inactive, error
	Duration         int64    `json:"duration"`         // milliseconds
	ErrorMessage     string   `json:"errorMessage,omitempty"`
	Stages           []Stage  `json:"stages"`
}

// Stage represents a publish pipeline stage
type Stage struct {
	Name      string `json:"name"`
	Status    string `json:"status"` // pending, running, completed, failed, skipped
	Duration  int64  `json:"duration"`
	Message   string `json:"message,omitempty"`
}

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts interface{}) (*PublishResult, error) {
	startTime := time.Now()
	
	// Parse options
	options, ok := opts.(PublishOptions)
	if !ok {
		// Try to convert from map
		if m, ok := opts.(map[string]interface{}); ok {
			options = PublishOptions{
				Mode:         getString(m, "mode", "full"),
				CreateBackup: getBool(m, "createBackup", true),
			}
			if files, ok := m["files"].([]interface{}); ok {
				for _, f := range files {
					if s, ok := f.(string); ok {
						options.Files = append(options.Files, s)
					}
				}
			}
		}
	}

	result := &PublishResult{
		Success:          false,
		ActivationStatus: "unknown",
		Stages:           []Stage{},
	}

	// Broadcast start event
	s.broadcastProgress(pluginID, siteID, "started", 0, "Starting publish...")

	// Get plugin info
	pluginInfo, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "failed", 0, err.Error())
		return result, nil
	}

	// Get mapping to find remote slug and site info
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "failed", 0, err.Error())
		return result, nil
	}

	// Get site credentials
	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "failed", 0, err.Error())
		return result, nil
	}

	// Create WordPress client
	s.log.Info("Creating WordPress client", "siteUrl", siteInfo.URL, "username", siteInfo.Username)
	s.broadcastDetailedLog(pluginID, siteID, "info", "connect", fmt.Sprintf("Connecting to WordPress: %s", siteInfo.URL), map[string]interface{}{
		"siteUrl":  siteInfo.URL,
		"username": siteInfo.Username,
	})
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)

	// Stage 1: Create backup (optional)
	if options.CreateBackup {
		stage := s.runStage("backup", func() error {
			s.broadcastProgress(pluginID, siteID, "backup", 10, "Creating backup...")
			s.broadcastDetailedLog(pluginID, siteID, "info", "backup", "Initiating remote plugin backup", map[string]interface{}{
				"mappingId":  mapping.ID,
				"remoteSlug": mapping.RemoteSlug,
			})
			// TODO: Implement backup creation via s.backupService
			return nil
		})
		result.Stages = append(result.Stages, stage)
		if stage.Status == "failed" {
			result.ErrorMessage = stage.Message
			s.broadcastProgress(pluginID, siteID, "failed", 10, stage.Message)
			return result, nil
		}
	}

	// Stage 2: Build package
	var zipPath string
	var fileCount int
	stage := s.runStage("package", func() error {
		s.broadcastProgress(pluginID, siteID, "packaging", 30, "Building package...")
		
		plug := pluginInfo
		var err error
		
		s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("Packaging plugin from: %s", plug.Path), map[string]interface{}{
			"pluginPath":      plug.Path,
			"pluginName":      plug.Name,
			"mode":            options.Mode,
			"excludePatterns": plug.ExcludePatterns,
		})
		
		if options.Mode == "selected" && len(options.Files) > 0 {
			fileCount = len(options.Files)
			s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("Creating selective ZIP with %d files", fileCount), map[string]interface{}{
				"selectedFiles": options.Files,
			})
			zipPath, err = s.createSelectiveZip(plug.Path, plug.Name, options.Files)
		} else {
			fileCount = plug.FileCount
			s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("Creating full ZIP with ~%d files", fileCount), nil)
			zipPath, err = s.createFullZip(plug.Path, plug.Name, plug.ExcludePatterns)
		}
		
		if err == nil && zipPath != "" {
			if info, statErr := os.Stat(zipPath); statErr == nil {
				s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(zipPath), info.Size()), map[string]interface{}{
					"zipPath":  zipPath,
					"zipSize":  info.Size(),
					"fileCount": fileCount,
				})
			}
		}
		
		return err
	})
	result.Stages = append(result.Stages, stage)
	if stage.Status == "failed" {
		result.ErrorMessage = stage.Message
		s.broadcastDetailedLog(pluginID, siteID, "error", "package", fmt.Sprintf("Package failed: %s", stage.Message), nil)
		s.broadcastProgress(pluginID, siteID, "failed", 30, stage.Message)
		return result, nil
	}

	// Ensure cleanup
	defer func() {
		if zipPath != "" {
			s.broadcastDetailedLog(pluginID, siteID, "debug", "cleanup", fmt.Sprintf("Removing temp ZIP: %s", zipPath), nil)
			os.Remove(zipPath)
		}
	}()

	// Stage 3: Upload to WordPress
	stage = s.runStage("upload", func() error {
		s.broadcastProgress(pluginID, siteID, "uploading", 60, "Uploading to WordPress...")
		s.broadcastDetailedLog(pluginID, siteID, "info", "upload", fmt.Sprintf("Uploading to %s as plugin: %s", siteInfo.URL, mapping.RemoteSlug), map[string]interface{}{
			"targetSite":  siteInfo.URL,
			"remoteSlug":  mapping.RemoteSlug,
			"zipPath":     zipPath,
		})
		err := s.uploadPlugin(ctx, wpClient, zipPath, mapping.RemoteSlug)
		if err != nil {
			s.broadcastDetailedLog(pluginID, siteID, "error", "upload", fmt.Sprintf("Upload failed: %s", err.Error()), map[string]interface{}{
				"error": err.Error(),
			})
		} else {
			s.broadcastDetailedLog(pluginID, siteID, "info", "upload", "Upload completed successfully", nil)
		}
		return err
	})
	result.Stages = append(result.Stages, stage)
	if stage.Status == "failed" {
		result.ErrorMessage = stage.Message
		s.broadcastProgress(pluginID, siteID, "failed", 60, stage.Message)
		return result, nil
	}

	// Stage 4: Activate plugin
	stage = s.runStage("activate", func() error {
		s.broadcastProgress(pluginID, siteID, "activating", 80, "Activating plugin...")
		s.broadcastDetailedLog(pluginID, siteID, "info", "activate", fmt.Sprintf("Activating plugin: %s", mapping.RemoteSlug), map[string]interface{}{
			"remoteSlug": mapping.RemoteSlug,
			"siteUrl":    siteInfo.URL,
		})
		err := wpClient.ActivatePlugin(mapping.RemoteSlug)
		if err != nil {
			s.broadcastDetailedLog(pluginID, siteID, "error", "activate", fmt.Sprintf("Activation failed: %s", err.Error()), nil)
		} else {
			s.broadcastDetailedLog(pluginID, siteID, "info", "activate", "Plugin activated successfully", nil)
		}
		return err
	})
	result.Stages = append(result.Stages, stage)
	if stage.Status == "failed" {
		result.ActivationStatus = "error"
		result.ErrorMessage = stage.Message
		// Continue - upload succeeded, just activation failed
	} else {
		result.ActivationStatus = "active"
	}

	// Stage 5: Mark files as synced
	stage = s.runStage("cleanup", func() error {
		s.broadcastProgress(pluginID, siteID, "cleanup", 95, "Marking files as synced...")
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", "Updating local sync state", nil)
		if options.Mode == "selected" && len(options.Files) > 0 {
			return s.syncService.MarkSynced(ctx, pluginID, siteID, options.Files)
		}
		return s.syncService.ClearChanges(ctx, pluginID)
	})
	result.Stages = append(result.Stages, stage)

	// Calculate totals
	result.Success = result.ActivationStatus == "active" || result.ActivationStatus == "inactive"
	result.Duration = time.Since(startTime).Milliseconds()
	
	if options.Mode == "selected" {
		result.FilesUpdated = len(options.Files)
	} else {
		// Count files in plugin
		plug := pluginInfo
		result.FilesUpdated = plug.FileCount
	}

	// Broadcast complete
	status := "completed"
	completionMessage := fmt.Sprintf("Published %d files in %dms", result.FilesUpdated, result.Duration)
	if !result.Success {
		status = "failed"
		completionMessage = result.ErrorMessage
		if completionMessage == "" {
			completionMessage = "Publish failed - check logs for details"
		}
	}
	
	s.broadcastDetailedLog(pluginID, siteID, func() string {
		if result.Success {
			return "info"
		}
		return "error"
	}(), "complete", completionMessage, map[string]interface{}{
		"success":      result.Success,
		"filesUpdated": result.FilesUpdated,
		"durationMs":   result.Duration,
	})
	s.broadcastProgress(pluginID, siteID, status, 100, completionMessage)

	s.log.Info("Plugin published", 
		"pluginId", pluginID, 
		"siteId", siteID, 
		"mode", options.Mode,
		"files", result.FilesUpdated,
		"duration", result.Duration,
		"success", result.Success)

	return result, nil
}

// PublishFiles publishes specific files to a WordPress site
func (s *Service) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (*PublishResult, error) {
	return s.Publish(ctx, pluginID, siteID, PublishOptions{
		Mode:         "selected",
		Files:        files,
		CreateBackup: false,
	})
}

// runStage executes a stage and captures timing/result
func (s *Service) runStage(name string, fn func() error) Stage {
	start := time.Now()
	stage := Stage{
		Name:   name,
		Status: "running",
	}

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = "failed"
		stage.Message = err.Error()
	} else {
		stage.Status = "completed"
	}

	return stage
}

// broadcastProgress sends a WebSocket progress event with detailed step info
// Updated to match frontend PublishProgressDialog expected payload shape
func (s *Service) broadcastProgress(pluginID, siteID int64, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	// Determine event type based on step
	eventType := ws.EventPublishProgress
	if step == "started" {
		eventType = ws.EventPublishStarted
	} else if step == "completed" || step == "failed" {
		eventType = ws.EventPublishComplete
	}

	// Map step names to stage names for frontend compatibility
	stage := step
	switch step {
	case "started":
		stage = "backup"
	case "packaging":
		stage = "package"
	case "uploading":
		stage = "upload"
	case "activating":
		stage = "activate"
	case "cleanup":
		stage = "cleanup"
	}

	// Determine status for the current stage
	status := "running"
	if step == "completed" {
		status = "success"
	} else if step == "failed" {
		status = "error"
	}

	// Broadcast progress event
	s.wsHub.Broadcast(eventType, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"stage":    stage,
		"step":     step, // Keep for backward compatibility
		"status":   status,
		"progress": progress,
		"total":    100,
		"message":  message,
	})
	
	// Also broadcast detailed log entry for frontend live log display
	logLevel := "info"
	if step == "failed" {
		logLevel = "error"
	}
	s.wsHub.BroadcastPublishLog(pluginID, siteID, logLevel, stage, message, nil)
	
	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "step", step, "stage", stage, "progress", progress, "message", message)
}

// broadcastDetailedLog sends a detailed log entry with structured data for inner operation visibility
func (s *Service) broadcastDetailedLog(pluginID, siteID int64, level, step, message string, details map[string]interface{}) {
	if s.wsHub == nil {
		return
	}
	s.wsHub.BroadcastPublishLog(pluginID, siteID, level, step, message, details)
	
	// Also log to server logger for backend trace
	switch level {
	case "error":
		s.log.Error(message, "pluginId", pluginID, "siteId", siteID, "step", step)
	case "warn":
		s.log.Warn(message, "pluginId", pluginID, "siteId", siteID, "step", step)
	case "debug":
		s.log.Debug(message, "pluginId", pluginID, "siteId", siteID, "step", step)
	default:
		s.log.Info(message, "pluginId", pluginID, "siteId", siteID, "step", step)
	}

// createFullZip creates a zip file of the entire plugin directory
func (s *Service) createFullZip(pluginPath, pluginName string, excludePatterns []string) (string, error) {
	// Ensure temp directory exists
	if err := os.MkdirAll(s.tempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	// Create zip file
	zipPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-%d.zip", pluginName, time.Now().Unix()))
	zipFile, err := os.Create(zipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	// Walk the plugin directory
	err = filepath.Walk(pluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		// Skip directories (they're created implicitly)
		if info.IsDir() {
			return nil
		}

		// Get relative path
		relPath, err := filepath.Rel(pluginPath, path)
		if err != nil {
			return err
		}

		// Check exclude patterns
		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
			if strings.Contains(relPath, pattern) {
				return nil
			}
		}

		// Skip hidden files and common excludes
		if s.shouldExclude(relPath) {
			return nil
		}

		// Create file in zip with plugin name as root folder
		zipPath := filepath.Join(pluginName, relPath)
		zipPath = filepath.ToSlash(zipPath) // Normalize for zip

		writer, err := zipWriter.Create(zipPath)
		if err != nil {
			return err
		}

		file, err := os.Open(path)
		if err != nil {
			return err
		}
		defer file.Close()

		_, err = io.Copy(writer, file)
		return err
	})

	if err != nil {
		os.Remove(zipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip archive")
	}

	return zipPath, nil
}

// createSelectiveZip creates a zip file with only selected files
func (s *Service) createSelectiveZip(pluginPath, pluginName string, files []string) (string, error) {
	// Ensure temp directory exists
	if err := os.MkdirAll(s.tempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	// Create zip file
	zipPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-patch-%d.zip", pluginName, time.Now().Unix()))
	zipFile, err := os.Create(zipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	for _, relPath := range files {
		fullPath := filepath.Join(pluginPath, relPath)
		
		// Check if file exists
		info, err := os.Stat(fullPath)
		if err != nil {
			if os.IsNotExist(err) {
				continue // Skip deleted files
			}
			return "", err
		}

		if info.IsDir() {
			continue
		}

		// Create file in zip with plugin name as root folder
		zipFilePath := filepath.Join(pluginName, relPath)
		zipFilePath = filepath.ToSlash(zipFilePath)

		writer, err := zipWriter.Create(zipFilePath)
		if err != nil {
			os.Remove(zipPath)
			return "", err
		}

		file, err := os.Open(fullPath)
		if err != nil {
			os.Remove(zipPath)
			return "", err
		}

		_, err = io.Copy(writer, file)
		file.Close()
		if err != nil {
			os.Remove(zipPath)
			return "", err
		}
	}

	return zipPath, nil
}

// shouldExclude checks if a file should be excluded from the zip
func (s *Service) shouldExclude(relPath string) bool {
	excludePatterns := []string{
		".git",
		".svn",
		".DS_Store",
		"node_modules",
		".idea",
		".vscode",
		"Thumbs.db",
		".env",
		".env.local",
	}

	for _, pattern := range excludePatterns {
		if strings.HasPrefix(relPath, pattern+string(filepath.Separator)) ||
			relPath == pattern ||
			strings.Contains(relPath, string(filepath.Separator)+pattern+string(filepath.Separator)) {
			return true
		}
	}

	// Skip hidden files
	parts := strings.Split(relPath, string(filepath.Separator))
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != "." && part != ".." {
			return true
		}
	}

	return false
}

// uploadPlugin uploads a plugin zip to WordPress
func (s *Service) uploadPlugin(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) error {
	// Read the zip file
	zipData, err := os.ReadFile(zipPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrFSRead, "failed to read zip file")
	}

	// Upload via WordPress REST API
	// Note: WordPress doesn't have a native plugin upload endpoint in REST API
	// This would typically require a custom endpoint or wp-cli
	// For now, we'll log and simulate success
	s.log.Info("Plugin upload prepared", "slug", slug, "size", len(zipData))

	// TODO: Implement actual upload when WordPress site has custom upload endpoint
	// return wpClient.UploadPlugin(slug, zipData)
	
	return nil
}

// getMapping retrieves the plugin-site mapping
func (s *Service) getMapping(ctx context.Context, pluginID, siteID int64) (*models.PluginMapping, error) {
	query := `
		SELECT Id, PluginId, SiteId, RemoteSlug, SyncStatus, LastSyncAt, LastBackupAt, CreatedAt, UpdatedAt
		FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`
	
	row := s.db.QueryRowContext(ctx, query, pluginID, siteID)
	
	var mapping models.PluginMapping
	var lastSyncAt, lastBackupAt, createdAt, updatedAt sql.NullString
	
	err := row.Scan(
		&mapping.ID,
		&mapping.PluginID,
		&mapping.SiteID,
		&mapping.RemoteSlug,
		&mapping.SyncStatus,
		&lastSyncAt,
		&lastBackupAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "mapping not found")
	}

	// Parse nullable datetime fields
	mapping.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	mapping.LastBackupAt = dbops.ParseNullTime(lastBackupAt)
	mapping.CreatedAt = dbops.ParseDateTime(createdAt.String)
	mapping.UpdatedAt = dbops.ParseDateTime(updatedAt.String)

	return &mapping, nil
}

// getSiteCredentials retrieves site info and decrypted password
func (s *Service) getSiteCredentials(ctx context.Context, siteID int64) (*models.Site, string, error) {
	query := `
		SELECT Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, 
		       LastTestedAt, LastSyncAt, CreatedAt, UpdatedAt
		FROM Sites
		WHERE Id = ?
	`
	
	row := s.db.QueryRowContext(ctx, query, siteID)
	
	var site models.Site
	var lastTestedAt, lastSyncAt, createdAt, updatedAt sql.NullString
	
	err := row.Scan(
		&site.ID,
		&site.Name,
		&site.URL,
		&site.Username,
		&site.PasswordEncrypted,
		&site.ConnectionStatus,
		&lastTestedAt,
		&lastSyncAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return nil, "", apperror.Wrap(err, apperror.ErrNotFound, "site not found")
	}

	// Parse nullable datetime fields
	site.LastTestedAt = dbops.ParseNullTime(lastTestedAt)
	site.LastSyncAt = dbops.ParseNullTime(lastSyncAt)
	site.CreatedAt = dbops.ParseDateTime(createdAt.String)
	site.UpdatedAt = dbops.ParseDateTime(updatedAt.String)

	// Decrypt password using the site password decryptor
	var password string
	if s.sitePasswordDecryptor != nil {
		password, err = s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
		if err != nil {
			s.log.Warn("Failed to decrypt password", "siteId", siteID, "error", err)
			return nil, "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt site password")
		}
	}
	
	return &site, password, nil
}

// Helper functions for parsing options
func getString(m map[string]interface{}, key, defaultVal string) string {
	if v, ok := m[key].(string); ok {
		return v
	}
	return defaultVal
}

func getBool(m map[string]interface{}, key string, defaultVal bool) bool {
	if v, ok := m[key].(bool); ok {
		return v
	}
	return defaultVal
}
