package publish

import (
	"context"
	"fmt"
	"os"
	"time"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	pluginstatus "wp-plugin-publish/internal/enums/pluginstatustype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
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

	s.initPipelineContext(pctx, siteInfo, password, mapping)

	if pctx.Options.IsCreateBackup {
		if err := s.runBackupStage(pctx); err != nil {
			return err
		}
	}

	return s.runUploadAndActivate(ctx, pctx)
}

// initPipelineContext sets up the WP client and context fields.
func (s *Service) initPipelineContext(pctx *publishContext, siteInfo *models.Site, password string, mapping *models.PluginMapping) {
	s.logConnect(pctx.PluginId, pctx.SiteId, siteInfo)
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)

	pctx.WPClient = wpClient
	pctx.Mapping = mapping
	pctx.SiteInfo = siteInfo
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
		return s.reportBackupFailure(pctx, stage)
	}

	return nil
}

// reportBackupFailure records and broadcasts a backup stage failure.
func (s *Service) reportBackupFailure(pctx *publishContext, stage Stage) error {
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

// runUploadAndActivate handles package, upload, activate, and cleanup stages.
func (s *Service) runUploadAndActivate(ctx context.Context, pctx *publishContext) error {
	pkgResult := s.executePackageStage(pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, pkgResult.Stage)

	if pkgResult.Stage.Status.IsFailed() {
		return s.failStage(pctx, publishstep.Package, pkgResult.Stage)
	}

	return s.uploadActivateAndCleanup(ctx, pctx, pkgResult)
}

// uploadActivateAndCleanup handles the post-packaging stages.
func (s *Service) uploadActivateAndCleanup(ctx context.Context, pctx *publishContext, pkgResult PackageStageResult) error {
	preUploadBackupZip := s.createPreUploadBackup(ctx, pctx)
	hasBackupZip := preUploadBackupZip != ""

	if hasBackupZip {
		defer os.Remove(preUploadBackupZip)
	}

	defer s.deferCleanupZip(pctx, pkgResult.ZipPath)

	return s.executeUploadAndFinish(ctx, pctx, pkgResult, preUploadBackupZip)
}

// deferCleanupZip builds the cleanup input and runs cleanup.
func (s *Service) deferCleanupZip(pctx *publishContext, zipPath string) {
	cleanupInput := cleanupZipInput{
		PluginId:        pctx.PluginId,
		SiteId:          pctx.SiteId,
		ZipPath:         zipPath,
		IsPublishFailed: false,
		IsKeepZipFiles:  pctx.Options.IsKeepZipFiles,
	}
	s.cleanupZip(cleanupInput)
}

// executeUploadAndFinish runs upload stage and counts files updated.
func (s *Service) executeUploadAndFinish(ctx context.Context, pctx *publishContext, pkgResult PackageStageResult, preUploadBackupZip string) error {
	if err := s.runUploadStage(ctx, pctx, pkgResult.ZipPath, preUploadBackupZip); err != nil {
		return err
	}

	pctx.Result.FilesUpdated = s.countFilesUpdated(pctx.Options, pctx.PluginInfo, pkgResult.FileCount)

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
	s.broadcastProgress(pctx.progress(publishstep.Failed, 30, stage.Message))

	return fmt.Errorf("%s", stage.Message)
}

// runUploadStage executes upload, activate, and cleanup stages.
func (s *Service) runUploadStage(ctx context.Context, pctx *publishContext, zipPath, preUploadBackupZip string) error {
	isAlreadyActivated, uploadStage := s.executeUploadStage(ctx, pctx, zipPath)
	pctx.Result.Stages = append(pctx.Result.Stages, uploadStage)

	s.broadcastUploadComplete(pctx, uploadStage, isAlreadyActivated)

	if uploadStage.Status.IsFailed() {
		return s.reportUploadFailure(pctx, uploadStage)
	}

	s.runActivateAndCleanup(ctx, pctx, isAlreadyActivated, preUploadBackupZip)

	return nil
}

// broadcastUploadComplete sends the upload stage complete event.
func (s *Service) broadcastUploadComplete(pctx *publishContext, uploadStage Stage, isAlreadyActivated bool) {
	uploadDetails := toDetails(UploadStageDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Activated:  isAlreadyActivated,
	})
	uploadComplete := pctx.stageComplete(stageCompleteInput{
		StageName:  publishstep.Upload,
		Status:     uploadStage.Status.String(),
		DurationMs: uploadStage.Duration,
		Details:    uploadDetails,
	})
	s.broadcastStageComplete(uploadComplete)
}

