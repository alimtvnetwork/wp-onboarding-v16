package publish

import (
	"context"
	"time"

	"wp-plugin-publish/internal/enums/endpointtype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// tryQUploadFallback attempts upload via QUpload as a fallback.
func (s *Service) tryQUploadFallback(_ context.Context, wpClient *wordpress.Client, zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Info("Checking QUpload availability for fallback", "slug", slug)

	qAvailResult := wpClient.CheckQUploadAvailable()
	qAvail := qAvailResult.Value()

	if qAvail.IsUnavailable() {
		s.log.Error("QUpload also not available — no uploader on remote site",
			"slug", slug,
		)

		return s.simulateUpload(zipPath, slug)
	}

	s.log.Info("QUpload available, proceeding with fallback upload",
		"slug", slug,
		"namespace", qAvail.Namespace,
	)

	return s.performQUploadUpload(wpClient, zipPath, slug)
}

// performQUploadUpload uploads via QUpload.
func (s *Service) performQUploadUpload(wpClient *wordpress.Client, zipPath, slug string) apperror.Result[UploadOutcome] {
	s.log.Info("Using QUpload for upload", "slug", slug)

	uploadResult := s.callQUploadUpload(wpClient, zipPath, slug)
	if uploadResult.HasError() {
		s.log.Error("QUpload upload failed",
			"slug", slug,
			"error", uploadResult.AppError().Error(),
		)

		return apperror.Fail[UploadOutcome](
			apperror.Wrap(uploadResult.AppError(), apperror.ErrWPUploadFailed, "QUpload fallback upload failed"),
		)
	}

	result := uploadResult.Value()
	s.logQUploadSuccess(slug, result)

	return apperror.Ok(buildUploadOutcome(slug, result))
}

// callQUploadUpload sends the upload request to QUpload.
func (s *Service) callQUploadUpload(wpClient *wordpress.Client, zipPath, slug string) apperror.Result[*wordpress.UploaderUploadResult] {
	uploadInput := wordpress.UploadInput{
		ZipPath:      zipPath,
		Slug:         slug,
		IsActivate:   true,
		UploadSource: uploadsource.RestApi,
	}

	return wpClient.UploadPluginViaQUpload(uploadInput)
}

// logQUploadSuccess logs the successful QUpload fallback result.
func (s *Service) logQUploadSuccess(slug string, result *wordpress.UploaderUploadResult) {
	s.log.Info("Plugin uploaded via QUpload (fallback)",
		"slug", slug,
		"success", result.Success,
		"message", result.Message,
		"activated", result.Activated,
	)
}

// activateViaQUploadFallback attempts activation via QUpload.
func (s *Service) activateViaQUploadFallback(pctx *publishContext, startTime time.Time) *apperror.AppError {
	s.log.Info("Checking QUpload availability for activation fallback",
		"slug", pctx.Mapping.RemoteSlug,
	)

	qAvailResult := pctx.WPClient.CheckQUploadAvailable()
	qAvail := qAvailResult.Value()

	if qAvail.IsUnavailable() {
		s.log.Error("QUpload also not available for activation",
			"slug", pctx.Mapping.RemoteSlug,
		)

		return s.failActivateNoUploader(pctx)
	}

	s.log.Info("Using QUpload for activation fallback",
		"slug", pctx.Mapping.RemoteSlug,
		"namespace", qAvail.Namespace,
	)

	endpointUrl := wordpress.BuildWpPluginUrl(
		pctx.SiteInfo.Url,
		wordpress.QUploadNamespace,
		endpointtype.Enable,
	)
	s.logActivateRequest(pctx, endpointUrl)

	return s.executeQUploadActivation(pctx, endpointUrl, startTime)
}

// executeQUploadActivation performs plugin activation via QUpload.
func (s *Service) executeQUploadActivation(pctx *publishContext, endpointUrl string, startTime time.Time) *apperror.AppError {
	enableErr := pctx.WPClient.EnablePluginViaQUpload(pctx.Mapping.RemoteSlug)
	if enableErr != nil {
		activateErr := apperror.Wrap(enableErr, apperror.ErrWPConnection, "QUpload plugin activation failed")

		s.log.Error("QUpload activation failed",
			"slug", pctx.Mapping.RemoteSlug,
			"error", activateErr.Error(),
		)

		errInput := activationErrorInput{
			EndpointUrl: endpointUrl,
			StartTime:   startTime,
			Err:         activateErr,
		}
		s.logActivationError(pctx, errInput)

		return activateErr
	}

	s.log.Info("Plugin activated via QUpload (fallback)",
		"slug", pctx.Mapping.RemoteSlug,
	)
	s.logActivateSuccess(pctx, endpointUrl, startTime)

	return nil
}
