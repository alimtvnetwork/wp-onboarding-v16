package publish

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/enums/endpoint"
	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
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

	retryCfg := DefaultRetryConfig()
	uploadOutcome, retryResult := withRetry(ctx, retryCfg, "upload", func(attempt int) (UploadOutcome, *apperror.AppError) {
		result := s.uploadPlugin(ctx, pctx.WPClient, zipPath, pctx.Mapping.RemoteSlug)
		if result.HasError() {
			return UploadOutcome{}, result.AppError()
		}

		return result.Value(), nil
	})

	if retryResult.LastError != nil {
		s.logUploadError(pctx, retryResult.Attempts, retryResult.LastError)

		return false, apperror.Wrap(retryResult.LastError, apperror.ErrWPConnection, "plugin upload failed")
	}

	if uploadOutcome.IsPerformed {
		successInput := logUploadSuccessInput{
			ZipSize:      zipSize,
			StartTime:    startTime,
			IsActivated:  uploadOutcome.IsActivated,
			Attempts:     retryResult.Attempts,
			UploadResult: uploadOutcome.UploadResult,
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

	return uploadOutcome.IsActivated, nil
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
	availability, _ := pctx.WPClient.CheckRiseupAsiaAvailable()
	isUploaderMissing := availability == nil || !availability.Available
	if isUploaderMissing {
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
