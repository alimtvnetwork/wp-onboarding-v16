package publish

import (
	"context"
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"wp-plugin-publish/internal/enums/endpoint"
	healthstatus "wp-plugin-publish/internal/enums/health_status"
	loglevel "wp-plugin-publish/internal/enums/log_level"
	pluginstatus "wp-plugin-publish/internal/enums/plugin_status"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) apperror.Result[PublishResult] {
	startTime := time.Now()
	result := &PublishResult{Success: false, ActivationStatus: healthstatus.Unknown.DBValue(), Stages: []Stage{}}

	pluginInfo, siteInfo, password, sessionID, err := s.initPublishContext(ctx, pluginID, siteID, result)
	if err != nil {
		return apperror.Ok(*result)
	}
	result.SessionID = sessionID

	s.broadcastProgressWithSession(pluginID, siteID, sessionID, stagestatus.Started.String(), 0, "Starting publish...")
	s.sessionLog(sessionID, loglevel.Info.String(), "init", fmt.Sprintf("Starting publish for %s to %s", pluginInfo.Name, siteInfo.Name), nil)

	if err := s.runPublishPipeline(ctx, pluginID, siteID, sessionID, pluginInfo, siteInfo, password, opts, result); err != nil {
		return apperror.Ok(*result)
	}

	s.finalizePublishResult(pluginID, siteID, pluginInfo, siteInfo, opts, result, startTime)
	return apperror.Ok(*result)
}

// initPublishContext loads plugin, site, credentials, and starts a session.
func (s *Service) initPublishContext(ctx context.Context, pluginID, siteID int64, result *PublishResult) (models.Plugin, *models.Site, string, string, error) {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		result.ErrorMessage = pluginResult.AppError().Error()
		s.broadcastProgress(pluginID, siteID, stagestatus.Failed.String(), 0, pluginResult.AppError().Error())
		return models.Plugin{}, nil, "", "", pluginResult.AppError()
	}
	pluginInfo := pluginResult.Value()

	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, stagestatus.Failed.String(), 0, err.Error())
		return models.Plugin{}, nil, "", "", err
	}

	sessionID, _ := s.startPublishSession(pluginID, siteID, pluginInfo, siteInfo)
	return pluginInfo, siteInfo, password, sessionID, nil
}

// runPublishPipeline executes the backup → package → upload → activate → cleanup pipeline.
func (s *Service) runPublishPipeline(ctx context.Context, pluginID, siteID int64, sessionID string, pluginInfo models.Plugin, siteInfo *models.Site, password string, options PublishOptions, result *PublishResult) error {
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.sessionLog(sessionID, loglevel.Error.String(), "init", fmt.Sprintf("Failed to get mapping: %s", err.Error()), nil)
		s.endSession(sessionID, loglevel.Error.String(), err.Error())
		s.broadcastProgressWithSession(pluginID, siteID, sessionID, stagestatus.Failed.String(), 0, err.Error())
		return err
	}

	s.log.Info("Creating WordPress client", "siteUrl", siteInfo.URL, "username", siteInfo.Username)
	s.broadcastDetailedLog(pluginID, siteID, loglevel.Info.String(), "connect", fmt.Sprintf("Connecting to WordPress: %s", siteInfo.URL), toDetails(ConnectDetails{SiteURL: siteInfo.URL, Username: siteInfo.Username}))
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)

	if options.CreateBackup {
		stage := s.executeBackupStage(pluginID, siteID, mapping)
		result.Stages = append(result.Stages, stage)
		if stage.Status.IsFailed() {
			result.ErrorMessage = stage.Message
			s.broadcastProgress(pluginID, siteID, stagestatus.Failed.String(), 10, stage.Message)
			return fmt.Errorf("%s", stage.Message)
		}
	}

	return s.runUploadAndActivate(ctx, pluginID, siteID, sessionID, wpClient, pluginInfo, siteInfo, mapping, options, result)
}

