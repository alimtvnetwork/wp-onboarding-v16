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
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	publishtype "wp-plugin-publish/internal/enums/publish_type"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// publishContext bundles the recurring identifiers and dependencies that flow through the publish pipeline.
type publishContext struct {
	PluginId   int64
	SiteId     int64
	SessionId  string
	WPClient   *wordpress.Client
	Mapping    *models.PluginMapping
	SiteInfo   *models.Site
	PluginInfo models.Plugin
	Options    PublishOptions
	Result     *PublishResult
	StartTime  time.Time
}

// stageLog builds a StageLogInput from the context fields.
func (p *publishContext) stageLog(level loglevel.Variant, stage publishstep.Variant, ctx StageContext) StageLogInput {
	return StageLogInput{
		PluginId:  p.PluginId,
		SiteId:    p.SiteId,
		SessionId: p.SessionId,
		Level:     level,
		Stage:     stage,
		Ctx:       ctx,
	}
}

// progress builds a ProgressInput from the context fields.
func (p *publishContext) progress(step publishstep.Variant, pct int, message string) ProgressInput {
	return ProgressInput{
		PluginId:  p.PluginId,
		SiteId:    p.SiteId,
		SessionId: p.SessionId,
		Step:      step,
		Progress:  pct,
		Message:   message,
	}
}

// stageComplete builds a StageCompleteInput from the context fields.
func (p *publishContext) stageComplete(stageName publishstep.Variant, status string, durationMs int64, details []byte) StageCompleteInput {
	return StageCompleteInput{
		PluginId:   p.PluginId,
		SiteId:     p.SiteId,
		SessionId:  p.SessionId,
		StageName:  stageName,
		Status:     status,
		DurationMs: durationMs,
		Details:    details,
	}
}

// ─── Publish Entry Points ────────────────────────────────────────────────────

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) apperror.Result[PublishResult] {
	result := &PublishResult{
		ActivationStatus: healthstatus.Unknown.DBValue(),
		Stages:           []Stage{},
	}

	pluginInfo, siteInfo, password, sessionID, err := s.initPublishContext(ctx, pluginID, siteID, result)
	if err != nil {
		return apperror.Ok(*result)
	}
	result.SessionId = sessionID

	startProgress := ProgressInput{
		PluginId:  pluginID,
		SiteId:    siteID,
		SessionId: sessionID,
		Step:      publishstep.Started,
		Progress:  0,
		Message:   "Starting publish...",
	}
	s.broadcastProgress(startProgress)

	initLog := sessionLogInput{
		SessionId: sessionID,
		Level:     loglevel.Info,
		Step:      publishstep.Init,
		Message:   fmt.Sprintf("Starting publish for %s to %s", pluginInfo.Name, siteInfo.Name),
	}
	s.sessionLog(initLog)

	pctx := &publishContext{
		PluginId:   pluginID,
		SiteId:     siteID,
		SessionId:  sessionID,
		PluginInfo: pluginInfo,
		Options:    opts,
		Result:     result,
		StartTime:  time.Now(),
	}

	if err := s.runPublishPipeline(ctx, pctx, siteInfo, password); err != nil {
		return apperror.Ok(*result)
	}

	s.finalizePublishResult(pctx)

	return apperror.Ok(*result)
}

// initPublishContext loads plugin, site, credentials, and starts a session.
func (s *Service) initPublishContext(ctx context.Context, pluginID, siteID int64, result *PublishResult) (models.Plugin, *models.Site, string, string, error) {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return s.failInit(pluginID, siteID, pluginResult.AppError(), result)
	}

	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return s.failInit(pluginID, siteID, err, result)
	}

	pluginInfo := pluginResult.Value()
	sessionID, _ := s.startPublishSession(pluginID, siteID, pluginInfo, siteInfo)

	return pluginInfo, siteInfo, password, sessionID, nil
}

// failInit records error and broadcasts failure for init context.
func (s *Service) failInit(pluginID, siteID int64, err error, result *PublishResult) (models.Plugin, *models.Site, string, string, error) {
	result.ErrorMessage = err.Error()

	failProgress := ProgressInput{
		PluginId: pluginID,
		SiteId:   siteID,
		Step:     publishstep.Failed,
		Progress: 0,
		Message:  err.Error(),
	}
	s.broadcastProgress(failProgress)

	return models.Plugin{}, nil, "", "", err
}

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

