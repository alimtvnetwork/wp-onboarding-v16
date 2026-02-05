// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"archive/zip"
	"context"
	"crypto/md5"
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
	"wp-plugin-publish/pkg/pathutil"
)

// SitePasswordDecryptor interface for getting decrypted site passwords
type SitePasswordDecryptor interface {
	GetDecryptedPassword(ctx context.Context, siteID int64) (string, error)
}

// SessionLogger interface for session-based logging
type SessionLogger interface {
	StartSession(sessionType interface{}, pluginID, siteID int64, pluginName, siteName string) (string, error)
	Log(sessionID, level, step, message string, details map[string]interface{})
	LogStageStart(sessionID, stageName string)
	LogStageEnd(sessionID, stageName, status string, durationMs int64)
	EndSession(sessionID, status, errorMsg string)
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
	}
}

// PublishOptions configures the publish operation
type PublishOptions struct {
	Mode         string   `json:"mode"`         // "selected" or "full"
	Files        []string `json:"files"`        // files to publish (for "selected" mode)
	CreateBackup bool     `json:"createBackup"` // create backup before publishing
	KeepZipFiles bool     `json:"keepZipFiles"` // keep ZIP files after publish (for debugging)
}

// PublishResult represents the result of a publish operation
type PublishResult struct {
	Success          bool     `json:"success"`
	SessionID        string   `json:"sessionId,omitempty"`
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
				KeepZipFiles: getBool(m, "keepZipFiles", false),
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

	// Get plugin info early to have name for session
	pluginInfo, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "failed", 0, err.Error())
		return result, nil
	}

	// Get site info early to have name for session
	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "failed", 0, err.Error())
		return result, nil
	}

	// Start session for this publish operation
	var sessionID string
	if s.sessionService != nil {
		sessionID, err = s.sessionService.StartSession("publish", pluginID, siteID, pluginInfo.Name, siteInfo.Name)
		if err != nil {
			s.log.Warn("Failed to start session", "error", err)
		} else {
			result.SessionID = sessionID
		}
	}

	// Broadcast start event with session ID
	s.broadcastProgressWithSession(pluginID, siteID, sessionID, "started", 0, "Starting publish...")

	// Log session start
	s.sessionLog(sessionID, "info", "init", fmt.Sprintf("Starting publish for %s to %s", pluginInfo.Name, siteInfo.Name), nil)

	// Get mapping to find remote slug and site info
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.sessionLog(sessionID, "error", "init", fmt.Sprintf("Failed to get mapping: %s", err.Error()), nil)
		s.endSession(sessionID, "error", err.Error())
		s.broadcastProgressWithSession(pluginID, siteID, sessionID, "failed", 0, err.Error())
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
				// Log ZIP internal structure for debugging
				zipEntries := s.getZipStructure(zipPath)
				s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("ZIP created: %s (%d bytes)", filepath.Base(zipPath), info.Size()), map[string]interface{}{
					"zipPath":  zipPath,
					"zipSize":  info.Size(),
					"fileCount": fileCount,
					"zipStructure": zipEntries,
				})
				// Log first 20 entries for quick visibility
				maxShow := 20
				if len(zipEntries) < maxShow {
					maxShow = len(zipEntries)
				}
				for i := 0; i < maxShow; i++ {
					s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  └─ %s", zipEntries[i]), nil)
				}
				if len(zipEntries) > 20 {
					s.broadcastDetailedLog(pluginID, siteID, "debug", "package", fmt.Sprintf("  ... and %d more files", len(zipEntries)-20), nil)
				}
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

	// Track whether publish succeeded or failed for cleanup decisions
	publishFailed := false

	// Ensure cleanup - but ALWAYS keep ZIP on failure for debugging
	defer func() {
		if zipPath == "" {
			return
		}

		// ALWAYS keep ZIP on failure - essential for debugging upload issues
		if publishFailed {
			s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP for debugging (publish failed): %s", zipPath), map[string]interface{}{
				"zipPath": zipPath,
				"reason":  "publish_failed",
			})
			return
		}

		// On success: check user preference
		if options.KeepZipFiles {
			s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", fmt.Sprintf("Keeping temp ZIP (user setting): %s", zipPath), map[string]interface{}{
				"zipPath":      zipPath,
				"keepZipFiles": true,
			})
			return
		}

		// Success + user doesn't want to keep: remove
		s.broadcastDetailedLog(pluginID, siteID, "debug", "cleanup", fmt.Sprintf("Removing temp ZIP: %s", zipPath), map[string]interface{}{
			"keepZipFiles": options.KeepZipFiles,
		})
		os.Remove(zipPath)
	}()

	// Stage 3: Upload to WordPress
	var alreadyActivated bool
	var uploadStartTime = time.Now()
	stage = s.runStageWithSession(sessionID, "upload", func() error {
		// Get ZIP file info for context
		var zipSize int64
		if info, err := os.Stat(zipPath); err == nil {
			zipSize = info.Size()
		}

		// Log stage start with structured context
		s.broadcastStageLog(pluginID, siteID, sessionID, "info", "upload", StageContext{
			What:  fmt.Sprintf("Uploading ZIP (%s) to WordPress", formatBytes(zipSize)),
			Why:   fmt.Sprintf("Deploy %s plugin update to production", pluginInfo.Name),
			Where: fmt.Sprintf("%s/wp-json/riseup-asia-uploader/v1/upload", siteInfo.URL),
			InnerData: map[string]interface{}{
				"zipPath":    zipPath,
				"zipSize":    zipSize,
				"remoteSlug": mapping.RemoteSlug,
				"targetSite": siteInfo.URL,
				"method":     "POST",
				"contentType": "multipart/form-data",
			},
		})

		s.broadcastProgress(pluginID, siteID, "uploading", 60, "Uploading to WordPress...")
		performed, uploadResult, activated, err := s.uploadPlugin(ctx, wpClient, zipPath, mapping.RemoteSlug)
		alreadyActivated = activated

		if err != nil {
			// Build detailed error diagnostics with what/why/where/result
			errorCtx := StageContext{
				What:   fmt.Sprintf("Upload ZIP to %s", siteInfo.URL),
				Why:    "Deploy plugin update",
				Where:  siteInfo.URL,
				Result: fmt.Sprintf("FAILED: %s", err.Error()),
				InnerData: map[string]interface{}{
					"zipPath":    zipPath,
					"remoteSlug": mapping.RemoteSlug,
				},
			}
			
			// Extract APIError details if available
			if apiErr, ok := err.(*wordpress.APIError); ok {
				errorCtx.InnerData["request"] = map[string]interface{}{
					"method":   apiErr.Method,
					"endpoint": apiErr.Endpoint,
					"url":      apiErr.URL,
				}
				errorCtx.InnerData["response"] = map[string]interface{}{
					"status": apiErr.StatusCode,
					"body":   truncateString(apiErr.ResponseBody, 2000),
				}
				if apiErr.StackTrace != "" {
					errorCtx.InnerData["stackTrace"] = apiErr.StackTrace
				}
			} else if appErr, ok := err.(*apperror.AppError); ok {
				errorCtx.InnerData["code"] = appErr.Code
				if appErr.StackTrace != "" {
					errorCtx.InnerData["stackTrace"] = appErr.StackTrace
				}
				if cause := appErr.Unwrap(); cause != nil {
					if apiErr, ok := cause.(*wordpress.APIError); ok {
						errorCtx.InnerData["request"] = map[string]interface{}{
							"method":   apiErr.Method,
							"endpoint": apiErr.Endpoint,
							"url":      apiErr.URL,
						}
						errorCtx.InnerData["response"] = map[string]interface{}{
							"status": apiErr.StatusCode,
							"body":   truncateString(apiErr.ResponseBody, 2000),
						}
					}
				}
			}
			s.broadcastStageLog(pluginID, siteID, sessionID, "error", "upload", errorCtx)
			return err
		}

		// Success logging with detailed result
		if performed {
			resultMsg := "Plugin uploaded successfully"
			if alreadyActivated {
				resultMsg = "Plugin uploaded and activated"
			}
			
			successCtx := StageContext{
				What:   fmt.Sprintf("Upload ZIP (%s)", formatBytes(zipSize)),
				Why:    "Deploy plugin update",
				Where:  siteInfo.URL,
				Result: resultMsg,
				InnerData: map[string]interface{}{
					"remoteSlug":   mapping.RemoteSlug,
					"activated":    alreadyActivated,
					"durationMs":   time.Since(uploadStartTime).Milliseconds(),
				},
			}
			if uploadResult != nil {
				successCtx.InnerData["uploadResponse"] = map[string]interface{}{
					"success":     uploadResult.Success,
					"message":     uploadResult.Message,
					"pluginName":  uploadResult.PluginName,
					"version":     uploadResult.Version,
					"overwritten": uploadResult.Overwritten,
				}
			}
			s.broadcastStageLog(pluginID, siteID, sessionID, "info", "upload", successCtx)
			return nil
		}

		// Companion plugin not installed - simulated upload
		s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "upload", StageContext{
			What:   "Upload ZIP to WordPress",
			Why:    "Deploy plugin update",
			Where:  siteInfo.URL,
			Result: "SIMULATED - no companion plugin available",
			InnerData: map[string]interface{}{
				"zipPath":    zipPath,
				"remoteSlug": mapping.RemoteSlug,
				"hint":       "Install the Riseup Asia Uploader plugin on the target WordPress site to enable real uploads",
			},
		})
		return nil
	})
	result.Stages = append(result.Stages, stage)
	
	// Broadcast stage complete event for frontend tracking
	s.broadcastStageComplete(pluginID, siteID, sessionID, "upload", stage.Status, stage.Duration, map[string]interface{}{
		"remoteSlug": mapping.RemoteSlug,
		"activated":  alreadyActivated,
	})
	
	if stage.Status == "failed" {
		result.ErrorMessage = stage.Message
		s.broadcastProgress(pluginID, siteID, "failed", 60, stage.Message)
		publishFailed = true
		return result, nil
	}

	// Stage 4: Activate plugin
	var activateStartTime = time.Now()
	stage = s.runStageWithSession(sessionID, "activate", func() error {
		s.broadcastProgress(pluginID, siteID, "activating", 80, "Activating plugin...")

		// If plugin was already activated during upload, skip activation
		if alreadyActivated {
			s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
				What:   "Activate plugin on WordPress",
				Why:    "Enable plugin functionality after upload",
				Where:  siteInfo.URL,
				Result: "SKIPPED - plugin activated during upload",
				InnerData: map[string]interface{}{
					"remoteSlug": mapping.RemoteSlug,
					"reason":     "already_activated_during_upload",
				},
			})
			return nil
		}

		// Try Plugin Uploader Helper first (simpler endpoint)
		if available, _ := wpClient.CheckOnboardPluginAvailable(); available {
			endpointURL := fmt.Sprintf("%s/wp-json/onboard-plugin/v1/plugins/%s/enable", siteInfo.URL, mapping.RemoteSlug)
			
			s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
				What:  "Activate plugin via Onboard Plugin API",
				Why:   "Enable plugin after successful upload",
				Where: endpointURL,
				InnerData: map[string]interface{}{
					"method":     "POST",
					"remoteSlug": mapping.RemoteSlug,
				},
			})

			err := wpClient.EnablePlugin(mapping.RemoteSlug)
			if err != nil {
				errorCtx := StageContext{
					What:   "Activate plugin via Onboard Plugin",
					Why:    "Enable plugin after upload",
					Where:  endpointURL,
					Result: fmt.Sprintf("FAILED: %s", err.Error()),
					InnerData: map[string]interface{}{
						"remoteSlug": mapping.RemoteSlug,
						"durationMs": time.Since(activateStartTime).Milliseconds(),
					},
				}
				if apiErr, ok := err.(*wordpress.APIError); ok {
					errorCtx.InnerData["request"] = map[string]interface{}{
						"method":   apiErr.Method,
						"endpoint": apiErr.Endpoint,
						"url":      apiErr.URL,
					}
					errorCtx.InnerData["response"] = map[string]interface{}{
						"status": apiErr.StatusCode,
						"body":   truncateString(apiErr.ResponseBody, 2000),
					}
				}
				s.broadcastStageLog(pluginID, siteID, sessionID, "error", "activate", errorCtx)
				return err
			}

			s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
				What:   "Activate plugin via Onboard Plugin",
				Why:    "Enable plugin after upload",
				Where:  endpointURL,
				Result: "SUCCESS - plugin is now active",
				InnerData: map[string]interface{}{
					"remoteSlug": mapping.RemoteSlug,
					"durationMs": time.Since(activateStartTime).Milliseconds(),
				},
			})
			return nil
		}

		// Fallback to WordPress Core REST API
		resolvedIdentifier := mapping.RemoteSlug
		if id, resolveErr := wpClient.ResolvePluginIdentifier(mapping.RemoteSlug); resolveErr == nil {
			resolvedIdentifier = id
			if resolvedIdentifier != mapping.RemoteSlug {
				s.broadcastStageLog(pluginID, siteID, sessionID, "debug", "activate", StageContext{
					What:   "Resolve plugin identifier",
					Why:    "WordPress API requires full plugin path",
					Where:  siteInfo.URL,
					Result: fmt.Sprintf("Resolved %s → %s", mapping.RemoteSlug, resolvedIdentifier),
				})
			}
		}

		endpointURL := fmt.Sprintf("%s/wp-json/wp/v2/plugins/%s", siteInfo.URL, resolvedIdentifier)
		
		s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
			What:  "Activate plugin via WordPress Core API",
			Why:   "Enable plugin after successful upload (fallback method)",
			Where: endpointURL,
			InnerData: map[string]interface{}{
				"method":             "PUT",
				"remoteSlug":         mapping.RemoteSlug,
				"resolvedIdentifier": resolvedIdentifier,
				"payload":            map[string]string{"status": "active"},
			},
		})

		err := wpClient.ActivatePlugin(resolvedIdentifier)
		if err != nil {
			errorCtx := StageContext{
				What:   "Activate plugin via WordPress Core API",
				Why:    "Enable plugin after upload",
				Where:  endpointURL,
				Result: fmt.Sprintf("FAILED: %s", err.Error()),
				InnerData: map[string]interface{}{
					"remoteSlug":         mapping.RemoteSlug,
					"resolvedIdentifier": resolvedIdentifier,
					"durationMs":         time.Since(activateStartTime).Milliseconds(),
				},
			}
			if apiErr, ok := err.(*wordpress.APIError); ok {
				errorCtx.InnerData["request"] = map[string]interface{}{
					"method":   apiErr.Method,
					"endpoint": apiErr.Endpoint,
					"url":      apiErr.URL,
				}
				errorCtx.InnerData["response"] = map[string]interface{}{
					"status": apiErr.StatusCode,
					"body":   truncateString(apiErr.ResponseBody, 2000),
				}
			}
			s.broadcastStageLog(pluginID, siteID, sessionID, "error", "activate", errorCtx)
			return err
		}

		s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
			What:   "Activate plugin via WordPress Core API",
			Why:    "Enable plugin after upload",
			Where:  endpointURL,
			Result: "SUCCESS - plugin is now active",
			InnerData: map[string]interface{}{
				"remoteSlug":         mapping.RemoteSlug,
				"resolvedIdentifier": resolvedIdentifier,
				"durationMs":         time.Since(activateStartTime).Milliseconds(),
			},
		})
		return nil
	})
	result.Stages = append(result.Stages, stage)
	
	// Broadcast stage complete event
	s.broadcastStageComplete(pluginID, siteID, sessionID, "activate", stage.Status, stage.Duration, map[string]interface{}{
		"remoteSlug": mapping.RemoteSlug,
		"skipped":    alreadyActivated,
	})
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
	publishFailed = !result.Success // Ensure cleanup knows the final status
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

