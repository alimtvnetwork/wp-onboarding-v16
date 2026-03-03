package publish

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// ─── Upload ──────────────────────────────────────────────────────────────────

// executeUploadStage uploads the plugin ZIP to WordPress
func (s *Service) executeUploadStage(ctx context.Context, pctx *publishContext, zipPath string) (bool, Stage) {
	var isAlreadyActivated bool
	uploadStartTime := time.Now()

	stage := s.runStageWithSession(pctx.SessionId, "upload", func() error {
		activated, appErr := s.performUpload(performUploadInput{
			Ctx:       ctx,
			Pctx:      pctx,
			ZipPath:   zipPath,
			StartTime: uploadStartTime,
		})
		if appErr != nil {
			return appErr
		}

		isAlreadyActivated = activated

		return nil
	})

	return isAlreadyActivated, stage
}

// performUploadInput bundles parameters for performUpload.
type performUploadInput struct {
	Ctx       context.Context
	Pctx      *publishContext
	ZipPath   string
	StartTime time.Time
}

// performUpload handles the upload retry and result logging.
func (s *Service) performUpload(input performUploadInput) (bool, *apperror.AppError) {
	zipSize := getFileSize(input.ZipPath)
	s.broadcastProgress(input.Pctx.progress(publishstep.Uploading, 60, fmt.Sprintf("Uploading %s to WordPress...", formatBytes(zipSize))))

	uploadOutcome, retryResult := s.attemptUploadWithRetry(input.Ctx, input.Pctx, input.ZipPath)

	if retryResult.LastError != nil {
		return false, s.handleUploadRetryFailure(input.Pctx, retryResult)
	}

	s.logUploadOutcome(uploadOutcomeLogInput{
		Pctx:      input.Pctx,
		Outcome:   uploadOutcome,
		ZipSize:   zipSize,
		StartTime: input.StartTime,
		Attempts:  retryResult.Attempts,
	})

	return uploadOutcome.IsActivated, nil
}

// attemptUploadWithRetry runs the upload with retry logic.
func (s *Service) attemptUploadWithRetry(ctx context.Context, pctx *publishContext, zipPath string) (UploadOutcome, RetryResult) {
	retryCfg := DefaultRetryConfig()

	return withRetry(ctx, retryCfg, "upload", func(attempt int) (UploadOutcome, *apperror.AppError) {
		result := s.uploadPlugin(ctx, pctx.WPClient, zipPath, pctx.Mapping.RemoteSlug)
		if result.HasError() {
			return UploadOutcome{}, result.AppError()
		}

		return result.Value(), nil
	})
}

// handleUploadRetryFailure logs the error and returns an AppError.
func (s *Service) handleUploadRetryFailure(pctx *publishContext, retryResult RetryResult) *apperror.AppError {
	s.logUploadError(pctx, retryResult.Attempts, retryResult.LastError)

	return apperror.Wrap(retryResult.LastError, apperror.ErrWPConnection, "plugin upload failed")
}

// uploadOutcomeLogInput bundles parameters for logUploadOutcome and logPerformedUpload.
type uploadOutcomeLogInput struct {
	Pctx      *publishContext
	Outcome   UploadOutcome
	ZipSize   int64
	StartTime time.Time
	Attempts  int
}

// logUploadOutcome logs the upload result based on whether it was performed.
func (s *Service) logUploadOutcome(input uploadOutcomeLogInput) {
	if input.Outcome.IsPerformed {
		s.logPerformedUpload(input)

		return
	}

	s.logSimulatedUpload(input.Pctx)
}

// logPerformedUpload logs a real upload that was performed.
func (s *Service) logPerformedUpload(input uploadOutcomeLogInput) {
	successInput := logUploadSuccessInput{
		ZipSize:      input.ZipSize,
		StartTime:    input.StartTime,
		IsActivated:  input.Outcome.IsActivated,
		Attempts:     input.Attempts,
		UploadResult: input.Outcome.UploadResult,
	}
	s.logUploadSuccess(input.Pctx, successInput)
}

// logSimulatedUpload logs a simulated upload when no companion plugin is available.
func (s *Service) logSimulatedUpload(pctx *publishContext) {
	simCtx := StageContext{
		What:   "Upload ZIP to WordPress",
		Result: "SIMULATED - no companion plugin available",
	}
	simLog := pctx.stageLog(loglevel.Warn, publishstep.Upload, simCtx)
	s.broadcastStageLog(simLog)
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
			s.logActivateSkipped(pctx)

			return nil
		}

		appErr := s.activateViaUploader(pctx, activateStartTime)
		if appErr != nil {
			return appErr
		}

		return nil
	})
}