// reportUploadFailure records and broadcasts an upload failure.
func (s *Service) reportUploadFailure(pctx *publishContext, uploadStage Stage) error {
	pctx.Result.ErrorMessage = uploadStage.Message
	s.broadcastProgress(pctx.progress(publishstep.Failed, 60, uploadStage.Message))

	return fmt.Errorf("%s", uploadStage.Message)
}

// runActivateAndCleanup handles the activate and cleanup stages.
func (s *Service) runActivateAndCleanup(ctx context.Context, pctx *publishContext, isAlreadyActivated bool, preUploadBackupZip string) {
	activateStage := s.executeActivateStage(pctx, isAlreadyActivated)
	pctx.Result.Stages = append(pctx.Result.Stages, activateStage)

	s.broadcastActivateComplete(pctx, activateStage, isAlreadyActivated)
	s.handleActivateResult(ctx, pctx, activateStage, preUploadBackupZip)

	cleanupStage := s.executeCleanupStage(ctx, pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, cleanupStage)
}

// broadcastActivateComplete sends the activate stage complete event.
func (s *Service) broadcastActivateComplete(pctx *publishContext, activateStage Stage, isAlreadyActivated bool) {
	activateDetails := toDetails(ActivateSkipDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Skipped:    isAlreadyActivated,
	})
	activateComplete := pctx.stageComplete(stageCompleteInput{
		StageName:  publishstep.Activate,
		Status:     activateStage.Status.String(),
		DurationMs: activateStage.Duration,
		Details:    activateDetails,
	})
	s.broadcastStageComplete(activateComplete)
}

// handleActivateResult sets activation status and triggers rollback if needed.
func (s *Service) handleActivateResult(ctx context.Context, pctx *publishContext, activateStage Stage, preUploadBackupZip string) {
	if activateStage.Status.IsFailed() {
		pctx.Result.ActivationStatus = loglevel.Error.Lower()
		pctx.Result.ErrorMessage = activateStage.Message
		s.handleRollback(rollbackInput{
			Ctx:                ctx,
			Pctx:               pctx,
			PreUploadBackupZip: preUploadBackupZip,
			ActivateStage:      activateStage,
		})
	} else {
		pctx.Result.ActivationStatus = pluginstatus.Active.String()
	}
}

// finalizePublishResult computes final metrics, broadcasts completion, and records history.
func (s *Service) finalizePublishResult(pctx *publishContext) {
	isActive := pctx.Result.ActivationStatus == pluginstatus.Active.String()
	isInactive := pctx.Result.ActivationStatus == pluginstatus.Inactive.String()
	pctx.Result.IsSuccess = isActive || isInactive
	pctx.Result.Duration = time.Since(pctx.StartTime).Milliseconds()

	s.broadcastCompletion(pctx)
	s.logPublishComplete(pctx)
	s.recordHistory(pctx.PluginInfo, pctx.SiteInfo, pctx.Options, pctx.Result)
}

// logPublishComplete writes the final publish log entry.
func (s *Service) logPublishComplete(pctx *publishContext) {
	s.log.Info("Plugin published",
		"pluginId", pctx.PluginId,
		"siteId", pctx.SiteId,
		"mode", pctx.Options.Mode,
		"files", pctx.Result.FilesUpdated,
		"duration", pctx.Result.Duration,
		"success", pctx.Result.IsSuccess,
	)
}