// broadcastStageStatus explicitly marks a publish stage as success/error.
// This is used for late-stage failures (e.g. activation) where a subsequent stage (cleanup)
// would otherwise cause the UI to incorrectly treat the prior stage as successful.
func (s *Service) broadcastStageStatus(pluginID, siteID int64, stage string, status string, progress int, message string, details map[string]interface{}) {
	if s.wsHub == nil {
		return
	}

	payload := map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"stage":    stage,
		"step":     stage,
		"status":   status,
		"progress": progress,
		"total":    100,
		"message":  message,
	}
	if details != nil {
		payload["details"] = details
	}

	s.wsHub.Broadcast(ws.EventPublishProgress, payload)

	level := "info"
	if status == "error" {
		level = "error"
	}
	s.wsHub.BroadcastPublishLog(pluginID, siteID, level, stage, message, details)
}

// StageContext provides structured what/why/where/result context for logging
type StageContext struct {
	What       string                 // What is being done
	Why        string                 // Why it's being done
	Where      string                 // Target URL/path
	Result     string                 // Outcome description
	InnerData  map[string]interface{} // HTTP status, response snippets, etc.
}

// broadcastStageLog sends a detailed log entry with structured what/why/where/result context
func (s *Service) broadcastStageLog(pluginID, siteID int64, sessionID, level, stage string, ctx StageContext) {
	details := map[string]interface{}{
		"what": ctx.What,
	}
	if ctx.Why != "" {
		details["why"] = ctx.Why
	}
	if ctx.Where != "" {
		details["where"] = ctx.Where
	}
	if ctx.Result != "" {
		details["result"] = ctx.Result
	}
	if ctx.InnerData != nil {
		for k, v := range ctx.InnerData {
			details[k] = v
		}
	}

	// Build display message
	message := ctx.What
	if ctx.Result != "" {
		message = fmt.Sprintf("%s → %s", ctx.What, ctx.Result)
	}

	// Broadcast to WebSocket
	s.broadcastDetailedLog(pluginID, siteID, level, stage, message, details)
	
	// Also log to session
	s.sessionLog(sessionID, level, stage, message, details)
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
}