// PublishFiles publishes specific files to a WordPress site
func (s *Service) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) apperror.Result[PublishResult] {
	opts := PublishOptions{
		Mode:           publishtype.Selected,
		Files:          files,
		IsCreateBackup: false,
	}

	return s.Publish(ctx, pluginID, siteID, opts)
}

// startPublishSession initializes a session for the publish operation
func (s *Service) startPublishSession(pluginID, siteID int64, pluginInfo models.Plugin, siteInfo *models.Site) (string, error) {
	if s.sessionService == nil {
		return "", nil
	}

	startInput := session.StartSessionInput{
		Type:       session.SessionTypePublish,
		PluginID:   pluginID,
		SiteID:     siteID,
		PluginName: pluginInfo.Name,
		SiteName:   siteInfo.Name,
	}
	sessionID, err := s.sessionService.StartSession(startInput)
	if err != nil {
		s.log.Warn("Failed to start session", "error", err)

		return "", apperror.Wrap(err, apperror.ErrSessionInit, "failed to start publish session")
	}

	return sessionID, nil
}

// ─── Stage Execution ─────────────────────────────────────────────────────────

// executeBackupStage runs the backup stage of the publish pipeline
func (s *Service) executeBackupStage(pctx *publishContext) Stage {
	return s.runStage("backup", func() error {
		backupProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Backup,
			Progress: 10,
			Message:  "Creating backup...",
		}
		s.broadcastProgress(backupProgress)

		backupDetails := toDetails(BackupStageDetails{
			MappingID:  pctx.Mapping.ID,
			RemoteSlug: pctx.Mapping.RemoteSlug,
		})
		backupLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Backup,
			Message:  "Initiating remote plugin backup",
			Details:  backupDetails,
		}
		s.broadcastDetailedLog(backupLog)

		return nil
	})
}

// executePackageStage builds the ZIP package and returns the path, file count, and stage result
func (s *Service) executePackageStage(pctx *publishContext) (string, int, Stage) {
	var zipPath string
	var fileCount int

	stage := s.runStage("package", func() error {
		pkgProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Packaging,
			Progress: 30,
			Message:  "Building package...",
		}
		s.broadcastProgress(pkgProgress)

		var err error
		zipPath, fileCount, err = s.buildPluginPackage(pctx)

		return err
	})

	return zipPath, fileCount, stage
}

// buildPluginPackage creates the ZIP for full or selective mode.
func (s *Service) buildPluginPackage(pctx *publishContext) (string, int, error) {
	pkgDetails := toDetails(PackageDetails{
		PluginPath:      pctx.PluginInfo.Path,
		PluginName:      pctx.PluginInfo.Name,
		Mode:            pctx.Options.Mode,
		ExcludePatterns: pctx.PluginInfo.ExcludePatterns,
	})
	pkgLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Packaging plugin from: %s", pctx.PluginInfo.Path),
		Details:  pkgDetails,
	}
	s.broadcastDetailedLog(pkgLog)

	zipPath, fileCount, err := s.buildZip(pctx)
	if err != nil {
		return "", 0, apperror.Wrap(err, apperror.ErrInternal, "failed to create plugin ZIP package")
	}

	if zipPath != "" {
		zipInput := logZipInput{
			PluginId:  pctx.PluginId,
			SiteId:    pctx.SiteId,
			ZipPath:   zipPath,
			FileCount: fileCount,
		}
		s.logZipCreated(zipInput)
	}

	return zipPath, fileCount, nil
}

// buildZip delegates to selective or full zip creation.
func (s *Service) buildZip(pctx *publishContext) (string, int, error) {
	if pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0 {
		selectDetails := toDetails(SelectedFilesDetails{
			SelectedFiles: pctx.Options.Files,
		})
		selectLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("Creating selective ZIP with %d files", len(pctx.Options.Files)),
			Details:  selectDetails,
		}
		s.broadcastDetailedLog(selectLog)

		path, err := s.createSelectiveZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.Options.Files)

		return path, len(pctx.Options.Files), err
	}

	fullLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Creating full ZIP with ~%d files", pctx.PluginInfo.FileCount),
	}
	s.broadcastDetailedLog(fullLog)

	path, err := s.createFullZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.PluginInfo.ExcludePatterns)

	return path, pctx.PluginInfo.FileCount, err
}

// ─── Pre-Upload Backup ──────────────────────────────────────────────────────