// runUploadAndActivate handles package, upload, activate, and cleanup stages.
func (s *Service) runUploadAndActivate(ctx context.Context, pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, pluginInfo models.Plugin, siteInfo *models.Site, mapping *models.PluginMapping, options PublishOptions, result *PublishResult) error {
	zipPath, fileCount, stage := s.executePackageStage(pluginID, siteID, pluginInfo, options)
	result.Stages = append(result.Stages, stage)
	if stage.Status.IsFailed() {
		result.ErrorMessage = stage.Message
		s.broadcastDetailedLog(pluginID, siteID, loglevel.Error.String(), "package", fmt.Sprintf("Package failed: %s", stage.Message), nil)
		s.broadcastProgress(pluginID, siteID, "failed", 30, stage.Message)
		return fmt.Errorf("%s", stage.Message)
	}

	publishFailed := false
	preUploadBackupZip := s.createPreUploadBackup(ctx, pluginID, siteID, sessionID, wpClient, mapping, options)
	if preUploadBackupZip != "" {
		defer os.Remove(preUploadBackupZip)
	}
	defer s.cleanupZip(pluginID, siteID, zipPath, publishFailed, options.KeepZipFiles)

	alreadyActivated, uploadStage := s.executeUploadStage(ctx, pluginID, siteID, sessionID, wpClient, zipPath, mapping, siteInfo)
	result.Stages = append(result.Stages, uploadStage)
	s.broadcastStageComplete(pluginID, siteID, sessionID, "upload", uploadStage.Status.String(), uploadStage.Duration, toDetails(UploadStageDetails{RemoteSlug: mapping.RemoteSlug, Activated: alreadyActivated}))
	if uploadStage.Status.IsFailed() {
		result.ErrorMessage = uploadStage.Message
		s.broadcastProgress(pluginID, siteID, stagestatus.Failed.String(), 60, uploadStage.Message)
		publishFailed = true
		return fmt.Errorf("%s", uploadStage.Message)
	}

	activateStage := s.executeActivateStage(pluginID, siteID, sessionID, wpClient, mapping, siteInfo, alreadyActivated)
	result.Stages = append(result.Stages, activateStage)
	s.broadcastStageComplete(pluginID, siteID, sessionID, "activate", activateStage.Status.String(), activateStage.Duration, toDetails(ActivateSkipDetails{RemoteSlug: mapping.RemoteSlug, Skipped: alreadyActivated}))
	if activateStage.Status.IsFailed() {
		result.ActivationStatus = loglevel.Error.String()
		result.ErrorMessage = activateStage.Message
		s.handleRollback(pluginID, siteID, sessionID, ctx, wpClient, mapping, siteInfo, preUploadBackupZip, options, activateStage, result)
	} else {
		result.ActivationStatus = pluginstatus.Active.String()
	}

	cleanupStage := s.executeCleanupStage(ctx, pluginID, siteID, options)
	result.Stages = append(result.Stages, cleanupStage)
	result.FilesUpdated = s.countFilesUpdated(options, pluginInfo, fileCount)
	return nil
}

// finalizePublishResult computes final metrics, broadcasts completion, and records history.
func (s *Service) finalizePublishResult(pluginID, siteID int64, pluginInfo models.Plugin, siteInfo *models.Site, options PublishOptions, result *PublishResult, startTime time.Time) {
	result.IsSuccess = result.ActivationStatus == pluginstatus.Active.String() || result.ActivationStatus == pluginstatus.Inactive.String()
	result.Duration = time.Since(startTime).Milliseconds()

	s.broadcastCompletion(pluginID, siteID, result)
	s.log.Info("Plugin published", "pluginId", pluginID, "siteId", siteID, "mode", options.Mode, "files", result.FilesUpdated, "duration", result.Duration, "success", result.IsSuccess)
	s.recordHistory(pluginInfo, siteInfo, options, result)
}

// PublishFiles publishes specific files to a WordPress site
func (s *Service) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) apperror.Result[PublishResult] {
	return s.Publish(ctx, pluginID, siteID, PublishOptions{
		Mode:         "selected",
		Files:        files,
		CreateBackup: false,
	})
}