// runStageWithSession executes a stage with session logging and captures timing/result
func (s *Service) runStageWithSession(sessionID, name string, fn func() error) Stage {
	start := time.Now()
	stage := Stage{
		Name:   name,
		Status: "running",
	}

	// Log stage start to session
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageStart(sessionID, strings.ToUpper(name))
	}

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = "failed"
		stage.Message = err.Error()
	} else {
		stage.Status = "completed"
	}

	// Log stage end to session
	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageEnd(sessionID, strings.ToUpper(name), stage.Status, stage.Duration)
	}

	return stage
}

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(pluginID, siteID int64, sessionID, stageName, status string, durationMs int64, details map[string]interface{}) {
	if s.wsHub == nil {
		return
	}

	payload := map[string]interface{}{
		"type":      "stage_complete",
		"sessionId": sessionID,
		"stage":     stageName,
		"status":    status,
		"duration":  durationMs,
		"pluginId":  pluginID,
		"siteId":    siteID,
	}
	if details != nil {
		payload["details"] = details
	}

	s.wsHub.Broadcast(ws.EventPublishProgress, payload)
}

// formatBytes formats byte count as human-readable string
func formatBytes(bytes int64) string {
	const unit = 1024
	if bytes < unit {
		return fmt.Sprintf("%d B", bytes)
	}
	div, exp := int64(unit), 0
	for n := bytes / unit; n >= unit; n /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f %cB", float64(bytes)/float64(div), "KMGTPE"[exp])
}