// createPreUploadBackup exports the remote plugin for rollback capability
func (s *Service) createPreUploadBackup(ctx context.Context, pctx *publishContext) string {
	s.broadcastProgress(pctx.progress(publishstep.PreBackup, 45, "Creating pre-upload backup for rollback..."))

	return s.exportRemoteForRollback(pctx)
}

// exportRemoteForRollback exports and saves the remote plugin zip.
func (s *Service) exportRemoteForRollback(pctx *publishContext) string {
	exportResult, exportErr := pctx.WPClient.ExportPlugin(pctx.Mapping.RemoteSlug)
	if exportErr != nil {
		skipCtx := StageContext{
			What:   "Pre-upload backup for rollback",
			Result: fmt.Sprintf("Skipped: %s (rollback won't be available)", exportErr.Error()),
		}
		skipLog := pctx.stageLog(loglevel.Warn, publishstep.PreBackup, skipCtx)
		s.broadcastStageLog(skipLog)

		return ""
	}

	if exportResult == nil || exportResult.PluginZip == "" {
		return ""
	}

	return s.saveRollbackZip(pctx, exportResult)
}

// saveRollbackZip decodes and writes the rollback zip to disk.
func (s *Service) saveRollbackZip(pctx *publishContext, exportResult *wordpress.ExportResult) string {
	zipData, decErr := base64.StdEncoding.DecodeString(exportResult.PluginZip)
	if decErr != nil {
		return ""
	}

	backupPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-rollback-%d.zip", pctx.Mapping.RemoteSlug, time.Now().Unix()))
	if writeErr := os.WriteFile(backupPath, zipData, 0644); writeErr != nil {
		return ""
	}

	savedCtx := StageContext{
		What:   "Pre-upload backup created",
		Result: fmt.Sprintf("Saved %s (%d files)", formatBytes(int64(len(zipData))), exportResult.FileCount),
	}
	savedLog := pctx.stageLog(loglevel.Info, publishstep.PreBackup, savedCtx)
	s.broadcastStageLog(savedLog)

	return backupPath
}

// ─── Upload ──────────────────────────────────────────────────────────────────

// executeUploadStage uploads the plugin ZIP to WordPress
func (s *Service) executeUploadStage(ctx context.Context, pctx *publishContext, zipPath string) (bool, Stage) {
	var isAlreadyActivated bool
	uploadStartTime := time.Now()

	stage := s.runStageWithSession(pctx.SessionId, "upload", func() error {
		var err error
		isAlreadyActivated, err = s.performUpload(ctx, pctx, zipPath, uploadStartTime)

		return err
	})

	return isAlreadyActivated, stage
}

// performUpload handles the upload retry and result logging.
func (s *Service) performUpload(ctx context.Context, pctx *publishContext, zipPath string, startTime time.Time) (bool, error) {
	zipSize := getFileSize(zipPath)
	s.broadcastProgress(pctx.progress(publishstep.Uploading, 60, fmt.Sprintf("Uploading %s to WordPress...", formatBytes(zipSize))))

	type uploadOut struct {
		isPerformed  bool
		uploadResult *wordpress.OnboardUploadResult
		isActivated  bool
	}
	retryCfg := DefaultRetryConfig()
	uploadVal, retryResult := withRetry(ctx, retryCfg, "upload", func(attempt int) (uploadOut, *apperror.AppError) {
		p, ur, a, e := s.uploadPlugin(ctx, pctx.WPClient, zipPath, pctx.Mapping.RemoteSlug)

		return uploadOut{p, ur, a}, e
	})

	if retryResult.LastError != nil {
		s.logUploadError(pctx, retryResult.Attempts, retryResult.LastError)

		return false, apperror.Wrap(retryResult.LastError, apperror.ErrWPConnection, "plugin upload failed")
	}

	if uploadVal.isPerformed {
		successInput := logUploadSuccessInput{
			ZipSize:      zipSize,
			StartTime:    startTime,
			IsActivated:  uploadVal.isActivated,
			Attempts:     retryResult.Attempts,
			UploadResult: uploadVal.uploadResult,
		}
		s.logUploadSuccess(pctx, successInput)
	} else {
		simCtx := StageContext{
			What:   "Upload ZIP to WordPress",
			Result: "SIMULATED - no companion plugin available",
		}
		simLog := pctx.stageLog(loglevel.Warn, publishstep.Upload, simCtx)
		s.broadcastStageLog(simLog)
	}

	return uploadVal.isActivated, nil
}

