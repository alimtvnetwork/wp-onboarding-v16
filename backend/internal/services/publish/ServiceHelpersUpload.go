package publish

import (
	"fmt"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// logUploadError logs a structured upload error
func (s *Service) logUploadError(pctx *publishContext, attempts int, appErr *apperror.AppError) {
	inner := buildUploadErrorInner(pctx.Mapping.RemoteSlug, attempts, appErr)

	errCtx := StageContext{
		What:    fmt.Sprintf("Upload ZIP to %s", pctx.SiteInfo.URL),
		Result:  fmt.Sprintf("FAILED: %s", appErr.Error()),
		Details: toDetails(inner),
	}
	errLog := pctx.stageLog(loglevel.Error, publishstep.Upload, errCtx)
	s.broadcastStageLog(errLog)
}

// buildUploadErrorInner extracts structured error info from an AppError.
func buildUploadErrorInner(slug string, attempts int, appErr *apperror.AppError) UploadErrorInner {
	inner := UploadErrorInner{
		RemoteSlug: slug,
		Attempts:   attempts,
		Code:       appErr.Code,
	}

	if cause := appErr.Unwrap(); cause != nil {
		if apiErr := wordpress.ExtractAPIError(cause); apiErr != nil {
			inner.Status = apiErr.StatusCode
			inner.Response = truncateString(apiErr.ResponseBody, 2000)
		}
	}

	return inner
}

// logUploadSuccessInput bundles parameters for logUploadSuccess.
type logUploadSuccessInput struct {
	ZipSize      int64
	StartTime    time.Time
	IsActivated  bool
	Attempts     int
	UploadResult *wordpress.OnboardUploadResult
}

// logUploadSuccess logs a structured upload success
func (s *Service) logUploadSuccess(pctx *publishContext, input logUploadSuccessInput) {
	resultMsg := "Plugin uploaded successfully"
	if input.IsActivated {
		resultMsg = "Plugin uploaded and activated"
	}

	inner := buildUploadSuccessInner(pctx.Mapping.RemoteSlug, input)
	successCtx := StageContext{
		What:    fmt.Sprintf("Upload ZIP (%s)", formatBytes(input.ZipSize)),
		Result:  resultMsg,
		Details: toDetails(inner),
	}
	successLog := pctx.stageLog(loglevel.Info, publishstep.Upload, successCtx)
	s.broadcastStageLog(successLog)
}

// buildUploadSuccessInner constructs success detail struct.
func buildUploadSuccessInner(slug string, input logUploadSuccessInput) UploadSuccessInner {
	inner := UploadSuccessInner{
		RemoteSlug: slug,
		Activated:  input.IsActivated,
		DurationMs: time.Since(input.StartTime).Milliseconds(),
		Attempts:   input.Attempts,
	}

	if input.UploadResult != nil {
		inner.Version = input.UploadResult.Version
		inner.Overwritten = input.UploadResult.Overwritten
	}

	return inner
}

// activationErrorInput bundles parameters for logActivationError.
type activationErrorInput struct {
	EndpointURL string
	StartTime   time.Time
	Err         *apperror.AppError
}

// logActivationError logs a structured activation error
func (s *Service) logActivationError(pctx *publishContext, input activationErrorInput) {
	inner := buildActivateErrorInner(pctx.Mapping.RemoteSlug, input.StartTime, input.Err)

	errCtx := StageContext{
		What:    "Activate plugin via Riseup Asia Uploader",
		Why:     "Enable plugin after upload",
		Where:   input.EndpointURL,
		Result:  fmt.Sprintf("FAILED: %s", input.Err.Error()),
		Details: toDetails(inner),
	}
	errLog := pctx.stageLog(loglevel.Error, publishstep.Activate, errCtx)
	s.broadcastStageLog(errLog)
}

// buildActivateErrorInner constructs activation error details.
func buildActivateErrorInner(slug string, startTime time.Time, appErr *apperror.AppError) ActivateErrorInner {
	inner := ActivateErrorInner{
		RemoteSlug: slug,
		DurationMs: time.Since(startTime).Milliseconds(),
	}

	if cause := appErr.Unwrap(); cause != nil {
		if apiErr := wordpress.ExtractAPIError(cause); apiErr != nil {
			inner.Request = &ActivateRequestInfo{
				Method:   apiErr.Method,
				Endpoint: apiErr.Endpoint,
				URL:      apiErr.URL,
			}
			inner.Response = &ActivateResponseInfo{
				Status: apiErr.StatusCode,
				Body:   truncateString(apiErr.ResponseBody, 2000),
			}
		}
	}

	return inner
}