// truncateString truncates a string to maxLen with ellipsis
func truncateString(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen-3] + "..."
}

// createFullZip creates a zip file of the entire plugin directory
func (s *Service) createFullZip(pluginPath, pluginName string, excludePatterns []string) (string, error) {
	// Resolve temp directory to absolute path
	absTempDir, err := pathutil.ToAbsolute(s.tempDir)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	if err := os.MkdirAll(absTempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	// Resolve plugin path to absolute
	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	// Create slug from plugin name (lowercase, hyphens instead of spaces)
	// This slug is used BOTH for the ZIP filename AND as the folder name inside the ZIP
	// to match the PowerShell script behavior and WordPress expectations
	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))

	// Create zip file with slug-based name (no timestamp, no spaces)
	absZipPath := pathutil.MustJoin(absTempDir, fmt.Sprintf("%s.zip", slug))
	zipFile, err := os.Create(absZipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)

	// Walk the plugin directory (using absolute path)
	err = filepath.Walk(absPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		// Skip directories (they're created implicitly)
		if info.IsDir() {
			return nil
		}

		// Get relative path
		relPath, err := filepath.Rel(absPluginPath, path)
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

		// Create file in zip with SLUG as root folder (not display name)
		// This matches the PowerShell script behavior: the folder inside the ZIP
		// must match the expected WordPress plugin directory name (slug)
		zipEntryPath := filepath.Join(slug, relPath)
		zipEntryPath = filepath.ToSlash(zipEntryPath) // Normalize for zip

		writer, err := zipWriter.Create(zipEntryPath)
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
		zipWriter.Close()
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip archive")
	}

	// CRITICAL: Close the ZIP writer FIRST to finalize the archive (write central directory)
	// Then close the file. This must happen BEFORE returning the path.
	if err := zipWriter.Close(); err != nil {
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to finalize zip archive")
	}
	if err := zipFile.Close(); err != nil {
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to close zip file")
	}

	// Verify the file exists and has content
	if info, statErr := os.Stat(absZipPath); statErr != nil {
		return "", apperror.Wrap(statErr, apperror.ErrFSRead, "zip file not found after creation")
	} else if info.Size() == 0 {
		os.Remove(absZipPath)
		return "", apperror.New(apperror.ErrFSZip, "zip file is empty after creation")
	}

	return absZipPath, nil
}