// startPublishSession initializes a session for the publish operation
func (s *Service) startPublishSession(pluginID, siteID int64, pluginInfo models.Plugin, siteInfo *models.Site) (string, error) {
	if s.sessionService == nil {
		return "", nil
	}
	sessionID, err := s.sessionService.StartSession(session.SessionTypePublish, pluginID, siteID, pluginInfo.Name, siteInfo.Name)
	if err != nil {
		s.log.Warn("Failed to start session", "error", err)
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "failed to start publish session")
	}
	return sessionID, nil
}

// executeBackupStage runs the backup stage of the publish pipeline
func (s *Service) executeBackupStage(pluginID, siteID int64, mapping *models.PluginMapping) Stage {
	return s.runStage("backup", func() error {
		s.broadcastProgress(pluginID, siteID, "backup", 10, "Creating backup...")
		s.broadcastDetailedLog(pluginID, siteID, loglevel.Info.String(), "backup", "Initiating remote plugin backup", toDetails(BackupStageDetails{
			MappingID:  mapping.ID,
			RemoteSlug: mapping.RemoteSlug,
		}))
		// TODO: Implement backup creation via s.backupService
		return nil
	})
}

// executePackageStage builds the ZIP package and returns the path, file count, and stage result
func (s *Service) executePackageStage(pluginID, siteID int64, pluginInfo models.Plugin, options PublishOptions) (string, int, Stage) {
	var zipPath string
	var fileCount int

	stage := s.runStage("package", func() error {
		s.broadcastProgress(pluginID, siteID, "packaging", 30, "Building package...")
		s.broadcastDetailedLog(pluginID, siteID, loglevel.Info.String(), "package", fmt.Sprintf("Packaging plugin from: %s", pluginInfo.Path), toDetails(PackageDetails{
			PluginPath:      pluginInfo.Path,
			PluginName:      pluginInfo.Name,
			Mode:            options.Mode,
			ExcludePatterns: pluginInfo.ExcludePatterns,
		}))

		var err error
		if options.Mode == "selected" && len(options.Files) > 0 {
			fileCount = len(options.Files)
			s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("Creating selective ZIP with %d files", fileCount), toDetails(SelectedFilesDetails{
				SelectedFiles: options.Files,
			}))
			zipPath, err = s.createSelectiveZip(pluginInfo.Path, pluginInfo.Name, options.Files)
		} else {
			fileCount = pluginInfo.FileCount
			s.broadcastDetailedLog(pluginID, siteID, "info", "package", fmt.Sprintf("Creating full ZIP with ~%d files", fileCount), nil)
			zipPath, err = s.createFullZip(pluginInfo.Path, pluginInfo.Name, pluginInfo.ExcludePatterns)
		}

		if err == nil && zipPath != "" {
			s.logZipCreated(pluginID, siteID, zipPath, fileCount)
		}

		if err != nil {
			return apperror.Wrap(err, apperror.ErrInternal, "failed to create plugin ZIP package")
		}
		return nil
	})

	return zipPath, fileCount, stage
}

// createPreUploadBackup exports the remote plugin for rollback capability
func (s *Service) createPreUploadBackup(ctx context.Context, pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, mapping *models.PluginMapping, options PublishOptions) string {
	if !options.RollbackOnFailure {
		return ""
	}

	s.broadcastProgress(pluginID, siteID, "pre-backup", 45, "Creating pre-upload backup for rollback...")
	exportResult, exportErr := wpClient.ExportPlugin(mapping.RemoteSlug)
	if exportErr != nil {
		s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "pre-backup", StageContext{
			What:   "Pre-upload backup for rollback",
			Result: fmt.Sprintf("Skipped: %s (rollback won't be available)", exportErr.Error()),
		})
		return ""
	}

	if exportResult == nil || exportResult.PluginZip == "" {
		return ""
	}

	zipData, decErr := base64.StdEncoding.DecodeString(exportResult.PluginZip)
	if decErr != nil {
		return ""
	}

	backupPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-rollback-%d.zip", mapping.RemoteSlug, time.Now().Unix()))
	if writeErr := os.WriteFile(backupPath, zipData, 0644); writeErr != nil {
		return ""
	}

	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "pre-backup", StageContext{
		What:   "Pre-upload backup created",
		Result: fmt.Sprintf("Saved %s (%d files)", formatBytes(int64(len(zipData))), exportResult.FileCount),
	})
	return backupPath
}

