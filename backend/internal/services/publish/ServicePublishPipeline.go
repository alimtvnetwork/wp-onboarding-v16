package publish

import (
	"context"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
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
	mappingResult := s.getMapping(ctx, pctx.PluginId, pctx.SiteId)
	if mappingResult.HasError() {

		return s.failPipeline(pctx, mappingResult.AppError(), pctx.Result)
	}

	mapping := mappingResult.Value()
	s.initPipelineContext(pctx, creds, &mapping)

	if pctx.Options.IsCreateBackup {
		appErr := s.runBackupStage(ctx, pctx)
		if appErr != nil {

			return appErr
		}
	}

	return s.runUploadAndActivate(ctx, pctx)
}

// initPipelineContext sets up the WP client and context fields.
func (s *Service) initPipelineContext(pctx *publishContext, creds pipelineCredentials, mapping *models.PluginMapping) {
	s.logConnect(pctx.PluginId, pctx.SiteId, creds.SiteInfo)
	wpClient := s.wpClientFactory(creds.SiteInfo.Url, creds.SiteInfo.Username, creds.Password)

	pctx.WPClient = wpClient
	pctx.Mapping = mapping
	pctx.SiteInfo = creds.SiteInfo
}

// failPipeline handles a pipeline init failure.
func (s *Service) failPipeline(pctx *publishContext, appErr *apperror.AppError, result *PublishResult) *apperror.AppError {
	result.ErrorMessage = appErr.Error()

	failLog := sessionLogInput{
		SessionId: pctx.SessionId,
		Level:     loglevel.Error,
		Step:      publishstep.Init,
		Message:   fmt.Sprintf("Failed to get mapping: %s", appErr.Error()),
	}
	s.sessionLog(failLog)
	s.endSession(pctx.SessionId, loglevel.Error.Lower(), appErr.Error())
	s.broadcastProgress(pctx.progress(publishstep.Failed, 0, appErr.Error()))

	return apperror.Wrap(appErr, apperror.ErrInternal, "publish pipeline init failed")
}

// logConnect broadcasts the WordPress connection attempt.
func (s *Service) logConnect(pluginId, siteId int64, siteInfo *models.Site) {
	s.log.Info("Creating WordPress client", "siteUrl", siteInfo.Url, "username", siteInfo.Username)

	connectDetails := toDetails(ConnectDetails{
		SiteUrl:  siteInfo.Url,
		Username: siteInfo.Username,
	})
	connectLog := DetailedLogInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Level:    loglevel.Info,
		Step:     publishstep.Connect,
		Message:  fmt.Sprintf("Connecting to WordPress: %s", siteInfo.Url),
		Details:  connectDetails,
	}
	s.broadcastDetailedLog(connectLog)
}

// runBackupStage runs backup and appends the stage result.
func (s *Service) runBackupStage(ctx context.Context, pctx *publishContext) *apperror.AppError {
	stage := s.executeBackupStage(ctx, pctx)
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

	defer s.cleanupZip(cleanupZipInput{PluginId: pctx.PluginId, SiteId: pctx.SiteId, ZipPath: pkgResult.ZipPath, IsKeepZipFiles: pctx.Options.IsKeepZipFiles})

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