// createSelectiveZip creates a zip file with only selected files
func (s *Service) createSelectiveZip(pluginPath, pluginName string, files []string) (string, error) {
	// Resolve temp directory to absolute path
	absTempDir, err := pathutil.ToAbsolute(s.tempDir)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	if err := os.MkdirAll(absTempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	// Resolve plugin path to absolute
	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	// Create slug from plugin name (lowercase, hyphens instead of spaces)
	// This slug is used BOTH for the ZIP filename AND as the folder name inside the ZIP
	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))

	// Create zip file with slug-based name (no timestamp, no spaces)
	absZipPath := pathutil.MustJoin(absTempDir, fmt.Sprintf("%s-patch.zip", slug))
	zipFile, err := os.Create(absZipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)

	for _, relPath := range files {
		fullPath := pathutil.MustJoin(absPluginPath, relPath)
		
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

		// Create file in zip with SLUG as root folder (not display name)
		// This matches the PowerShell script behavior
		zipFilePath := filepath.Join(slug, relPath)
		zipFilePath = filepath.ToSlash(zipFilePath)

		writer, err := zipWriter.Create(zipFilePath)
		if err != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", err
		}

		file, err := os.Open(fullPath)
		if err != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", err
		}

		_, err = io.Copy(writer, file)
		file.Close()
		if err != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", err
		}
	}

	// CRITICAL: Close the ZIP writer FIRST to finalize the archive (write central directory)
	// Then close the file. This must happen BEFORE returning the path.
	if err := zipWriter.Close(); err != nil {
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to finalize zip archive")
	}
	if err := zipFile.Close(); err != nil {
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to close zip file")
	}

	// Verify the file exists and has content
	if info, statErr := os.Stat(absZipPath); statErr != nil {
		return "", apperror.Wrap(statErr, apperror.ErrFSRead, "zip file not found after creation")
	} else if info.Size() == 0 {
		os.Remove(absZipPath)
		return "", apperror.New(apperror.ErrFSZip, "zip file is empty after creation")
	}

	return absZipPath, nil
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

