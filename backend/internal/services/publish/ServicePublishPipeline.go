package publish

import (
	"context"
	"fmt"
	"time"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	pluginstatus "wp-plugin-publish/internal/enums/pluginstatustype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// ─── Pipeline ────────────────────────────────────────────────────────────────

// pipelineCredentials bundles site info and password for pipeline init.
type pipelineCredentials struct {
	SiteInfo *models.Site
	Password string
}

// runPublishPipeline executes the backup → package → upload → activate → cleanup pipeline.
func (s *Service) runPublishPipeline(ctx context.Context, pctx *publishContext, creds pipelineCredentials) *apperror.AppError {
	mapping, err := s.getMapping(ctx, pctx.PluginId, pctx.SiteId)
	if err != nil {

		return s.failPipeline(pctx, err, pctx.Result)
	}

	s.initPipelineContext(pctx, creds, mapping)

	if pctx.Options.IsCreateBackup {
		appErr := s.runBackupStage(pctx)
		if appErr != nil {

			return appErr
		}
	}

	return s.runUploadAndActivate(ctx, pctx)
}

// initPipelineContext sets up the WP client and context fields.
func (s *Service) initPipelineContext(pctx *publishContext, creds pipelineCredentials, mapping *models.PluginMapping) {
	s.logConnect(pctx.PluginId, pctx.SiteId, creds.SiteInfo)
	wpClient := s.wpClientFactory(creds.SiteInfo.URL, creds.SiteInfo.Username, creds.Password)

	pctx.WPClient = wpClient
	pctx.Mapping = mapping
	pctx.SiteInfo = creds.SiteInfo
}

// failPipeline handles a pipeline init failure.
func (s *Service) failPipeline(pctx *publishContext, err error, result *PublishResult) *apperror.AppError {
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

	return apperror.Wrap(err, apperror.ErrInternal, "publish pipeline init failed")
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
func (s *Service) runBackupStage(pctx *publishContext) *apperror.AppError {
	stage := s.executeBackupStage(pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, stage)

	if stage.Status.IsFailed() {

		return s.reportBackupFailure(pctx, stage)
	}

	return nil
}

// reportBackupFailure records and broadcasts a backup stage failure.
func (s *Service) reportBackupFailure(pctx *publishContext, stage Stage) *apperror.AppError {
	pctx.Result.ErrorMessage = stage.Message

	failProgress := ProgressInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Step:     publishstep.Failed,
		Progress: 10,
		Message:  stage.Message,
	}
	s.broadcastProgress(failProgress)

	return apperror.New(apperror.ErrInternal, stage.Message)
}

// runUploadAndActivate handles package, upload, activate, and cleanup stages.
func (s *Service) runUploadAndActivate(ctx context.Context, pctx *publishContext) *apperror.AppError {
	pkgResult := s.executePackageStage(pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, pkgResult.Stage)

	if pkgResult.Stage.Status.IsFailed() {

		return s.failStage(pctx, publishstep.Package, pkgResult.Stage)
	}

	return s.uploadActivateAndCleanup(ctx, pctx, pkgResult)
}

// uploadActivateAndCleanup handles the post-packaging stages.
func (s *Service) uploadActivateAndCleanup(ctx context.Context, pctx *publishContext, pkgResult PackageStageResult) *apperror.AppError {
	preUploadBackupZip := s.createPreUploadBackup(ctx, pctx)
	hasBackupZip := preUploadBackupZip != ""

	if hasBackupZip {
		defer pathutil.RemoveFileUnchecked(preUploadBackupZip)
	}

	defer s.deferCleanupZip(pctx, pkgResult.ZipPath)

	return s.executeUploadAndFinish(ctx, pctx, uploadFinishInput{PkgResult: pkgResult, PreUploadBackupZip: preUploadBackupZip})
}

// uploadFinishInput bundles parameters for executeUploadAndFinish.
type uploadFinishInput struct {
	PkgResult          PackageStageResult
	PreUploadBackupZip string
}

// executeUploadAndFinish runs upload stage and counts files updated.
func (s *Service) executeUploadAndFinish(ctx context.Context, pctx *publishContext, input uploadFinishInput) *apperror.AppError {
	appErr := s.runUploadStage(ctx, pctx, uploadStageInput{ZipPath: input.PkgResult.ZipPath, PreUploadBackupZip: input.PreUploadBackupZip})
	if appErr != nil {

		return appErr
	}

	pctx.Result.FilesUpdated = s.countFilesUpdated(pctx.Options, pctx.PluginInfo, input.PkgResult.FileCount)

	return nil
}

// failStage records a stage failure in the result.
func (s *Service) failStage(pctx *publishContext, step publishstep.Variant, stage Stage) *apperror.AppError {
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

	return apperror.New(apperror.ErrInternal, stage.Message)
}

// uploadStageInput bundles parameters for runUploadStage.
type uploadStageInput struct {
	ZipPath            string
	PreUploadBackupZip string
}

// runUploadStage executes upload, activate, and cleanup stages.
func (s *Service) runUploadStage(ctx context.Context, pctx *publishContext, input uploadStageInput) *apperror.AppError {
	isAlreadyActivated, uploadStage := s.executeUploadStage(ctx, pctx, input.ZipPath)
	pctx.Result.Stages = append(pctx.Result.Stages, uploadStage)

	s.broadcastUploadComplete(pctx, uploadStage, isAlreadyActivated)

	if uploadStage.Status.IsFailed() {

		return s.reportUploadFailure(pctx, uploadStage)
	}

	s.runActivateAndCleanup(ctx, pctx, activateCleanupInput{IsAlreadyActivated: isAlreadyActivated, PreUploadBackupZip: input.PreUploadBackupZip})

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
func (s *Service) reportUploadFailure(pctx *publishContext, uploadStage Stage) *apperror.AppError {
	pctx.Result.ErrorMessage = uploadStage.Message
	s.broadcastProgress(pctx.progress(publishstep.Failed, 60, uploadStage.Message))

	return apperror.New(apperror.ErrWPUploadFailed, uploadStage.Message)
}

// activateCleanupInput bundles parameters for runActivateAndCleanup.
type activateCleanupInput struct {
	IsAlreadyActivated bool
	PreUploadBackupZip string
}

// runActivateAndCleanup handles the activate and cleanup stages.
func (s *Service) runActivateAndCleanup(ctx context.Context, pctx *publishContext, input activateCleanupInput) {
	activateStage := s.executeActivateStage(pctx, input.IsAlreadyActivated)
	pctx.Result.Stages = append(pctx.Result.Stages, activateStage)

	s.broadcastActivateComplete(pctx, activateStage, input.IsAlreadyActivated)
	s.handleActivateResult(ctx, pctx, activateResultInput{ActivateStage: activateStage, PreUploadBackupZip: input.PreUploadBackupZip})

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

// activateResultInput bundles parameters for handleActivateResult.
type activateResultInput struct {
	ActivateStage      Stage
	PreUploadBackupZip string
}

// handleActivateResult sets activation status and triggers rollback if needed.
func (s *Service) handleActivateResult(ctx context.Context, pctx *publishContext, input activateResultInput) {
	if input.ActivateStage.Status.IsFailed() {
		pctx.Result.ActivationStatus = loglevel.Error.Lower()
		pctx.Result.ErrorMessage = input.ActivateStage.Message
		s.handleRollback(rollbackInput{
			Ctx:                ctx,
			Pctx:               pctx,
			PreUploadBackupZip: input.PreUploadBackupZip,
			ActivateStage:      input.ActivateStage,
		})

		return
	}

	pctx.Result.ActivationStatus = pluginstatus.Active.String()
}

// finalizePublishResult computes final metrics, broadcasts completion, and records history.
func (s *Service) finalizePublishResult(pctx *publishContext) {
	isActive := pctx.Result.ActivationStatus == pluginstatus.Active.String()
	isInactive := pctx.Result.ActivationStatus == pluginstatus.Inactive.String()
	pctx.Result.IsSuccess =
		isActive ||
		isInactive
	pctx.Result.Duration = time.Since(pctx.StartTime).Milliseconds()

	s.broadcastCompletion(pctx)
	s.logPublishComplete(pctx)
	s.recordHistory(pctx)
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