// executeUploadStage uploads the plugin ZIP to WordPress
func (s *Service) executeUploadStage(ctx context.Context, pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, zipPath string, mapping *models.PluginMapping, siteInfo *models.Site) (bool, Stage) {
	var alreadyActivated bool
	uploadStartTime := time.Now()

	stage := s.runStageWithSession(sessionID, "upload", func() error {
		var zipSize int64
		if info, err := os.Stat(zipPath); err == nil {
			zipSize = info.Size()
		}

		s.broadcastProgress(pluginID, siteID, "uploading", 60, fmt.Sprintf("Uploading %s to WordPress...", formatBytes(zipSize)))

		retryCfg := DefaultRetryConfig()
		type uploadOut struct {
			performed    bool
			uploadResult *wordpress.OnboardUploadResult
			activated    bool
		}
		uploadVal, retryResult := withRetry(ctx, retryCfg, "upload", func(attempt int) (uploadOut, error) {
			p, ur, a, e := s.uploadPlugin(ctx, wpClient, zipPath, mapping.RemoteSlug)
			return uploadOut{p, ur, a}, e
		})

		performed := uploadVal.performed
		uploadResult := uploadVal.uploadResult
		alreadyActivated = uploadVal.activated
		err := retryResult.LastError

		if err != nil {
			s.logUploadError(pluginID, siteID, sessionID, siteInfo, mapping, retryResult.Attempts, err)
			return apperror.Wrap(err, apperror.ErrWPConnection, "plugin upload failed")
		}

		if performed {
			s.logUploadSuccess(pluginID, siteID, sessionID, mapping, zipSize, uploadStartTime, alreadyActivated, retryResult.Attempts, uploadResult)
			return nil
		}

		s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "upload", StageContext{
			What:   "Upload ZIP to WordPress",
			Result: "SIMULATED - no companion plugin available",
		})
		return nil
	})

	return alreadyActivated, stage
}

// executeActivateStage activates the plugin on WordPress
func (s *Service) executeActivateStage(pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, mapping *models.PluginMapping, siteInfo *models.Site, alreadyActivated bool) Stage {
	activateStartTime := time.Now()

	return s.runStageWithSession(sessionID, "activate", func() error {
		s.broadcastProgress(pluginID, siteID, "activating", 80, "Activating plugin...")

		if alreadyActivated {
			s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
				What:   "Activate plugin on WordPress",
				Why:    "Enable plugin functionality after upload",
				Where:  siteInfo.URL,
				Result: "SKIPPED - plugin activated during upload",
				Details: toDetails(ActivateSkipDetails{
					RemoteSlug: mapping.RemoteSlug,
					Skipped:    true,
				}),
			})
			return nil
		}

		return s.activateViaUploader(pluginID, siteID, sessionID, wpClient, mapping, siteInfo, activateStartTime)
	})
}

// activateViaUploader attempts plugin activation via the Riseup Asia Uploader
func (s *Service) activateViaUploader(pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, mapping *models.PluginMapping, siteInfo *models.Site, startTime time.Time) error {
	if available, _, _ := wpClient.CheckRiseupAsiaAvailable(); !available {
		s.broadcastStageLog(pluginID, siteID, sessionID, "error", "activate", StageContext{
			What:   "Activate plugin failed",
			Why:    "Riseup Asia Uploader is not available on the remote site",
			Where:  siteInfo.URL,
			Result: "FAILED: Install the Riseup Asia Uploader companion plugin to enable activation",
		})
		return apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available — cannot activate plugin").WithURL(siteInfo.URL)
	}

	endpointURL := fmt.Sprintf("%s/wp-json/%s%s", siteInfo.URL, wordpress.RiseupAsiaNamespace, endpoint.Enable)
	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
		What:  "Activate plugin via Riseup Asia Uploader",
		Why:   "Enable plugin after successful upload",
		Where: endpointURL,
		Details: toDetails(ActivateRequestDetails{
			Method:     "POST",
			RemoteSlug: mapping.RemoteSlug,
		}),
	})

	if err := wpClient.EnablePluginViaUploader(mapping.RemoteSlug); err != nil {
		s.logActivationError(pluginID, siteID, sessionID, mapping, endpointURL, startTime, err)
		return apperror.Wrap(err, apperror.ErrWPConnection, "plugin activation failed")
	}

	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "activate", StageContext{
		What:   "Activate plugin via Riseup Asia Uploader",
		Why:    "Enable plugin after upload",
		Where:  endpointURL,
		Result: "SUCCESS - plugin is now active",
		Details: toDetails(ActivateSuccessDetails{
			RemoteSlug: mapping.RemoteSlug,
			DurationMs: time.Since(startTime).Milliseconds(),
		}),
	})
	return nil
}