// getZipStructure reads the internal structure of a ZIP file and returns a list of paths
func (s *Service) getZipStructure(zipPath string) []string {
	var entries []string

	// Resolve to absolute path
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		s.log.Warn("Failed to resolve ZIP path", "zipPath", zipPath, "error", err.Error())
		absZipPath = zipPath
	}
	
	reader, err := zip.OpenReader(absZipPath)
	if err != nil {
		s.log.Warn("Failed to read ZIP structure", "zipPath", absZipPath, "error", err.Error())
		return []string{"(failed to read ZIP structure: " + err.Error() + ")"}
	}
	defer reader.Close()
	
	for _, file := range reader.File {
		// Include folder indicator
		suffix := ""
		if file.FileInfo().IsDir() {
			suffix = "/"
		}
		entries = append(entries, fmt.Sprintf("%s%s (%d bytes)", file.Name, suffix, file.UncompressedSize64))
	}
	
	return entries
}

// uploadPlugin uploads a plugin zip to WordPress via available methods.
// Priority: 1) Plugin Uploader Helper (plugin-uploader/v1), 2) Onboard Plugin (onboard-plugin/v1), 3) Simulated
// Returns (performed, result, alreadyActivated, error).
func (s *Service) uploadPlugin(ctx context.Context, wpClient *wordpress.Client, zipPath, slug string) (bool, *wordpress.OnboardUploadResult, bool, error) {
	// Check if Plugin Uploader Helper is available (preferred - simpler API)
	uploaderAvailable, _ := wpClient.CheckUploaderHelperAvailable()
	if uploaderAvailable {
		s.log.Info("Using Plugin Uploader Helper for upload", "slug", slug)

		result, err := wpClient.UploadPluginViaUploader(zipPath, slug, true) // pass slug and activate=true
		if err != nil {
			return true, nil, false, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via uploader helper")
		}

		// Convert to OnboardUploadResult for compatibility
		onboardResult := &wordpress.OnboardUploadResult{
			Success:    result.Success,
			Message:    result.Message,
			PluginSlug: slug,
			Overwritten: true,
		}
		if result.PluginDetails != nil {
			onboardResult.PluginName = result.PluginDetails.Name
			onboardResult.Version = result.PluginDetails.Version
		}

		// Track if activation happened during upload
		alreadyActivated := result.Activated

		s.log.Info("Plugin uploaded via Plugin Uploader Helper",
			"slug", slug,
			"success", result.Success,
			"message", result.Message,
			"activated", result.Activated,
		)

		return true, onboardResult, alreadyActivated, nil
	}

	// Check if Onboard Plugin is available (legacy companion)
	onboardAvailable, err := wpClient.CheckOnboardPluginAvailable()
	if err != nil {
		s.log.Warn("Could not check for companion plugins", "error", err)
	}

	if onboardAvailable {
		s.log.Info("Using Onboard Plugin for upload", "slug", slug)

		result, err := wpClient.UploadPluginZip(zipPath, slug)
		if err != nil {
			return true, nil, false, apperror.Wrap(err, apperror.ErrWPUploadFailed, "failed to upload plugin via onboard plugin")
		}

		s.log.Info("Plugin uploaded via Onboard Plugin",
			"slug", slug,
			"success", result.Success,
			"message", result.Message,
			"filesUpdated", result.FilesUpdated,
			"overwritten", result.Overwritten,
		)

		return true, result, false, nil // Onboard plugin doesn't auto-activate
	}

	// No companion plugin available - log simulated upload
	s.log.Warn("No companion plugin available; upload simulated", "slug", slug)
	if info, err := os.Stat(zipPath); err == nil {
		s.log.Info("Plugin upload prepared (simulated)", "slug", slug, "size", info.Size())
	}
	return false, nil, false, nil
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

// Session logging helper methods

// sessionLog writes a log entry to the session file
func (s *Service) sessionLog(sessionID, level, step, message string, details map[string]interface{}) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.Log(sessionID, level, step, message, details)
}

