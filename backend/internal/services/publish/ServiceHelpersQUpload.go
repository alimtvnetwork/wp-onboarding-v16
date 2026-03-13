package publish

import (
	"context"

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