// getFileSize returns the file size or 0 on error.
func getFileSize(path string) int64 {
	return pathutil.FileSize(path)
}

// ─── Activate ────────────────────────────────────────────────────────────────

// executeActivateStage activates the plugin on WordPress
func (s *Service) executeActivateStage(pctx *publishContext, isAlreadyActivated bool) Stage {
	activateStartTime := time.Now()

	return s.runStageWithSession(pctx.SessionId, "activate", func() error {
		s.broadcastProgress(pctx.progress(publishstep.Activating, 80, "Activating plugin..."))

		if isAlreadyActivated {
			return s.logActivateSkipped(pctx)
		}

		return s.activateViaUploader(pctx, activateStartTime)
	})
}

// logActivateSkipped logs when activation was already done during upload.
func (s *Service) logActivateSkipped(pctx *publishContext) error {
	skipCtx := StageContext{
		What:   "Activate plugin on WordPress",
		Why:    "Enable plugin functionality after upload",
		Where:  pctx.SiteInfo.URL,
		Result: "SKIPPED - plugin activated during upload",
		Details: toDetails(ActivateSkipDetails{
			RemoteSlug: pctx.Mapping.RemoteSlug,
			Skipped:    true,
		}),
	}
	skipLog := pctx.stageLog(loglevel.Info, publishstep.Activate, skipCtx)
	s.broadcastStageLog(skipLog)

	return nil
}

// activateViaUploader attempts plugin activation via the Riseup Asia Uploader
func (s *Service) activateViaUploader(pctx *publishContext, startTime time.Time) error {
	if available, _, _ := pctx.WPClient.CheckRiseupAsiaAvailable(); !available {
		return s.failActivateNoUploader(pctx)
	}

	endpointURL := fmt.Sprintf("%s/wp-json/%s%s", pctx.SiteInfo.URL, wordpress.RiseupAsiaNamespace, endpoint.Enable)
	s.logActivateRequest(pctx, endpointURL)

	if err := pctx.WPClient.EnablePluginViaUploader(pctx.Mapping.RemoteSlug); err != nil {
		activateErr := apperror.Wrap(err, apperror.ErrWPConnection, "plugin activation failed")

		errInput := activationErrorInput{
			EndpointURL: endpointURL,
			StartTime:   startTime,
			Err:         activateErr,
		}
		s.logActivationError(pctx, errInput)

		return activateErr
	}

	s.logActivateSuccess(pctx, endpointURL, startTime)

	return nil
}

// failActivateNoUploader reports that activation failed because no uploader is available.
func (s *Service) failActivateNoUploader(pctx *publishContext) error {
	failCtx := StageContext{
		What:   "Activate plugin failed",
		Why:    "Riseup Asia Uploader is not available on the remote site",
		Where:  pctx.SiteInfo.URL,
		Result: "FAILED: Install the Riseup Asia Uploader companion plugin to enable activation",
	}
	failLog := pctx.stageLog(loglevel.Error, publishstep.Activate, failCtx)
	s.broadcastStageLog(failLog)

	return apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available — cannot activate plugin").
		WithURL(pctx.SiteInfo.URL)
}

// logActivateRequest broadcasts the activation request details.
func (s *Service) logActivateRequest(pctx *publishContext, endpointURL string) {
	reqDetails := toDetails(ActivateRequestDetails{
		Method:     "POST",
		RemoteSlug: pctx.Mapping.RemoteSlug,
	})
	reqCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after successful upload",
		Where:   endpointURL,
		Details: reqDetails,
	}
	reqLog := pctx.stageLog(loglevel.Info, publishstep.Activate, reqCtx)
	s.broadcastStageLog(reqLog)
}

// logActivateSuccess broadcasts activation success.
func (s *Service) logActivateSuccess(pctx *publishContext, endpointURL string, startTime time.Time) {
	successDetails := toDetails(ActivateSuccessDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		DurationMs: time.Since(startTime).Milliseconds(),
	})
	successCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after upload",
		Where:   endpointURL,
		Result:  "SUCCESS - plugin is now active",
		Details: successDetails,
	}
	successLog := pctx.stageLog(loglevel.Info, publishstep.Activate, successCtx)
	s.broadcastStageLog(successLog)
}

// ─── Rollback ────────────────────────────────────────────────────────────────