// sessionLogStageStart writes a stage header to the session log
func (s *Service) sessionLogStageStart(sessionID, stageName string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageStart(sessionID, stageName)
}

// sessionLogStageEnd writes a stage completion marker
func (s *Service) sessionLogStageEnd(sessionID, stageName, status string, durationMs int64) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageEnd(sessionID, stageName, status, durationMs)
}

// endSession marks a session as complete
func (s *Service) endSession(sessionID, status, errorMsg string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.EndSession(sessionID, status, errorMsg)
}

// broadcastProgressWithSession sends a WebSocket progress event with session ID
func (s *Service) broadcastProgressWithSession(pluginID, siteID int64, sessionID, step string, progress int, message string) {
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

	// Broadcast progress event with session ID
	s.wsHub.BroadcastWithSession(eventType, map[string]interface{}{
		"pluginId":  pluginID,
		"siteId":    siteID,
		"sessionId": sessionID,
		"stage":     stage,
		"step":      step,
		"status":    status,
		"progress":  progress,
		"total":     100,
		"message":   message,
	}, sessionID)

	// Also broadcast detailed log entry for frontend live log display
	logLevel := "info"
	if step == "failed" {
		logLevel = "error"
	}
	s.wsHub.BroadcastPublishLogWithSession(pluginID, siteID, sessionID, logLevel, stage, message, nil)

	// Also log to session file
	s.sessionLog(sessionID, logLevel, stage, message, nil)

	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "sessionId", sessionID, "step", step, "stage", stage, "progress", progress, "message", message)
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
	PluginID    int64          `json:"pluginId"`
	PluginName  string         `json:"pluginName"`
	SiteID      int64          `json:"siteId"`
	SiteName    string         `json:"siteName"`
	SiteURL     string         `json:"siteUrl"`
	RemoteSlug  string         `json:"remoteSlug"`
	TotalFiles  int            `json:"totalFiles"`
	TotalSize   int64          `json:"totalSize"`
	Added       int            `json:"added"`
	Modified    int            `json:"modified"`
	Deleted     int            `json:"deleted"`
	Files       []FilePreview  `json:"files"`
}