// logActivateSkipped logs when activation was already done during upload.
func (s *Service) logActivateSkipped(pctx *publishContext) {
	skipCtx := StageContext{
		What:   "Activate plugin on WordPress",
		Why:    "Enable plugin functionality after upload",
		Where:  pctx.SiteInfo.Url,
		Result: "SKIPPED - plugin activated during upload",
		Details: toDetails(ActivateSkipDetails{
			RemoteSlug: pctx.Mapping.RemoteSlug,
			Skipped:    true,
		}),
	}
	skipLog := pctx.stageLog(loglevel.Info, publishstep.Activate, skipCtx)
	s.broadcastStageLog(skipLog)
}

// activateViaUploader attempts plugin activation via the Riseup Asia Uploader
func (s *Service) activateViaUploader(pctx *publishContext, startTime time.Time) *apperror.AppError {
	availResult := pctx.WPClient.CheckRiseupAsiaAvailable()
	availability := availResult.ValueOr(nil)

	if availability.IsUnavailable() {
		return s.failActivateNoUploader(pctx)
	}

	endpointUrl := buildActivateEndpointUrl(pctx.SiteInfo.Url)
	s.logActivateRequest(pctx, endpointUrl)

	return s.executeActivation(pctx, endpointUrl, startTime)
}

// buildActivateEndpointUrl constructs the activation endpoint URL.
func buildActivateEndpointUrl(siteUrl string) string {
	return wordpress.BuildWpPluginUrl(siteUrl, wordpress.RiseupAsiaNamespace, endpoint.Enable)
}

// executeActivation performs the actual plugin activation call.
func (s *Service) executeActivation(pctx *publishContext, endpointUrl string, startTime time.Time) *apperror.AppError {
	enableErr := pctx.WPClient.EnablePluginViaUploader(pctx.Mapping.RemoteSlug)
	if enableErr != nil {
		activateErr := apperror.Wrap(enableErr, apperror.ErrWPConnection, "plugin activation failed")

		errInput := activationErrorInput{
			EndpointUrl: endpointUrl,
			StartTime:   startTime,
			Err:         activateErr,
		}
		s.logActivationError(pctx, errInput)

		return activateErr
	}

	s.logActivateSuccess(pctx, endpointUrl, startTime)

	return nil
}

// failActivateNoUploader reports that activation failed because no uploader is available.
func (s *Service) failActivateNoUploader(pctx *publishContext) *apperror.AppError {
	failCtx := StageContext{
		What:   "Activate plugin failed",
		Why:    "Riseup Asia Uploader is not available on the remote site",
		Where:  pctx.SiteInfo.Url,
		Result: "FAILED: Install the Riseup Asia Uploader companion plugin to enable activation",
	}
	failLog := pctx.stageLog(loglevel.Error, publishstep.Activate, failCtx)
	s.broadcastStageLog(failLog)

	return apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available — cannot activate plugin").
		WithUrl(pctx.SiteInfo.Url)
}

// logActivateRequest broadcasts the activation request details.
func (s *Service) logActivateRequest(pctx *publishContext, endpointUrl string) {
	reqDetails := toDetails(ActivateRequestDetails{
		Method:     httpmethod.Post.Value(),
		RemoteSlug: pctx.Mapping.RemoteSlug,
	})
	reqCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after successful upload",
		Where:   endpointUrl,
		Details: reqDetails,
	}
	reqLog := pctx.stageLog(loglevel.Info, publishstep.Activate, reqCtx)
	s.broadcastStageLog(reqLog)
}

// logActivateSuccess broadcasts activation success.
func (s *Service) logActivateSuccess(pctx *publishContext, endpointUrl string, startTime time.Time) {
	successDetails := toDetails(ActivateSuccessDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		DurationMs: time.Since(startTime).Milliseconds(),
	})
	successCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after upload",
		Where:   endpointUrl,
		Result:  "SUCCESS - plugin is now active",
		Details: successDetails,
	}
	successLog := pctx.stageLog(loglevel.Info, publishstep.Activate, successCtx)
	s.broadcastStageLog(successLog)
}