// handleRollback performs rollback when activation fails
func (s *Service) handleRollback(ctx context.Context, pctx *publishContext, preUploadBackupZip string, activateStage Stage) {
	if !pctx.Options.IsRollbackOnFailure {
		pctx.Result.RollbackStatus = stagestatus.Skipped.String()
		pctx.Result.RollbackMessage = "Rollback disabled by user"

		return
	}

	rollbackStage := s.runStageWithSession(pctx.SessionId, "rollback", func() error {
		return s.executeRollbackSteps(ctx, pctx, preUploadBackupZip, activateStage)
	})
	pctx.Result.Stages = append(pctx.Result.Stages, rollbackStage)
	s.reportRollbackOutcome(pctx, rollbackStage, pctx.Result)
}

// executeRollbackSteps deactivates the broken plugin and optionally re-uploads the backup.
func (s *Service) executeRollbackSteps(ctx context.Context, pctx *publishContext, preUploadBackupZip string, activateStage Stage) error {
	s.broadcastProgress(pctx.progress(publishstep.Rollback, 85, "Activation failed — rolling back..."))

	rollbackCtx := StageContext{
		What:  "Rolling back plugin after activation failure",
		Why:   fmt.Sprintf("Activation failed: %s", activateStage.Message),
		Where: pctx.SiteInfo.URL,
	}
	rollbackLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, rollbackCtx)
	s.broadcastStageLog(rollbackLog)

	s.rollbackDeactivate(pctx)

	return s.rollbackRestore(ctx, pctx, preUploadBackupZip)
}

// rollbackDeactivate deactivates the broken plugin during rollback.
func (s *Service) rollbackDeactivate(pctx *publishContext) {
	deactCtx := StageContext{
		What: "Deactivating broken plugin to stabilize site",
	}
	deactLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, deactCtx)
	s.broadcastStageLog(deactLog)

	if disableErr := pctx.WPClient.DisablePluginViaUploader(pctx.Mapping.RemoteSlug); disableErr != nil {
		failCtx := StageContext{
			What:   "Deactivation during rollback",
			Result: fmt.Sprintf("Could not deactivate: %s (site may already be safe)", disableErr.Error()),
		}
		failLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, failCtx)
		s.broadcastStageLog(failLog)
	}
}

// rollbackRestore re-uploads the pre-upload backup if available.
func (s *Service) rollbackRestore(ctx context.Context, pctx *publishContext, preUploadBackupZip string) error {
	if preUploadBackupZip == "" {
		noBackupCtx := StageContext{
			What:   "No pre-upload backup available",
			Result: "Plugin deactivated but files not restored. Manual intervention may be needed.",
		}
		noBackupLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, noBackupCtx)
		s.broadcastStageLog(noBackupLog)

		return nil
	}

	restoreCtx := StageContext{
		What: "Re-uploading pre-publish backup to restore previous version",
	}
	restoreLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, restoreCtx)
	s.broadcastStageLog(restoreLog)

	_, _, _, uploadErr := s.uploadPlugin(ctx, pctx.WPClient, preUploadBackupZip, pctx.Mapping.RemoteSlug)

	if uploadErr != nil {
		return apperror.Wrap(uploadErr, apperror.ErrWPConnection, "rollback upload failed")
	}

	doneCtx := StageContext{
		What:   "Rollback upload complete",
		Result: "Previous plugin version restored successfully",
	}
	doneLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, doneCtx)
	s.broadcastStageLog(doneLog)

	return nil
}

// reportRollbackOutcome logs and sets the final rollback status on the result.
func (s *Service) reportRollbackOutcome(pctx *publishContext, rollbackStage Stage, result *PublishResult) {
	if rollbackStage.Status.IsFailed() {
		result.RollbackStatus = enumstatus.Failed.String()
		result.RollbackMessage = rollbackStage.Message

		failCtx := StageContext{
			What:   "Rollback failed",
			Result: rollbackStage.Message,
		}
		failLog := pctx.stageLog(loglevel.Error, publishstep.Rollback, failCtx)
		s.broadcastStageLog(failLog)
	} else {
		result.RollbackStatus = enumstatus.Success.String()
		result.RollbackMessage = "Previous version restored"

		successCtx := StageContext{
			What:   "Rollback completed successfully",
			Result: "Site should be stable with previous plugin version",
		}
		successLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, successCtx)
		s.broadcastStageLog(successLog)
	}
}

