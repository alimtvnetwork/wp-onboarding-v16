package publish

import (
	"context"
	"fmt"
	"os"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	pluginstatus "wp-plugin-publish/internal/enums/plugin_status"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
)

// ─── Pipeline ────────────────────────────────────────────────────────────────

// runPublishPipeline executes the backup → package → upload → activate → cleanup pipeline.
func (s *Service) runPublishPipeline(ctx context.Context, pctx *publishContext, siteInfo *models.Site, password string) error {
	mapping, err := s.getMapping(ctx, pctx.PluginId, pctx.SiteId)
	if err != nil {
		return s.failPipeline(pctx, err, pctx.Result)
	}

	s.logConnect(pctx.PluginId, pctx.SiteId, siteInfo)
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)

	pctx.WPClient = wpClient
	pctx.Mapping = mapping
	pctx.SiteInfo = siteInfo

	if pctx.Options.IsCreateBackup {
		if err := s.runBackupStage(pctx); err != nil {
			return err
		}
	}

	return s.runUploadAndActivate(ctx, pctx)
}

// failPipeline handles a pipeline init failure.
func (s *Service) failPipeline(pctx *publishContext, err error, result *PublishResult) error {
	result.ErrorMessage = err.Error()

	failLog := sessionLogInput{
		SessionId: pctx.SessionId,
		Level:     loglevel.Error,
		Step:      publishstep.Init,
		Message:   fmt.Sprintf("Failed to get mapping: %s", err.Error()),
	}
	s.sessionLog(failLog)
	s.endSession(pctx.SessionId, loglevel.Error.Lower(), err.Error())
	s.broadcastProgress(pctx.progress(publishstep.Failed, 0, err.Error()))

	return err
}

// logConnect broadcasts the WordPress connection attempt.
func (s *Service) logConnect(pluginID, siteID int64, siteInfo *models.Site) {
	s.log.Info("Creating WordPress client", "siteUrl", siteInfo.URL, "username", siteInfo.Username)

	connectDetails := toDetails(ConnectDetails{
		SiteURL:  siteInfo.URL,
		Username: siteInfo.Username,
	})
	connectLog := DetailedLogInput{
		PluginId: pluginID,
		SiteId:   siteID,
		Level:    loglevel.Info,
		Step:     publishstep.Connect,
		Message:  fmt.Sprintf("Connecting to WordPress: %s", siteInfo.URL),
		Details:  connectDetails,
	}
	s.broadcastDetailedLog(connectLog)
}

// runBackupStage runs backup and appends the stage result.
func (s *Service) runBackupStage(pctx *publishContext) error {
	stage := s.executeBackupStage(pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, stage)

	if stage.Status.IsFailed() {
		pctx.Result.ErrorMessage = stage.Message

		failProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Failed,
			Progress: 10,
			Message:  stage.Message,
		}
		s.broadcastProgress(failProgress)

		return fmt.Errorf("%s", stage.Message)
	}

	return nil
}

// runUploadAndActivate handles package, upload, activate, and cleanup stages.
func (s *Service) runUploadAndActivate(ctx context.Context, pctx *publishContext) error {
	zipPath, fileCount, stage := s.executePackageStage(pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, stage)

	if stage.Status.IsFailed() {
		return s.failStage(pctx, publishstep.Package, stage)
	}

	isPublishFailed := false
	preUploadBackupZip := s.createPreUploadBackup(ctx, pctx)

	if preUploadBackupZip != "" {
		defer os.Remove(preUploadBackupZip)
	}

	cleanupInput := cleanupZipInput{
		PluginId:        pctx.PluginId,
		SiteId:          pctx.SiteId,
		ZipPath:         zipPath,
		IsPublishFailed: isPublishFailed,
		IsKeepZipFiles:  pctx.Options.IsKeepZipFiles,
	}
	defer s.cleanupZip(cleanupInput)

	if err := s.runUploadStage(ctx, pctx, zipPath, preUploadBackupZip); err != nil {
		isPublishFailed = true

		return err
	}

	pctx.Result.FilesUpdated = s.countFilesUpdated(pctx.Options, pctx.PluginInfo, fileCount)

	return nil
}