// PreviewPublish returns a preview of what files will change during publish
func (s *Service) PreviewPublish(ctx context.Context, pluginID, siteID int64) (*PublishPreviewResult, error) {
	result := &PublishPreviewResult{
		PluginID: pluginID,
		SiteID:   siteID,
		Files:    []FilePreview{},
	}

	// Get plugin info
	pluginInfo, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "plugin not found")
	}
	result.PluginName = pluginInfo.Name

	// Get site info and password
	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "site not found")
	}
	result.SiteName = siteInfo.Name
	result.SiteURL = siteInfo.URL

	// Get mapping
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "plugin-site mapping not found")
	}
	result.RemoteSlug = mapping.RemoteSlug

	// Scan local files
	pluginPath, err := pathutil.ToAbsolute(pluginInfo.Path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "invalid plugin path")
	}

	excludePatterns := pluginInfo.ExcludePatterns
	localFiles := make(map[string]FilePreview)
	var totalSize int64

	err = filepath.Walk(pluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip inaccessible files
		}

		if info.IsDir() {
			// Check exclude patterns for directories
			for _, pattern := range excludePatterns {
				if strings.Contains(path, pattern) {
					return filepath.SkipDir
				}
			}
			return nil
		}

		// Get relative path
		relPath, err := filepath.Rel(pluginPath, path)
		if err != nil {
			return nil
		}

		// Check exclude patterns for files
		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
		}

		// Skip hidden files
		if strings.HasPrefix(filepath.Base(path), ".") {
			return nil
		}

		// Calculate hash for the file
		hash, _ := s.calculateFileHash(path)
		relPathSlash := filepath.ToSlash(relPath)

		localFiles[relPathSlash] = FilePreview{
			Path:      relPathSlash,
			Size:      info.Size(),
			LocalHash: hash,
		}
		totalSize += info.Size()

		return nil
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to scan plugin files")
	}

	// Attempt to fetch remote files for true diff comparison
	remoteFileMap := make(map[string]string) // path -> hash
	remoteFileFetchFailed := false

	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteFiles, err := wpClient.GetPluginFilesViaRiseup(ctx, mapping.RemoteSlug)
	if err != nil {
		// Fallback: try Onboard plugin
		remoteFiles, err = wpClient.GetPluginFiles(ctx, mapping.RemoteSlug)
		if err != nil {
			// Remote files not available - fall back to treating all as "added"
			s.log.Debug("Could not fetch remote files, falling back to local-only preview", "error", err)
			remoteFileFetchFailed = true
		}
	}

	if !remoteFileFetchFailed {
		for _, rf := range remoteFiles {
			remoteFileMap[rf.Path] = rf.Hash
		}
	}

	// Compare local and remote files
	var files []FilePreview
	var added, modified, deleted int

	for path, localFile := range localFiles {
		if remoteHash, exists := remoteFileMap[path]; exists {
			// File exists on remote - check if modified
			if localFile.LocalHash != remoteHash {
				localFile.ChangeType = "modified"
				modified++
			} else {
				// Same hash - no change, skip from preview
				continue
			}
			// Remove from remote map to track what's left (deleted files)
			delete(remoteFileMap, path)
		} else {
			// File doesn't exist on remote - it's new
			localFile.ChangeType = "added"
			added++
		}
		files = append(files, localFile)
	}

	// Remaining remote files are deleted (exist on remote but not locally)
	if !remoteFileFetchFailed {
		for path := range remoteFileMap {
			files = append(files, FilePreview{
				Path:       path,
				ChangeType: "deleted",
				Size:       0,
			})
			deleted++
		}
	} else {
		// If we couldn't fetch remote files, treat all local files as "added"
		for _, localFile := range localFiles {
			localFile.ChangeType = "added"
			files = append(files, localFile)
			added++
		}
	}

	result.Files = files
	result.TotalFiles = len(files)
	result.TotalSize = totalSize
	result.Added = added
	result.Modified = modified
	result.Deleted = deleted

	return result, nil
}

// calculateFileHash computes MD5 hash of a file
func (s *Service) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	h := md5.New()
	if _, err := io.Copy(h, file); err != nil {
		return "", err
	}

	return fmt.Sprintf("%x", h.Sum(nil)), nil
}

// FileDiffResult contains local and remote content for a single file
type FileDiffResult struct {
	Path          string `json:"path"`
	LocalContent  string `json:"localContent"`
	RemoteContent string `json:"remoteContent"`
}

// GetFileDiff retrieves both local and remote content for a file to show differences
func (s *Service) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (*FileDiffResult, error) {
	// Get plugin info
	pluginInfo, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "plugin not found")
	}

	// Get site credentials
	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "site not found")
	}

	// Get mapping to find remote slug
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "mapping not found")
	}

	result := &FileDiffResult{
		Path: filePath,
	}

	// Read local file content
	localPath := pathutil.MustJoin(pluginInfo.Path, filePath)
	localFile, err := os.Open(localPath)
	if err != nil {
		if !os.IsNotExist(err) {
			return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to read local file")
		}
		// File doesn't exist locally (deleted case)
		result.LocalContent = ""
	} else {
		defer localFile.Close()
		content, err := io.ReadAll(localFile)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to read local file content")
		}
		result.LocalContent = string(content)
	}

	// Fetch remote file content
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteContent, err := wpClient.GetPluginFileContent(ctx, mapping.RemoteSlug, filePath)
	if err != nil {
		// Remote file doesn't exist (added case) or error fetching
		s.log.Debug("Could not fetch remote file content", "path", filePath, "error", err)
		result.RemoteContent = ""
	} else {
		result.RemoteContent = remoteContent
	}

	return result, nil
}