// ─── Cleanup ─────────────────────────────────────────────────────────────────

// executeCleanupStage marks files as synced
func (s *Service) executeCleanupStage(ctx context.Context, pctx *publishContext) Stage {
	return s.runStage("cleanup", func() error {
		cleanProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Cleanup,
			Progress: 95,
			Message:  "Marking files as synced...",
		}
		s.broadcastProgress(cleanProgress)

		cleanLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  "Updating local sync state",
		}
		s.broadcastDetailedLog(cleanLog)

		if pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0 {
			return s.syncService.MarkSynced(ctx, pctx.PluginId, pctx.SiteId, pctx.Options.Files)
		}

		return s.syncService.ClearChanges(ctx, pctx.PluginId)
	})
}

// countFilesUpdated returns the number of files updated based on publish mode
func (s *Service) countFilesUpdated(options PublishOptions, pluginInfo models.Plugin, fileCount int) int {
	if options.Mode.IsSelected() {
		return len(options.Files)
	}

	return pluginInfo.FileCount
}

// ─── Completion ──────────────────────────────────────────────────────────────

// broadcastCompletion sends the final publish status broadcast
func (s *Service) broadcastCompletion(pctx *publishContext) {
	completionStep, completionMessage := resolveCompletionStatus(pctx.Result)

	logLevel := loglevel.Info
	if !pctx.Result.IsSuccess {
		logLevel = loglevel.Error
	}

	completionDetails := toDetails(CompletionDetails{
		IsSuccess:    pctx.Result.IsSuccess,
		FilesUpdated: pctx.Result.FilesUpdated,
		DurationMs:   pctx.Result.Duration,
	})
	completionLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    logLevel,
		Step:     publishstep.Complete,
		Message:  completionMessage,
		Details:  completionDetails,
	}
	s.broadcastDetailedLog(completionLog)

	completionProgress := ProgressInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Step:     completionStep,
		Progress: 100,
		Message:  completionMessage,
	}
	s.broadcastProgress(completionProgress)
}

// resolveCompletionStatus returns step and message for completion broadcast.
func resolveCompletionStatus(result *PublishResult) (publishstep.Variant, string) {
	if result.IsSuccess {
		return publishstep.Completed, fmt.Sprintf("Published %d files in %dms", result.FilesUpdated, result.Duration)
	}
	msg := result.ErrorMessage
	if msg == "" {
		msg = "Publish failed - check logs for details"
	}

	return publishstep.Failed, msg
}

// ─── History ─────────────────────────────────────────────────────────────────

// recordHistory records the publish result to the history service
func (s *Service) recordHistory(pluginInfo models.Plugin, siteInfo *models.Site, options PublishOptions, result *PublishResult) {
	if s.historyService == nil {
		return
	}

	input := historyEntryInput{
		PluginInfo: pluginInfo,
		SiteInfo:   siteInfo,
		Options:    options,
		Result:     result,
	}
	entry := buildHistoryEntry(input)
	if _, err := s.historyService.Record(entry); err != nil {
		s.log.Error("Failed to record publish history", "error", err)
	}
}

// historyEntryInput bundles parameters for buildHistoryEntry.
type historyEntryInput struct {
	PluginInfo models.Plugin
	SiteInfo   *models.Site
	Options    PublishOptions
	Result     *PublishResult
}

// buildHistoryEntry constructs a PublishHistory from the publish context.
func buildHistoryEntry(input historyEntryInput) models.PublishHistory {
	historyStatus := enumstatus.Success.String()
	if !input.Result.IsSuccess {
		historyStatus = enumstatus.Failed.String()
	}

	return models.PublishHistory{
		PluginId:         input.PluginInfo.ID,
		PluginName:       input.PluginInfo.Name,
		SiteId:           input.SiteInfo.ID,
		SiteName:         input.SiteInfo.Name,
		SiteUrl:          input.SiteInfo.URL,
		SessionId:        input.Result.SessionId,
		Status:           historyStatus,
		Mode:             input.Options.Mode.Value(),
		FilesUpdated:     input.Result.FilesUpdated,
		ActivationStatus: input.Result.ActivationStatus,
		RollbackStatus:   input.Result.RollbackStatus,
		RollbackMessage:  input.Result.RollbackMessage,
		ErrorMessage:     input.Result.ErrorMessage,
		DurationMs:       input.Result.Duration,
	}
}