// handleRollback performs rollback when activation fails
func (s *Service) handleRollback(pluginID, siteID int64, sessionID string, ctx context.Context, wpClient *wordpress.Client, mapping *models.PluginMapping, siteInfo *models.Site, preUploadBackupZip string, options PublishOptions, activateStage Stage, result *PublishResult) {
	if !options.RollbackOnFailure {
		result.RollbackStatus = stagestatus.Skipped.String()
		result.RollbackMessage = "Rollback disabled by user"
		return
	}

	rollbackStage := s.runStageWithSession(sessionID, "rollback", func() error {
		return s.executeRollbackSteps(pluginID, siteID, sessionID, ctx, wpClient, mapping, siteInfo, preUploadBackupZip, activateStage)
	})
	result.Stages = append(result.Stages, rollbackStage)
	s.reportRollbackOutcome(pluginID, siteID, sessionID, rollbackStage, result)
}

// executeRollbackSteps deactivates the broken plugin and optionally re-uploads the backup.
func (s *Service) executeRollbackSteps(pluginID, siteID int64, sessionID string, ctx context.Context, wpClient *wordpress.Client, mapping *models.PluginMapping, siteInfo *models.Site, preUploadBackupZip string, activateStage Stage) error {
	s.broadcastProgress(pluginID, siteID, "rollback", 85, "Activation failed — rolling back...")
	s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "rollback", StageContext{
		What:  "Rolling back plugin after activation failure",
		Why:   fmt.Sprintf("Activation failed: %s", activateStage.Message),
		Where: siteInfo.URL,
	})

	s.rollbackDeactivate(pluginID, siteID, sessionID, wpClient, mapping)
	return s.rollbackRestore(pluginID, siteID, sessionID, ctx, wpClient, mapping, preUploadBackupZip)
}

// rollbackDeactivate deactivates the broken plugin during rollback.
func (s *Service) rollbackDeactivate(pluginID, siteID int64, sessionID string, wpClient *wordpress.Client, mapping *models.PluginMapping) {
	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "rollback", StageContext{
		What: "Deactivating broken plugin to stabilize site",
	})
	if disableErr := wpClient.DisablePluginViaUploader(mapping.RemoteSlug); disableErr != nil {
		s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "rollback", StageContext{
			What:   "Deactivation during rollback",
			Result: fmt.Sprintf("Could not deactivate: %s (site may already be safe)", disableErr.Error()),
		})
	}
}

// rollbackRestore re-uploads the pre-upload backup if available.
func (s *Service) rollbackRestore(pluginID, siteID int64, sessionID string, ctx context.Context, wpClient *wordpress.Client, mapping *models.PluginMapping, preUploadBackupZip string) error {
	if preUploadBackupZip == "" {
		s.broadcastStageLog(pluginID, siteID, sessionID, "warn", "rollback", StageContext{
			What:   "No pre-upload backup available",
			Result: "Plugin deactivated but files not restored. Manual intervention may be needed.",
		})
		return nil
	}

	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "rollback", StageContext{
		What: "Re-uploading pre-publish backup to restore previous version",
	})
	_, _, _, uploadErr := s.uploadPlugin(ctx, wpClient, preUploadBackupZip, mapping.RemoteSlug)
	if uploadErr != nil {
		return apperror.Wrap(uploadErr, apperror.ErrWPConnection, "rollback upload failed")
	}
	s.broadcastStageLog(pluginID, siteID, sessionID, "info", "rollback", StageContext{
		What:   "Rollback upload complete",
		Result: "Previous plugin version restored successfully",
	})
	return nil
}