// failStage records a stage failure in the result.
func (s *Service) failStage(pctx *publishContext, step publishstep.Variant, stage Stage) error {
	pctx.Result.ErrorMessage = stage.Message

	failLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Error,
		Step:     step,
		Message:  fmt.Sprintf("%s failed: %s", step.Value(), stage.Message),
	}
	s.broadcastDetailedLog(failLog)

	failProgress := ProgressInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Step:     publishstep.Failed,
		Progress: 30,
		Message:  stage.Message,
	}
	s.broadcastProgress(failProgress)

	return fmt.Errorf("%s", stage.Message)
}

// runUploadStage executes upload, activate, and cleanup stages.
func (s *Service) runUploadStage(ctx context.Context, pctx *publishContext, zipPath, preUploadBackupZip string) error {
	isAlreadyActivated, uploadStage := s.executeUploadStage(ctx, pctx, zipPath)
	pctx.Result.Stages = append(pctx.Result.Stages, uploadStage)

	uploadDetails := toDetails(UploadStageDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Activated:  isAlreadyActivated,
	})
	uploadComplete := pctx.stageComplete(
		publishstep.Upload,
		uploadStage.Status.String(),
		uploadStage.Duration,
		uploadDetails,
	)
	s.broadcastStageComplete(uploadComplete)

	if uploadStage.Status.IsFailed() {
		pctx.Result.ErrorMessage = uploadStage.Message
		s.broadcastProgress(pctx.progress(publishstep.Failed, 60, uploadStage.Message))

		return fmt.Errorf("%s", uploadStage.Message)
	}

	s.runActivateAndCleanup(ctx, pctx, isAlreadyActivated, preUploadBackupZip)

	return nil
}

// runActivateAndCleanup handles the activate and cleanup stages.
func (s *Service) runActivateAndCleanup(ctx context.Context, pctx *publishContext, isAlreadyActivated bool, preUploadBackupZip string) {
	activateStage := s.executeActivateStage(pctx, isAlreadyActivated)
	pctx.Result.Stages = append(pctx.Result.Stages, activateStage)

	activateDetails := toDetails(ActivateSkipDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Skipped:    isAlreadyActivated,
	})
	activateComplete := pctx.stageComplete(
		publishstep.Activate,
		activateStage.Status.String(),
		activateStage.Duration,
		activateDetails,
	)
	s.broadcastStageComplete(activateComplete)

	if activateStage.Status.IsFailed() {
		pctx.Result.ActivationStatus = loglevel.Error.Lower()
		pctx.Result.ErrorMessage = activateStage.Message
		s.handleRollback(ctx, pctx, preUploadBackupZip, activateStage)
	} else {
		pctx.Result.ActivationStatus = pluginstatus.Active.String()
	}

	cleanupStage := s.executeCleanupStage(ctx, pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, cleanupStage)
}

// finalizePublishResult computes final metrics, broadcasts completion, and records history.
func (s *Service) finalizePublishResult(pctx *publishContext) {
	pctx.Result.IsSuccess = pctx.Result.ActivationStatus == pluginstatus.Active.String() ||
		pctx.Result.ActivationStatus == pluginstatus.Inactive.String()
	pctx.Result.Duration = time.Since(pctx.StartTime).Milliseconds()

	s.broadcastCompletion(pctx)
	s.log.Info("Plugin published",
		"pluginId", pctx.PluginId,
		"siteId", pctx.SiteId,
		"mode", pctx.Options.Mode,
		"files", pctx.Result.FilesUpdated,
		"duration", pctx.Result.Duration,
		"success", pctx.Result.IsSuccess,
	)
	s.recordHistory(pctx.PluginInfo, pctx.SiteInfo, pctx.Options, pctx.Result)
}