// reportRollbackOutcome logs and sets the final rollback status on the result.
func (s *Service) reportRollbackOutcome(pluginID, siteID int64, sessionID string, rollbackStage Stage, result *PublishResult) {
	if rollbackStage.Status.IsFailed() {
		result.RollbackStatus = enumstatus.Failed.String()
		result.RollbackMessage = rollbackStage.Message
		s.broadcastStageLog(pluginID, siteID, sessionID, loglevel.Error.String(), "rollback", StageContext{What: "Rollback failed", Result: rollbackStage.Message})
	} else {
		result.RollbackStatus = enumstatus.Success.String()
		result.RollbackMessage = "Previous version restored"
		s.broadcastStageLog(pluginID, siteID, sessionID, loglevel.Info.String(), "rollback", StageContext{What: "Rollback completed successfully", Result: "Site should be stable with previous plugin version"})
	}
}

// executeCleanupStage marks files as synced
func (s *Service) executeCleanupStage(ctx context.Context, pluginID, siteID int64, options PublishOptions) Stage {
	return s.runStage("cleanup", func() error {
		s.broadcastProgress(pluginID, siteID, "cleanup", 95, "Marking files as synced...")
		s.broadcastDetailedLog(pluginID, siteID, "info", "cleanup", "Updating local sync state", nil)
		if options.Mode == "selected" && len(options.Files) > 0 {
			return s.syncService.MarkSynced(ctx, pluginID, siteID, options.Files)
		}
		return s.syncService.ClearChanges(ctx, pluginID)
	})
}

// countFilesUpdated returns the number of files updated based on publish mode
func (s *Service) countFilesUpdated(options PublishOptions, pluginInfo models.Plugin, fileCount int) int {
	if options.Mode == "selected" {
		return len(options.Files)
	}
	return pluginInfo.FileCount
}

// broadcastCompletion sends the final publish status broadcast
func (s *Service) broadcastCompletion(pluginID, siteID int64, result *PublishResult) {
	completionStep := stagestatus.Completed.String()
	completionMessage := fmt.Sprintf("Published %d files in %dms", result.FilesUpdated, result.Duration)
	if !result.IsSuccess {
		completionStep = stagestatus.Failed.String()
		completionMessage = result.ErrorMessage
		if completionMessage == "" {
			completionMessage = "Publish failed - check logs for details"
		}
	}

	logLevel := loglevel.Info.String()
	if !result.IsSuccess {
		logLevel = loglevel.Error.String()
	}

	s.broadcastDetailedLog(pluginID, siteID, logLevel, "complete", completionMessage, toDetails(CompletionDetails{
		IsSuccess:    result.IsSuccess,
		FilesUpdated: result.FilesUpdated,
		DurationMs:   result.Duration,
	}))
	s.broadcastProgress(pluginID, siteID, completionStep, 100, completionMessage)
}

// recordHistory records the publish result to the history service
func (s *Service) recordHistory(pluginInfo models.Plugin, siteInfo *models.Site, options PublishOptions, result *PublishResult) {
	if s.historyService == nil {
		return
	}

	historyStatus := enumstatus.Success.String()
	if !result.IsSuccess {
		historyStatus = enumstatus.Failed.String()
	}

	_, err := s.historyService.Record(models.PublishHistory{
		PluginID:         pluginInfo.ID,
		PluginName:       pluginInfo.Name,
		SiteID:           siteInfo.ID,
		SiteName:         siteInfo.Name,
		SiteURL:          siteInfo.URL,
		SessionID:        result.SessionID,
		Status:           historyStatus,
		Mode:             options.Mode,
		FilesUpdated:     result.FilesUpdated,
		ActivationStatus: result.ActivationStatus,
		RollbackStatus:   result.RollbackStatus,
		RollbackMessage:  result.RollbackMessage,
		ErrorMessage:     result.ErrorMessage,
		DurationMs:       result.Duration,
	})
	if err != nil {
		s.log.Error("Failed to record publish history", "error", err)
	}
}
